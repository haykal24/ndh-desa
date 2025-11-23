<?php

namespace Database\Seeders;

use App\Models\BatasWilayahPotensi;
use App\Models\ProfilDesa;
use App\Models\StrukturPemerintahan;
use App\Models\User;
use Illuminate\Database\Seeder;

class BasicDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * This seeder creates basic data needed for the application to run
     * without requiring Faker (dev dependency).
     */
    public function run(): void
    {
        // Get admin user
        $admin = User::where('email', 'admin@desaku.com')->first();

        if (!$admin) {
            $admin = User::role('admin')->first() ?? User::role('super_admin')->first();
        }

        if (!$admin) {
            $this->command->warn('No admin user found. Please run RoleAndPermissionSeeder first.');
            return;
        }

        // Create ProfilDesa if it doesn't exist
        $profilDesa = ProfilDesa::first();
        
        if (!$profilDesa) {
            $this->command->info('Creating basic ProfilDesa data...');
            
            $profilDesa = ProfilDesa::create([
                'created_by' => $admin->id,
                'nama_desa' => 'Desa Kedungwungu',
                'kecamatan' => 'Kecamatan Krangkeng',
                'kabupaten' => 'Kabupaten Indramayu',
                'provinsi' => 'Jawa Barat',
                'kode_pos' => '45258',
                'logo' => 'uploads/desa/kedungwungu-logo.png',
                'alamat' => 'Jl. Raya Kedungwungu No. 123, Kecamatan Krangkeng, Kabupaten Indramayu',
                'telepon' => '0234-5678901',
                'email' => 'desa-kedungwungu@indramayu.desa.id',
                'website' => 'https://kedungwungu.desa.id',
                'visi' => 'Mewujudkan Desa Kedungwungu yang Maju, Mandiri, dan Sejahtera dengan Pembangunan Berkelanjutan dan Berbasis Kearifan Lokal',
                'misi' => "1. Meningkatkan pembangunan infrastruktur yang mendukung perekonomian desa\n2. Mengoptimalkan pelayanan publik yang transparan dan akuntabel\n3. Meningkatkan produktivitas pertanian dan perikanan\n4. Mengembangkan potensi pariwisata dan UMKM\n5. Melestarikan budaya dan kearifan lokal",
                'sejarah' => "Desa Kedungwungu berdiri sejak tahun 1825, berawal dari pemukiman nelayan dan petani di pesisir utara Jawa. Nama desa berasal dari sebuah Kedung (cekungan air) yang airnya berwarna wungu (ungu) akibat tanah liat yang khas di daerah ini.\n\nPada masa kolonial, desa ini merupakan penghasil garam dan ikan yang penting. Setelah kemerdekaan, Desa Kedungwungu berkembang menjadi salah satu desa produktif di Indramayu dengan pertanian dan tambak sebagai sektor andalan.",
            ]);

            // Create BatasWilayahPotensi
            BatasWilayahPotensi::create([
                'profil_desa_id' => $profilDesa->id,
                'created_by' => $admin->id,
                'luas_wilayah' => 4567800, // dalam meter persegi (456.78 hektar)
                'batas_utara' => 'Desa Tegalwirangrong',
                'batas_timur' => 'Desa Kalianyar',
                'batas_selatan' => 'Desa Karangampel',
                'batas_barat' => 'Desa Krangkeng',
                'keterangan_batas' => 'Batas-batas wilayah sesuai dengan peta desa tahun 2020',
                'potensi_desa' => [
                    [
                        'nama' => 'Sumber mata air',
                        'kategori' => 'sda',
                        'lokasi' => 'Dusun Kramat',
                        'deskripsi' => 'Sumber mata air yang digunakan untuk kebutuhan air bersih desa'
                    ],
                    [
                        'nama' => 'Lahan pertanian subur',
                        'kategori' => 'sda',
                        'lokasi' => 'Seluruh wilayah desa',
                        'deskripsi' => 'Lahan dengan tingkat kesuburan tinggi untuk pertanian'
                    ],
                ],
                'keterangan_potensi' => 'Potensi desa berdasarkan pemetaan tahun 2023',
            ]);

            $this->command->info('ProfilDesa created successfully.');
        } else {
            $this->command->info('ProfilDesa already exists, skipping...');
        }

        // Create StrukturPemerintahan if it doesn't exist
        $strukturPemerintahan = StrukturPemerintahan::first();
        
        if (!$strukturPemerintahan && $profilDesa) {
            $this->command->info('Creating basic StrukturPemerintahan data...');
            
            StrukturPemerintahan::create([
                'profil_desa_id' => $profilDesa->id,
                'created_by' => $admin->id,
                'nama_kepala_desa' => 'H. Sumitro Hadi Prayitno',
                'periode_jabatan' => '2020-2026',
                'foto_kepala_desa' => 'uploads/desa/kepala-desa/default-kades.jpg',
                'sambutan_kepala_desa' => "<p>Assalamu'alaikum Wr. Wb.</p>
                    <p>Puji syukur kita panjatkan kehadirat Allah SWT, karena atas berkat dan rahmat-Nya kita masih diberikan kesehatan dan kesempatan untuk menjalankan tugas sebagai pelayan masyarakat di Desa Kedungwungu.</p>
                    <p>Sebagai Kepala Desa, saya bersama dengan perangkat desa dan BPD berkomitmen untuk memajukan desa kita dengan program-program yang inovatif dan tepat sasaran.</p>",
                'program_kerja' => "<p>Sebagai Kepala Desa Kedungwungu, saya berkomitmen untuk memajukan desa kita melalui program-program strategis dalam berbagai bidang.</p>",
                'prioritas_program' => "<ol>
                    <li><strong>Pembangunan Infrastruktur Pertanian</strong></li>
                    <li><strong>Pengembangan BUMDES</strong></li>
                    <li><strong>Pelatihan Digital untuk Pemuda</strong></li>
                </ol>",
                'bagan_struktur' => 'uploads/desa/struktur/default-struktur.jpg',
            ]);

            $this->command->info('StrukturPemerintahan created successfully.');
        } else {
            $this->command->info('StrukturPemerintahan already exists, skipping...');
        }

        $this->command->info('Basic data seeding completed!');
    }
}

