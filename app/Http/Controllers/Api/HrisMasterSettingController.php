<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HrisTrainingCategory;
use App\Models\HrisKpiPeriod;
use App\Models\HrisComplianceDocType;
use App\Models\HrisAssetCategory;

class HrisMasterSettingController extends Controller
{
    private function getTenantId(Request $request)
    {
        return $request->user()->isAdmin() ? $request->user()->id : ($request->user()->admin_id ?? $request->user()->id);
    }

    // ==========================================
    // 1. Training Categories
    // ==========================================
    public function getTrainingCategories(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $cats = HrisTrainingCategory::where('tenant_id', $tenantId)->orderBy('id', 'asc')->get();

        if ($cats->isEmpty()) {
            $defaults = [
                ['name' => 'Safety & Defensive Driving', 'code' => 'SAFETY', 'description' => 'Pelatihan keselamatan berkendara & kepatuhan lalu lintas.'],
                ['name' => 'K3 & Keselamatan Kerja', 'code' => 'K3', 'description' => 'Standar kesehatan dan keselamatan kerja lapangan.'],
                ['name' => 'SOP Layanan & Pengantaran', 'code' => 'SOP', 'description' => 'Standar operasional penjemputan dan pengantaran armada.'],
                ['name' => 'Perawatan & Teknis Armada', 'code' => 'TEKNIS', 'description' => 'Perawatan berkala mesin dan kelayakan kendaraan.'],
                ['name' => 'Customer Service & Komunikasi', 'code' => 'SERVICE', 'description' => 'Etika pelayanan dan komunikasi profesional.'],
            ];
            foreach ($defaults as $d) {
                HrisTrainingCategory::create(array_merge($d, ['tenant_id' => $tenantId, 'is_active' => true]));
            }
            $cats = HrisTrainingCategory::where('tenant_id', $tenantId)->orderBy('id', 'asc')->get();
        }

        return response()->json(['status' => 'success', 'data' => $cats]);
    }

    public function storeTrainingCategory(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $request->validate(['name' => 'required|string|max:255']);

        $cat = HrisTrainingCategory::create([
            'tenant_id' => $tenantId,
            'name' => $request->name,
            'code' => $request->code ?: strtoupper(substr($request->name, 0, 6)),
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json(['status' => 'success', 'data' => $cat], 201);
    }

    public function updateTrainingCategory(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $cat = HrisTrainingCategory::where('tenant_id', $tenantId)->findOrFail($id);
        $cat->update($request->only(['name', 'code', 'description', 'is_active']));
        return response()->json(['status' => 'success', 'data' => $cat]);
    }

    public function deleteTrainingCategory(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        HrisTrainingCategory::where('tenant_id', $tenantId)->where('id', $id)->delete();
        return response()->json(['status' => 'success', 'message' => 'Kategori pelatihan berhasil dihapus.']);
    }

    // ==========================================
    // 2. KPI Evaluation Periods
    // ==========================================
    public function getKpiPeriods(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $periods = HrisKpiPeriod::where('tenant_id', $tenantId)->orderBy('start_date', 'desc')->get();

        if ($periods->isEmpty()) {
            $defaults = [
                ['name' => 'Kuartal 3 2026 (Q3)', 'period_type' => 'quarterly', 'start_date' => '2026-07-01', 'end_date' => '2026-09-30', 'status' => 'open', 'description' => 'Evaluasi kinerja triwulan ketiga 2026.'],
                ['name' => 'Semester 2 2026', 'period_type' => 'semester', 'start_date' => '2026-07-01', 'end_date' => '2026-12-31', 'status' => 'open', 'description' => 'Evaluasi kinerja semester kedua 2026.'],
                ['name' => 'Kuartal 2 2026 (Q2)', 'period_type' => 'quarterly', 'start_date' => '2026-04-01', 'end_date' => '2026-06-30', 'status' => 'closed', 'description' => 'Evaluasi kinerja triwulan kedua 2026 (Selesai).'],
            ];
            foreach ($defaults as $d) {
                HrisKpiPeriod::create(array_merge($d, ['tenant_id' => $tenantId]));
            }
            $periods = HrisKpiPeriod::where('tenant_id', $tenantId)->orderBy('start_date', 'desc')->get();
        }

        return response()->json(['status' => 'success', 'data' => $periods]);
    }

    public function storeKpiPeriod(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $period = HrisKpiPeriod::create([
            'tenant_id' => $tenantId,
            'name' => $request->name,
            'period_type' => $request->period_type ?: 'quarterly',
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => $request->status ?: 'open',
            'description' => $request->description,
        ]);

        return response()->json(['status' => 'success', 'data' => $period], 201);
    }

    public function updateKpiPeriod(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $period = HrisKpiPeriod::where('tenant_id', $tenantId)->findOrFail($id);
        $period->update($request->only(['name', 'period_type', 'start_date', 'end_date', 'status', 'description']));
        return response()->json(['status' => 'success', 'data' => $period]);
    }

    public function deleteKpiPeriod(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        HrisKpiPeriod::where('tenant_id', $tenantId)->where('id', $id)->delete();
        return response()->json(['status' => 'success', 'message' => 'Periode KPI berhasil dihapus.']);
    }

    // ==========================================
    // 3. Compliance Document Types (Driver & Vehicle)
    // ==========================================
    public function getComplianceDocTypes(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $types = HrisComplianceDocType::where('tenant_id', $tenantId)->orderBy('target_type')->orderBy('id')->get();

        if ($types->isEmpty()) {
            $defaults = [
                // Driver
                ['target_type' => 'employee', 'name' => 'SIM B1 Umum', 'code' => 'SIM-B1', 'default_reminder_days' => 30, 'description' => 'Surat Izin Mengemudi B1 Umum untuk pengemudi angkutan.'],
                ['target_type' => 'employee', 'name' => 'SIM B2 Umum', 'code' => 'SIM-B2', 'default_reminder_days' => 30, 'description' => 'Surat Izin Mengemudi B2 Umum untuk truk gandeng/tronton.'],
                ['target_type' => 'employee', 'name' => 'SIM A Polos', 'code' => 'SIM-A', 'default_reminder_days' => 30, 'description' => 'Surat Izin Mengemudi A untuk mobil penumpang.'],
                ['target_type' => 'employee', 'name' => 'Sertifikat K3 Lapangan', 'code' => 'K3-CERT', 'default_reminder_days' => 60, 'description' => 'Sertifikasi keselamatan kerja K3 operasional.'],
                ['target_type' => 'employee', 'name' => 'Surat Bebas Narkoba / MCU', 'code' => 'MCU-DOC', 'default_reminder_days' => 30, 'description' => 'Hasil Medical Check-Up tahunan.'],
                // Vehicle
                ['target_type' => 'vehicle', 'name' => 'STNK Tahunan', 'code' => 'STNK-1TH', 'default_reminder_days' => 30, 'description' => 'Pajak Surat Tanda Nomor Kendaraan tahunan.'],
                ['target_type' => 'vehicle', 'name' => 'STNK 5 Tahunan (Ganti Plat)', 'code' => 'STNK-5TH', 'default_reminder_days' => 60, 'description' => 'Perpanjangan STNK dan ganti plat nomor.'],
                ['target_type' => 'vehicle', 'name' => 'Uji Berkala KIR DISHUB', 'code' => 'KIR', 'default_reminder_days' => 20, 'description' => 'Buku uji kelayakan kendaraan bermotor DISHUB 6 bulanan.'],
                ['target_type' => 'vehicle', 'name' => 'Asuransi Kendaraan (All Risk / TLO)', 'code' => 'ASURANSI', 'default_reminder_days' => 30, 'description' => 'Polis asuransi perlindungan armada operasional.'],
                ['target_type' => 'vehicle', 'name' => 'Izin Trayek Operasional', 'code' => 'IZIN-TRAYEK', 'default_reminder_days' => 45, 'description' => 'Surat izin operasi rute angkutan.'],
            ];
            foreach ($defaults as $d) {
                HrisComplianceDocType::create(array_merge($d, ['tenant_id' => $tenantId, 'is_active' => true]));
            }
            $types = HrisComplianceDocType::where('tenant_id', $tenantId)->orderBy('target_type')->orderBy('id')->get();
        }

        return response()->json(['status' => 'success', 'data' => $types]);
    }

    public function storeComplianceDocType(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $request->validate([
            'name' => 'required|string|max:255',
            'target_type' => 'required|in:employee,vehicle',
        ]);

        $type = HrisComplianceDocType::create([
            'tenant_id' => $tenantId,
            'target_type' => $request->target_type,
            'name' => $request->name,
            'code' => $request->code ?: strtoupper(substr($request->name, 0, 8)),
            'default_reminder_days' => $request->default_reminder_days ?: 30,
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json(['status' => 'success', 'data' => $type], 201);
    }

    public function updateComplianceDocType(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $type = HrisComplianceDocType::where('tenant_id', $tenantId)->findOrFail($id);
        $type->update($request->only(['name', 'target_type', 'code', 'default_reminder_days', 'description', 'is_active']));
        return response()->json(['status' => 'success', 'data' => $type]);
    }

    public function deleteComplianceDocType(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        HrisComplianceDocType::where('tenant_id', $tenantId)->where('id', $id)->delete();
        return response()->json(['status' => 'success', 'message' => 'Jenis dokumen kepatuhan berhasil dihapus.']);
    }

    // ==========================================
    // 4. Asset Categories
    // ==========================================
    public function getAssetCategories(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $cats = HrisAssetCategory::where('tenant_id', $tenantId)->orderBy('id', 'asc')->get();

        if ($cats->isEmpty()) {
            $defaults = [
                ['name' => 'Armada Kendaraan Operasional', 'code' => 'VEHICLE', 'description' => 'Mobil, truk, pick-up, dan sepeda motor inventaris.'],
                ['name' => 'Elektronik & Komputer Laptop', 'code' => 'LAPTOP', 'description' => 'Laptop kantor, PC desktop, printer, scanner.'],
                ['name' => 'Gadget & Smartphone Driver', 'code' => 'SMARTPHONE', 'description' => 'HP operasional untuk tracking GPS dan komunikasi.'],
                ['name' => 'Perlengkapan APD & Keselamatan', 'code' => 'APD', 'description' => 'Rompi safety, helm proyek, sepatu safety.'],
                ['name' => 'Peralatan Bengkel & Tools', 'code' => 'TOOLS', 'description' => 'Dongkrak, kunci roda, toolset perbaikan darurat.'],
            ];
            foreach ($defaults as $d) {
                HrisAssetCategory::create(array_merge($d, ['tenant_id' => $tenantId, 'is_active' => true]));
            }
            $cats = HrisAssetCategory::where('tenant_id', $tenantId)->orderBy('id', 'asc')->get();
        }

        return response()->json(['status' => 'success', 'data' => $cats]);
    }

    public function storeAssetCategory(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $request->validate(['name' => 'required|string|max:255']);

        $cat = HrisAssetCategory::create([
            'tenant_id' => $tenantId,
            'name' => $request->name,
            'code' => $request->code ?: strtoupper(substr($request->name, 0, 8)),
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json(['status' => 'success', 'data' => $cat], 201);
    }

    public function updateAssetCategory(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $cat = HrisAssetCategory::where('tenant_id', $tenantId)->findOrFail($id);
        $cat->update($request->only(['name', 'code', 'description', 'is_active']));
        return response()->json(['status' => 'success', 'data' => $cat]);
    }

    public function deleteAssetCategory(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        HrisAssetCategory::where('tenant_id', $tenantId)->where('id', $id)->delete();
        return response()->json(['status' => 'success', 'message' => 'Kategori aset berhasil dihapus.']);
    }
    // ==========================================
    // 5. Document Categories
    // ==========================================
    public function getDocumentCategories(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $cats = \App\Models\HrisDocumentCategory::where('tenant_id', $tenantId)->orderBy('id', 'asc')->get();

        if ($cats->isEmpty()) {
            $defaults = [
                ['name' => 'KTP & Identitas', 'code' => 'KTP', 'description' => 'Kartu Tanda Penduduk atau paspor.'],
                ['name' => 'Kartu Keluarga (KK)', 'code' => 'KK', 'description' => 'Kartu susunan keluarga resmi.'],
                ['name' => 'Ijazah & Transkrip Nilai', 'code' => 'IJAZAH', 'description' => 'Dokumen pendidikan terakhir.'],
                ['name' => 'Sertifikat Keahlian & Lisensi', 'code' => 'SERTIFIKAT', 'description' => 'Sertifikat pelatihan, kompetensi, atau lisensi profesi.'],
                ['name' => 'Kontrak Kerja & Perjanjian', 'code' => 'KONTRAK', 'description' => 'Surat perjanjian kerja fisik bertanda tangan.'],
                ['name' => 'Buku Nikah / Akta Lahir', 'code' => 'AKTA', 'description' => 'Dokumen pendukung keluarga.'],
                ['name' => 'NPWP & Finansial', 'code' => 'NPWP', 'description' => 'Nomor Pokok Wajib Pajak & dokumen perbankan.'],
            ];
            foreach ($defaults as $d) {
                \App\Models\HrisDocumentCategory::create(array_merge($d, ['tenant_id' => $tenantId, 'is_active' => true]));
            }
            $cats = \App\Models\HrisDocumentCategory::where('tenant_id', $tenantId)->orderBy('id', 'asc')->get();
        }

        return response()->json(['status' => 'success', 'data' => $cats]);
    }

    public function storeDocumentCategory(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $request->validate(['name' => 'required|string|max:255']);

        $cat = \App\Models\HrisDocumentCategory::create([
            'tenant_id' => $tenantId,
            'name' => $request->name,
            'code' => $request->code ?: strtoupper(substr($request->name, 0, 8)),
            'description' => $request->description,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json(['status' => 'success', 'data' => $cat], 201);
    }

    public function updateDocumentCategory(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $cat = \App\Models\HrisDocumentCategory::where('tenant_id', $tenantId)->findOrFail($id);
        $cat->update($request->only(['name', 'code', 'description', 'is_active']));
        return response()->json(['status' => 'success', 'data' => $cat]);
    }

    public function deleteDocumentCategory(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        \App\Models\HrisDocumentCategory::where('tenant_id', $tenantId)->where('id', $id)->delete();
        return response()->json(['status' => 'success', 'message' => 'Kategori dokumen berhasil dihapus.']);
    }


    // ==========================================
    // 6. Master Banks (Rekening Penggajian)
    // ==========================================
    public function getBanks(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $banks = \App\Models\HrisBank::where('tenant_id', $tenantId)->orderBy('id', 'asc')->get();

        if ($banks->isEmpty()) {
            $defaults = [
                ['name' => 'Bank Central Asia (BCA)', 'code' => 'BCA'],
                ['name' => 'Bank Mandiri', 'code' => 'MANDIRI'],
                ['name' => 'Bank Rakyat Indonesia (BRI)', 'code' => 'BRI'],
                ['name' => 'Bank Negara Indonesia (BNI)', 'code' => 'BNI'],
                ['name' => 'Bank Syariah Indonesia (BSI)', 'code' => 'BSI'],
                ['name' => 'Bank CIMB Niaga', 'code' => 'CIMB'],
                ['name' => 'Bank Permata', 'code' => 'PERMATA'],
                ['name' => 'Bank Danamon', 'code' => 'DANAMON'],
                ['name' => 'Bank Tabungan Negara (BTN)', 'code' => 'BTN'],
                ['name' => 'Bank BTPN / Jenius', 'code' => 'BTPN'],
            ];
            foreach ($defaults as $d) {
                \App\Models\HrisBank::create(array_merge($d, ['tenant_id' => $tenantId, 'is_active' => true]));
            }
            $banks = \App\Models\HrisBank::where('tenant_id', $tenantId)->orderBy('id', 'asc')->get();
        }

        return response()->json(['status' => 'success', 'data' => $banks]);
    }

    public function storeBank(Request $request)
    {
        $tenantId = $this->getTenantId($request);
        $request->validate(['name' => 'required|string|max:255']);

        $bank = \App\Models\HrisBank::create([
            'tenant_id' => $tenantId,
            'name' => $request->name,
            'code' => $request->code ?: strtoupper(substr($request->name, 0, 8)),
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json(['status' => 'success', 'data' => $bank], 201);
    }

    public function updateBank(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        $bank = \App\Models\HrisBank::where('tenant_id', $tenantId)->findOrFail($id);
        $bank->update($request->only(['name', 'code', 'is_active']));
        return response()->json(['status' => 'success', 'data' => $bank]);
    }

    public function deleteBank(Request $request, $id)
    {
        $tenantId = $this->getTenantId($request);
        \App\Models\HrisBank::where('tenant_id', $tenantId)->where('id', $id)->delete();
        return response()->json(['status' => 'success', 'message' => 'Bank berhasil dihapus.']);
    }

}
