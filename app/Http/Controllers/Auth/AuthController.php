<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login()
    {
        return view('login');
    }

    public function auth(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt([
            'username' => $request->username,
            'password' => $request->password,
        ], $remember)) {

            /** @var User $user */
            $user = Auth::user();

            // Cek apakah akun pengguna berstatus aktif
            if (! $user->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'username' => 'Akun Anda telah dinonaktifkan. Silakan hubungi Administrator.',
                ])->onlyInput('username');
            }

            $request->session()->regenerate();

            $user->update([
                'last_login' => now(),
            ]);

            return match ($user->role) {
                'admin' => redirect()->route('admin.dashboard'),
                'timdis' => redirect()->route('timdis.dashboard'),
                'mahasiswa' => redirect()->route('mahasiswa.dashboard'),
                default => redirect('/login'),
            };
        }

        return back()->withErrors([
            'username' => 'Username atau password yang Anda masukkan salah.',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        // Hapus session dan regenerasi token CSRF untuk keamanan
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Mengambil data profil user yang sedang login
     */
    public function me()
    {
        // Ambil user yang sedang login
        /** @var User $user */
        $user = Auth::user();

        // Kondisi jika user belum login atau sesi telah kadaluwarsa
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi tidak valid atau belum login.',
            ], 401);
        }

        // Kembalikan data user dalam format JSON
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'mahasiswa' => $user->mahasiswa,
                'permissions' => [
                    'can_manage_users' => $user->role === 'admin',
                    'can_edit_settings' => $user->role === 'admin',
                    'can_manage_mahasiswa' => $user->role === 'admin',
                    'can_verify_submissions' => in_array($user->role, ['timdis', 'garda']),
                ],
            ],
        ]);
    }
}
