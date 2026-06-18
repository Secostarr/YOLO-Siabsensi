<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CekRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Pastikan user sudah login dan rolenya ada dalam daftar parameter di route
        if ($request->user() && in_array($request->user()->role, $roles)) {
            return $next($request);
        }

        // JIKA ROLE TIDAK SESUAI:
        // Cek apakah request mengharapkan JSON response (API call)
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak: Anda tidak memiliki izin untuk mengakses resource ini.',
            ], 403);
        }

        // Jika request biasa (web page), redirect ke dashboard sesuai role
        $user = $request->user();
        if ($user) {
            return match ($user->role) {
                'admin' => redirect()->route('admin.dashboard')->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk halaman tersebut.'),
                'timdis' => redirect()->route('timdis.dashboard')->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk halaman tersebut.'),
                'mahasiswa' => redirect()->route('mahasiswa.dashboard')->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk halaman tersebut.'),
                'garda' => redirect()->route('garda.dashboard')->with('error', 'Akses ditolak: Anda tidak memiliki izin untuk halaman tersebut.'),
                default => redirect()->route('login')->with('error', 'Role tidak dikenali.'),
            };
        }

        // Jika tidak ada user, redirect ke login
        return redirect()->route('login')->with('error', 'Silakan login terlebih dahulu.');
    }
}
