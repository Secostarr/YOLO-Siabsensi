<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('users')->insert([
            // 1. Akun Admin
            [
                'username' => 'admin',
                'password_hash' => Hash::make('admin123'), // Password yang akan diinput di form
                'full_name' => 'Administrator Sistem',
                'email' => 'admin@siabsen.test',
                'role' => 'admin',
                'mahasiswa_id' => null,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            // 2. Akun Timdis (Tim Kedisiplinan)
            [
                'username' => 'timdis',
                'password_hash' => Hash::make('timdis123'),
                'full_name' => 'Tim Kedisiplinan',
                'email' => 'timdis@siabsen.test',
                'role' => 'timdis',
                'mahasiswa_id' => null,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            // 3. Akun Garda (Verifikasi sakit & absen manual)
            [
                'username' => 'garda',
                'password_hash' => Hash::make('garda123'),
                'full_name' => 'Petugas Garda',
                'email' => 'garda@siabsen.test',
                'role' => 'garda',
                'mahasiswa_id' => null,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            // 4. Akun Mahasiswa
            [
                'username' => 'mhs001',
                'password_hash' => Hash::make('mhs123'),
                'full_name' => 'Budi Mahasiswa',
                'email' => 'budi@mhs.test',
                'role' => 'mahasiswa',
                'mahasiswa_id' => 'MHS-001', // Sesuaikan jika nanti ada tabel mahasiswa
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
