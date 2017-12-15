<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\Hash;

class PTMajuSejahteraSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Dapatkan user admin@majusejahtera.com
        $admin = User::where('email', 'admin@majusejahtera.com')->first();
        if (!$admin) {
            $this->command->error("Admin admin@majusejahtera.com tidak ditemukan! Silakan jalankan seeder utama terlebih dahulu.");
            return;
        }

        // 2. Cari Plan Pro
        $proPlan = Plan::where('slug', 'pro')->first();
        if (!$proPlan) {
            $proPlan = Plan::create([
                'name' => 'Pro Tracking',
                'slug' => 'pro',
                'description' => 'Solusi lengkap untuk tim lapangan dan armada kendaraan',
                'price_monthly' => 200000,
                'max_employees' => 50,
                'max_vehicles' => 30,
                'ai_credits' => 500,
                'features' => [
                    'Real-time GPS Tracking',
                    'Riwayat Perjalanan 30 Hari',
                    'Advanced HRIS (Cuti & Izin)'
                ],
                'sort_order' => 2
            ]);
        }

        // 3. Upgrade Subscription tenant ke PRO agar batas limitnya 50 karyawan & 30 kendaraan
        Subscription::where('user_id', $admin->id)->delete();
        Subscription::create([
            'user_id' => $admin->id,
            'plan_id' => $proPlan->id,
            'max_employees' => 50,
            'max_vehicles' => 30,
            'ai_credits_limit' => 500,
            'ai_credits_used' => 0,
            'company_name' => 'PT Maju Sejahtera',
            'status' => 'active',
            'started_at' => now(),
            'expired_at' => now()->addYear(),
        ]);

        // 4. Bersihkan data karyawan dan kendaraan lama di tenant ini agar jumlahnya pas
        $oldEmployees = Employee::where('admin_id', $admin->id)->get();
        foreach ($oldEmployees as $emp) {
            $emp->user()->delete(); // Hapus akun user-nya
            $emp->delete();         // Hapus profil employee
        }
        Vehicle::where('admin_id', $admin->id)->delete();

        // 5. Generate 50 Karyawan Realistis (Yogyakarta & Jawa Tengah)
        $firstNames = [
            'Agus', 'Budi', 'Candra', 'Dedi', 'Eko', 'Fajar', 'Guntur', 'Hadi', 'Iwan', 'Joko', 
            'Kurniawan', 'Lukman', 'Mulyono', 'Nugroho', 'Oki', 'Prabowo', 'Rian', 'Slamet', 'Tono', 'Wahyu',
            'Yudi', 'Zainal', 'Andi', 'Bambang', 'Dharma', 'Edi', 'Hendra', 'Indra', 'Rahmat', 'Surya',
            'Adi', 'Aris', 'Bayu', 'Dwi', 'Ferry', 'Gede', 'Heru', 'Irfan', 'Jefri', 'Krisna',
            'Siti', 'Dewi', 'Indah', 'Lestari', 'Sri', 'Putri', 'Rini', 'Wahyuni', 'Kartini', 'Mega'
        ];
        
        $lastNames = [
            'Saputra', 'Wijaya', 'Kusuma', 'Prasetyo', 'Raharjo', 'Santoso', 'Hidayat', 'Wibowo', 'Nugraha', 'Setiawan',
            'Susanto', 'Hartono', 'Gunawan', 'Budiman', 'Subagyo', 'Nugroho', 'Sanjaya', 'Putra', 'Utomo', 'Siregar',
            'Arifin', 'Basuki', 'Mahendra', 'Firmansyah', 'Yulianto', 'Kurnia', 'Purnama', 'Wicaksono', 'Darsono', 'Suwarno',
            'Kurniawan', 'Pradana', 'Sudrajat', 'Haryanto', 'Nasution', 'Laksana', 'Pamungkas', 'Ryanto', 'Sapto', 'Wahyudi',
            'Lestari', 'Wulandari', 'Utami', 'Rahayu', 'Kartika', 'Sulistyo', 'Fitriani', 'Handayani', 'Puspita', 'Sari'
        ];

        // Daftar wilayah operasional beserta titik koordinat dan alamat contoh
        $regions = [
            ['city' => 'Yogyakarta', 'lat' => -7.797068, 'lon' => 110.370529, 'addresses' => [
                'Jl. Malioboro No. 12, Sosromenduran, Gedong Tengen, Kota Yogyakarta',
                'Jl. Ipda Tut Harsono No. 25, Muja Muju, Umbulharjo, Kota Yogyakarta',
                'Jl. Parangtritis Km 3.5, Mantrijeron, Kota Yogyakarta',
                'Jl. AM. Sangaji No. 45, Cokrodiningratan, Jetis, Kota Yogyakarta',
                'Jl. Kusumanegara No. 112, Umbulharjo, Kota Yogyakarta'
            ]],
            ['city' => 'Sleman', 'lat' => -7.7214, 'lon' => 110.3639, 'addresses' => [
                'Jl. Kaliurang Km 8.5, Sinduharjo, Ngaglik, Sleman',
                'Jl. Magelang Km 12, Triharjo, Sleman',
                'Jl. Ring Road Utara No. 18, Condongcatur, Depok, Sleman',
                'Gg. Pandega Marta No. 5, Caturtunggal, Depok, Sleman',
                'Jl. Godean Km 5, Demakijo, Gamping, Sleman'
            ]],
            ['city' => 'Bantul', 'lat' => -7.8872, 'lon' => 110.3274, 'addresses' => [
                'Jl. Jenderal Sudirman No. 45, Bantul Warung, Bantul',
                'Jl. Bantul Km 7, Pendowoharjo, Sewon, Bantul',
                'Jl. Imogiri Timur Km 10, Jejeran, Wonokromo, Pleret, Bantul',
                'Jl. Ringroad Selatan, Kasihan, Bantul',
                'Perum Kasongan Permai Blok C, Bangunjiwo, Kasihan, Bantul'
            ]],
            ['city' => 'Klaten', 'lat' => -7.7027, 'lon' => 110.6033, 'addresses' => [
                'Jl. Pemuda No. 88, Tonggalan, Klaten Tengah, Klaten',
                'Jl. Raya Solo-Jogja Km 22, Karangwuni, Ceper, Klaten',
                'Perumahan Gergunung Indah Blok B5, Klaten Utara, Klaten',
                'Jl. Jatinom No. 14, Baret, Klaten'
            ]],
            ['city' => 'Solo', 'lat' => -7.5666, 'lon' => 110.8243, 'addresses' => [
                'Jl. Slamet Riyadi No. 250, Timuran, Banjarsari, Surakarta',
                'Jl. Adi Sucipto No. 12, Manahan, Banjarsari, Surakarta',
                'Jl. Kolonel Sutarto No. 150, Jebres, Surakarta',
                'Jl. Veteran No. 99, Pasar Kliwon, Surakarta'
            ]],
            ['city' => 'Magelang', 'lat' => -7.4706, 'lon' => 110.2181, 'addresses' => [
                'Jl. Jenderal Sudirman No. 34, Magelang Tengah, Kota Magelang',
                'Jl. Tidar No. 8, Kemirirejo, Magelang Tengah, Kota Magelang',
                'Jl. Raya Borobudur Km 3, Mungkid, Kabupaten Magelang',
                'Jl. Pemuda No. 110, Muntilan, Kabupaten Magelang'
            ]],
            ['city' => 'Semarang', 'lat' => -6.9932, 'lon' => 110.4203, 'addresses' => [
                'Jl. Pandanaran No. 45, Mugassari, Semarang Selatan, Kota Semarang',
                'Jl. Pemuda No. 142, Sekayu, Semarang Tengah, Kota Semarang',
                'Jl. Setiabudi No. 88, Srondol Kulon, Banyumanik, Kota Semarang',
                'Jl. Majapahit No. 210, Palebon, Pedurungan, Kota Semarang'
            ]],
            ['city' => 'Purworejo', 'lat' => -7.7126, 'lon' => 110.0076, 'addresses' => [
                'Jl. Kutoarjo No. 12, Grabag, Purworejo',
                'Jl. Jenderal Urip Sumoharjo No. 45, Purworejo',
                'Jl. Jenderal Sudirman No. 88, Kutoarjo, Purworejo'
            ]]
        ];

        // Definisi departemen dan posisi di PT Maju Sejahtera (FMCG Distributor)
        $positions = [
            ['dept' => 'Sales', 'pos' => 'Field Sales Representative', 'field' => true],
            ['dept' => 'Sales', 'pos' => 'Sales Kanvaser', 'field' => true],
            ['dept' => 'Logistics', 'pos' => 'Courier Delivery', 'field' => true],
            ['dept' => 'Logistics', 'pos' => 'Logistics Driver', 'field' => true],
            ['dept' => 'Operations', 'pos' => 'Field Area Surveyor', 'field' => true],
            ['dept' => 'Operations', 'pos' => 'Merchandiser (SPO)', 'field' => true],
            
            // Staff Office (tetap dilacak koordinatnya di kantor)
            ['dept' => 'HRD', 'pos' => 'HR Staff', 'field' => false],
            ['dept' => 'Finance', 'pos' => 'Finance Admin', 'field' => false],
            ['dept' => 'IT', 'pos' => 'IT Support', 'field' => false],
            ['dept' => 'Operations', 'pos' => 'Warehouse Supervisor', 'field' => false],
        ];

        for ($i = 1; $i <= 50; $i++) {
            // Pilih nama secara acak
            $firstName = $firstNames[($i - 1) % count($firstNames)];
            $lastName = $lastNames[rand(0, count($lastNames) - 1)];
            
            if ($firstName === $lastName) {
                $lastName = $lastNames[($i + 3) % count($lastNames)];
            }
            $fullName = $firstName . ' ' . $lastName;
            
            // Format email: nama.kecil@majusejahtera.com
            $emailUser = strtolower($firstName) . str_pad($i, 2, '0', STR_PAD_LEFT);
            $email = $emailUser . '@majusejahtera.com';
            
            // Tentukan posisi. Sebagian besar (75%) adalah staf lapangan agar visual peta ramai
            if ($i <= 38) {
                $posConfig = $positions[rand(0, 5)]; // Pasti lapangan
            } else {
                $posConfig = $positions[rand(6, 9)]; // Staf kantor
            }
            
            // Pilih wilayah dan alamat acak
            $region = $regions[rand(0, count($regions) - 1)];
            $address = $region['addresses'][rand(0, count($region['addresses']) - 1)];
            
            // Buat titik acak di sekitar wilayah kota tersebut (+- 1.5 KM)
            $latitude = $region['lat'] + (rand(-150, 150) / 10000);
            $longitude = $region['lon'] + (rand(-150, 150) / 10000);
            
            // Buat Akun Karyawan
            $user = User::create([
                'name' => $fullName,
                'email' => $email,
                'password' => Hash::make('password123'),
                'role' => 'employee',
                'admin_id' => $admin->id,
                'is_active' => true,
            ]);

            // Buat Profil Karyawan
            Employee::create([
                'user_id' => $user->id,
                'admin_id' => $admin->id,
                'employee_id' => 'EMP' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'department' => $posConfig['dept'],
                'phone' => '08' . rand(12, 19) . rand(1111111, 9999999),
                'position' => $posConfig['pos'],
                'address' => $address,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'last_location_update' => now()->subMinutes(rand(5, 180)),
            ]);
        }

        // 6. Generate 30 Kendaraan Distribusi & Operasional
        $vehicleTypes = [
            ['type' => 'Motorcycle', 'brand' => 'Honda', 'model' => 'Supra X 125'],
            ['type' => 'Motorcycle', 'brand' => 'Honda', 'model' => 'Revo Fit'],
            ['type' => 'Motorcycle', 'brand' => 'Yamaha', 'model' => 'Gear 125'],
            ['type' => 'Van', 'brand' => 'Daihatsu', 'model' => 'Gran Max Blind Van'],
            ['type' => 'Van', 'brand' => 'Suzuki', 'model' => 'Carry Van'],
            ['type' => 'Pick-up', 'brand' => 'Daihatsu', 'model' => 'Gran Max Pick-up'],
            ['type' => 'Pick-up', 'brand' => 'Mitsubishi', 'model' => 'L300 Pick-up'],
            ['type' => 'Truck', 'brand' => 'Isuzu', 'model' => 'Elf CDE 4 Roda'],
            ['type' => 'Truck', 'brand' => 'Mitsubishi', 'model' => 'Colt Diesel FE 71'],
            ['type' => 'Car', 'brand' => 'Toyota', 'model' => 'Avanza (Operational)']
        ];

        // Kode plat nomor Jawa Tengah dan DIY
        $platePrefixes = ['AB', 'AD', 'H', 'AA'];
        $plateLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];

        for ($j = 1; $j <= 30; $j++) {
            // Pilih tipe kendaraan
            $vt = $vehicleTypes[($j - 1) % count($vehicleTypes)];
            
            // Format plat nomor acak Jateng/DIY
            $prefix = $platePrefixes[rand(0, count($platePrefixes) - 1)];
            $number = rand(1000, 9999);
            $suffix = $plateLetters[rand(0, count($plateLetters) - 1)] . $plateLetters[rand(0, count($plateLetters) - 1)];
            $plate = $prefix . ' ' . $number . ' ' . $suffix;
            
            // Pilih wilayah acak untuk koordinat awal kendaraan
            $region = $regions[rand(0, count($regions) - 1)];
            $latitude = $region['lat'] + (rand(-120, 120) / 10000);
            $longitude = $region['lon'] + (rand(-120, 120) / 10000);
            
            $vehicle = Vehicle::create([
                'admin_id' => $admin->id,
                'vehicle_number' => $plate,
                'vehicle_type' => $vt['type'],
                'brand' => $vt['brand'],
                'model' => $vt['model'],
                'year' => rand(2019, 2025),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'last_location_update' => now()->subMinutes(rand(5, 180)),
                'is_active' => true,
            ]);
            
            // Generate token tracking untuk GPS simulator
            $vehicle->generateTrackingToken();
        }
    }
}
