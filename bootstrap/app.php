<?php

use App\Http\Middleware\CekRole;
use App\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions; // <-- WAJIB: Tambahkan ini untuk membaca request
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // Sangat penting untuk hosting: Percayai SSL/Proxy agar sesi tidak terputus
        $middleware->trustProxies(at: '*');

        // 1. Redirect untuk Guest (Belum Login) jika mencoba akses halaman yang diproteksi
        $middleware->redirectGuestsTo('/login');

        // 2. Redirect untuk User (Sudah Login) jika mencoba akses halaman login/register
        // Di sini kita cek rolenya
        $middleware->redirectUsersTo(function (Request $request) {
            // Asumsi kamu punya kolom 'role' di tabel users.
            // Sesuaikan '$request->user()->role' dengan nama kolom database kamu.
            /** @var User $user */
            $user = $request->user();
            $role = $user->role;

            if ($role === 'admin') {
                return route('admin.dashboard'); // Sesuaikan dengan rute dashboard admin
            } elseif ($role === 'timdis') {
                return route('timdis.dashboard'); // Akses disesuaikan dengan rute dashboard timdis
            } elseif ($role === 'mahasiswa') {
                return route('mahasiswa.dashboard'); // Sesuaikan dengan rute dashboard mahasiswa
            } elseif ($role === 'garda') {
                return route('garda.dashboard'); // Dashboard khusus garda (verifikasi)
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // 2. Buat pesan error sementara (flash session)
            $request->session()->flash('error', 'Akses ditolak: Role tidak dikenali.');

            // 3. Arahkan kembali ke URL login
            return '/login';
        });

        // 3. Alias Middleware kamu yang sudah ada
        $middleware->alias([
            'role' => CekRole::class,
        ]);

        // 4. Pengecualian CSRF kamu yang sudah ada
        $middleware->validateCsrfTokens(except: [
            'api/hardware-absensi',
            'api/sensor/*',
            'api/mahasiswa/*/sertifikat/*',
            'api/sertifikat/*',
            'api/python/detect',
            'api/python/attendance',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
