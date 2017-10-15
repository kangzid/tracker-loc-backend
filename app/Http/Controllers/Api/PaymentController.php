<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Voucher;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct()
    {
        \Midtrans\Config::$serverKey = config('services.midtrans.server_key');
        \Midtrans\Config::$isProduction = config('services.midtrans.is_production', false);
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        // Fix SSL Error di Localhost (Windows) menggunakan Environment Variable
        if (!config('services.midtrans.is_production')) {
            $caPath = base_path('cacert.pem');
            if (file_exists($caPath)) {
                putenv('CURL_CA_BUNDLE=' . $caPath);
                putenv('SSL_CERT_FILE=' . $caPath);
            }
        }
    }

    /**
     * GET /api/plans — Daftar semua paket aktif (public)
     */
    public function getPlans()
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price_monthly')
            ->get();
        return response()->json($plans);
    }

    /**
     * POST /api/payment/validate-voucher
     * Body: { voucher_code, plan_id? }
     */
    public function validateVoucher(Request $request)
    {
        $request->validate([
            'voucher_code' => 'required|string',
            'plan_id'      => 'nullable|integer',
        ]);

        $voucher = Voucher::where('code', strtoupper($request->voucher_code))->first();

        if (!$voucher || !$voucher->isValid()) {
            return response()->json(['valid' => false, 'message' => 'Kode voucher tidak valid atau sudah kadaluarsa.'], 404);
        }

        $discount = $voucher->discount_percentage;
        $response = [
            'valid' => true,
            'discount_percentage' => $discount,
            'message' => "Voucher berhasil! Diskon {$discount}%",
        ];

        if ($request->plan_id) {
            $plan = Plan::findOrFail($request->plan_id);
            $finalPrice = $plan->price_monthly * (1 - ($discount / 100));
            $response['original_price'] = $plan->price_monthly;
            $response['final_price'] = (int) $finalPrice;
            $response['savings'] = (int) ($plan->price_monthly - $finalPrice);
        }

        return response()->json($response);
    }

    /**
     * POST /api/payment/create-transaction
     * Body: { plan_id, voucher_code? }
     */
    public function createTransaction(Request $request)
    {
        $user = $request->user();
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $plan = Plan::findOrFail($request->plan_id);

        if ($plan->is_custom) {
            return response()->json(['message' => 'Untuk paket Custom, silakan hubungi tim kami secara langsung.'], 422);
        }

        // Hitung harga setelah voucher
        $originalPrice = $plan->price_monthly;
        $discountApplied = 0;
        $finalPrice = $originalPrice;
        $voucherCode = null;

        if ($request->voucher_code) {
            $voucher = Voucher::where('code', strtoupper($request->voucher_code))->first();
            if ($voucher && $voucher->isValid()) {
                $discountApplied = $voucher->discount_percentage;
                $finalPrice = (int) ($originalPrice * (1 - ($discountApplied / 100)));
                $voucherCode = $voucher->code;
            }
        }

        $orderId = 'NATRA-' . strtoupper(Str::random(8)) . '-' . time();

        // Buat/Update subscription dengan status pending
        $subscription = Subscription::updateOrCreate(
            ['user_id' => $user->id],
            [
                'pending_plan_id'  => $plan->id,
                'plan_price'       => $finalPrice,
                'midtrans_order_id'=> $orderId,
                'payment_status'   => 'pending',
                'company_name'     => $user->subscription?->company_name ?? $user->name,
                // Jangan update plan_id/plan dulu di sini agar UI tidak berubah mendahului pembayaran
                'started_at'       => $user->subscription?->started_at ?? now(),
                'expired_at'       => $user->subscription?->expired_at ?? now(), 
                'status'           => $user->subscription?->status ?? 'inactive',
                'voucher_code'     => $voucherCode,
                'discount_applied' => $discountApplied,
            ]
        );

        // Buat Snap Token Midtrans
        $params = [
            'transaction_details' => [
                'order_id' => $orderId,
                'gross_amount' => max(1, $finalPrice), // min 1 rupiah
            ],
            'item_details' => [
                [
                    'id'       => $plan->slug,
                    'price'    => max(1, $finalPrice),
                    'quantity' => 1,
                    'name'     => 'Natra HRIS — ' . $plan->name . ' Plan (1 Bulan)',
                ]
            ],
            'customer_details' => [
                'first_name' => $user->name,
                'email'      => $user->email,
            ],
            'callbacks' => [
                'finish' => env('FRONTEND_URL', 'http://localhost:5173') . '/admin/subscription',
            ],
        ];

        try {
            // Kita gunakan Laravel Http Client (Guzzle) agar bisa bypass SSL di localhost dengan mudah
            $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->withHeaders([
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode(config('services.midtrans.server_key') . ':'),
                ])
                ->post('https://app.sandbox.midtrans.com/snap/v1/transactions', $params);

            if (!$response->successful()) {
                throw new \Exception($response->json()['error_messages'][0] ?? 'Gagal menghubungi Midtrans.');
            }

            $resultData = $response->json();
            $snapToken = $resultData['token'];
            
            // 1. Catat di tabel Transactions (Riwayat Permanen)
            $transactionRecord = Transaction::create([
                'user_id'           => $user->id,
                'plan_id'           => $plan->id,
                'plan_name'         => $plan->name,
                'company_name'      => $user->subscription?->company_name ?? $user->name,
                'midtrans_order_id' => $orderId,
                'snap_token'        => $snapToken, // Simpan token di sini
                'original_price'    => $originalPrice,
                'discount_applied'  => $discountApplied,
                'final_price'       => $finalPrice,
                'voucher_code'      => $voucherCode,
                'payment_status'    => 'pending',
            ]);

            $subscription->update(['snap_token' => $snapToken]);

            return response()->json([
                'snap_token'    => $snapToken,
                'order_id'      => $orderId,
                'plan'          => $plan,
                'original_price'=> $originalPrice,
                'discount'      => $discountApplied,
                'final_price'   => $finalPrice,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal menghubungi payment gateway: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/payment/webhook — Terima notifikasi dari Midtrans
     * Tidak membutuhkan auth token (Midtrans yang memanggil langsung)
     */
    public function webhook(Request $request)
    {
        $notif = new \Midtrans\Notification();

        $transaction = $notif->transaction_status;
        $orderId     = $notif->order_id;
        $fraudStatus = $notif->fraud_status;

        $transactionRecord = Transaction::where('midtrans_order_id', $orderId)->first();
        $subscription = Subscription::where('midtrans_order_id', $orderId)->first();

        if (!$transactionRecord || !$subscription) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($transaction == 'capture') {
            $newStatus = ($fraudStatus == 'accept') ? 'settlement' : 'pending';
        } elseif ($transaction == 'settlement') {
            $newStatus = 'settlement';
        } elseif (in_array($transaction, ['cancel', 'deny', 'expire'])) {
            $newStatus = $transaction;
        } else {
            $newStatus = $transaction;
        }

        // Update riwayat transaksi
        $transactionRecord->update([
            'payment_status' => $newStatus,
            'payment_type'   => $notif->payment_type,
            'payload'        => $request->all(),
        ]);

        // Update status subscription saat ini
        $subscription->update(['payment_status' => $newStatus]);

        // Jika pembayaran berhasil, aktifkan subscription 30 hari & update kuota
        if ($newStatus === 'settlement') {
            $plan = Plan::find($subscription->pending_plan_id ?? $subscription->plan_id);
            
            $subscription->update([
                'status'        => 'active',
                'plan_id'       => $plan->id,
                'plan'          => $plan->slug,
                'max_employees' => $plan->max_employees,
                'max_vehicles'  => $plan->max_vehicles,
                'started_at'    => now(),
                'expired_at'    => now()->addDays(30),
                'pending_plan_id' => null, // Reset pending
            ]);

            // Increment voucher usage if applicable
            if ($subscription->voucher_code) {
                Voucher::where('code', $subscription->voucher_code)->increment('used_count');
            }
        }

        return response()->json(['message' => 'Webhook processed']);
    }

    /**
     * POST /api/payment/sync-status
     * Body: { order_id }
     * Digunakan untuk sinkronisasi manual (terutama di localhost karena webhook tidak bisa masuk)
     */
    public function syncStatus(Request $request)
    {
        $request->validate(['order_id' => 'required|string']);
        
        $transactionRecord = Transaction::where('midtrans_order_id', $request->order_id)->first();
        $subscription = Subscription::where('midtrans_order_id', $request->order_id)->first();

        if (!$subscription) return response()->json(['message' => 'Order tidak ditemukan'], 404);

        try {
            $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->withHeaders([
                    'Accept'        => 'application/json',
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode(config('services.midtrans.server_key') . ':'),
                ])
                ->get("https://api.sandbox.midtrans.com/v2/{$request->order_id}/status");

            if (!$response->successful()) throw new \Exception('Gagal cek status ke Midtrans.');

            $data = $response->json();
            $transactionStatus = $data['transaction_status'];
            
            $newStatus = $transactionStatus;
            if ($transactionStatus == 'capture' || $transactionStatus == 'settlement') {
                $newStatus = 'settlement';
            }

            // Update riwayat transaksi jika ada
            if ($transactionRecord) {
                $transactionRecord->update([
                    'payment_status' => $newStatus,
                    'payment_type'   => $data['payment_type'] ?? null,
                    'payload'        => $data,
                ]);
            }

            $subscription->update(['payment_status' => $newStatus]);

            // Jika settlement, update data plan & kuota
            if ($newStatus === 'settlement') {
                $plan = Plan::find($subscription->pending_plan_id ?? $subscription->plan_id);
                
                $subscription->update([
                    'status'        => 'active',
                    'plan_id'       => $plan->id,
                    'plan'          => $plan->slug,
                    'max_employees' => $plan->max_employees,
                    'max_vehicles'  => $plan->max_vehicles,
                    'started_at'    => now(),
                    'expired_at'    => now()->addDays(30),
                    'pending_plan_id' => null, // Reset pending
                ]);

                if ($subscription->voucher_code) {
                    Voucher::where('code', $subscription->voucher_code)->increment('used_count');
                }
            }

            return response()->json([
                'payment_status' => $newStatus,
                'subscription_status' => $subscription->status,
                'message' => 'Status berhasil disinkronisasi.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/superadmin/transactions
     */
    public function getTransactions()
    {
        $transactions = Transaction::with(['user', 'plan'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($transactions);
    }
}
