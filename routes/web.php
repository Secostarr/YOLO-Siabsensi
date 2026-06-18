<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Mahasiswa\IzinController;
use App\Http\Controllers\Mahasiswa\KehadiranController;
use App\Http\Controllers\Mahasiswa\MahasiswaController;
use App\Http\Controllers\SertifikatController;
use App\Models\Attendance;
use App\Models\Mahasiswa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

// Helper function to format bytes
function formatBytes($bytes, $precision = 2)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);

    return round($bytes, $precision).' '.$units[$pow];
}

// Menangani path kosong (/) dengan kondisi pengecekan login
Route::get('/', function () {
    // Jika pengguna sudah login, arahkan ke dashboard masing-masing
    if (Auth::check()) {
        $role = Auth::user()->role;

        return match ($role) {
            'admin' => redirect()->route('admin.dashboard'),
            'timdis' => redirect()->route('timdis.dashboard'),
            'mahasiswa' => redirect()->route('mahasiswa.dashboard'),
            'garda' => redirect()->route('garda.dashboard'),
            default => redirect()->route('login'),
        };
    }

    // Kondisi jika pengguna BELUM login
    return redirect()->route('login');
});

Route::middleware(['guest'])->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/auth/login', [AuthController::class, 'auth'])->name('auth');
});

// Monitor Absensi (Public - untuk display di layar besar)
Route::get('/monitor', function () {
    return view('monitor');
});

// API untuk Monitor (Public - tanpa auth) - menggunakan endpoint khusus monitor
Route::get('/api/monitor/cameras', [AdminController::class, 'getCameras']);
Route::get('/api/monitor/attendance/today', [AdminController::class, 'getAttendanceToday']);

// Endpoint untuk Delta Updates Absensi Real-time (Polling)
Route::get('/api/monitor/attendance/stream', function (Request $request) {
    $lastUpdate = $request->query('last_update');

    $query = Attendance::with('mahasiswa:id,name,kompi')
        ->whereDate('date', Carbon::today()->toDateString());

    if ($lastUpdate) {
        $query->where(function ($q) use ($lastUpdate) {
            $q->where('check_in', '>', $lastUpdate)
                ->orWhere('check_out', '>', $lastUpdate)
                ->orWhere('created_at', '>', $lastUpdate);
        });
    } else {
        $query->orderBy('check_in', 'desc')->take(50);
    }

    $attendances = $query->get();

    $maxTimestamp = $attendances->max(function ($att) {
        return max($att->check_in, $att->check_out, $att->created_at);
    });

    return response()->json([
        'success' => true,
        'data' => $attendances->map(function ($att) {
            return [
                'id' => $att->id,
                'mahasiswa_id' => $att->mahasiswa_id,
                'name' => $att->mahasiswa->name ?? 'Unknown',
                'kompi' => $att->mahasiswa->kompi ?? '-',
                'check_in' => $att->check_in,
                'check_out' => $att->check_out,
                'status' => $att->status ?? 'present',
            ];
        }),
        'last_update' => $maxTimestamp ?: $lastUpdate,
    ]);
});

// API untuk Python Backend (Proxy ke port 5000)
Route::get('/api/python/status', function () {
    try {
        $response = Http::get('http://127.0.0.1:5000/api/python/status');

        return response()->json($response->json(), $response->status());
    } catch (Exception $e) {
        return response()->json(['success' => false, 'message' => 'Python backend tidak tersedia'], 503);
    }
});

Route::post('/api/python/detect', function (Request $request) {
    try {
        // Forward raw body (mencegah memory limit pada base64 payload besar)
        $response = Http::withBody($request->getContent(), 'application/json')->post('http://127.0.0.1:5000/api/python/detect');

        return response()->json($response->json(), $response->status());
    } catch (Exception $e) {
        return response()->json(['success' => false, 'message' => 'Python backend tidak tersedia: '.$e->getMessage()], 503);
    }
});

Route::post('/api/python/attendance', function (Request $request) {
    try {
        $mahasiswaId = $request->input('mahasiswa_id');
        $status = $request->input('status', 'present');

        if (! $mahasiswaId) {
            return response()->json(['success' => false, 'message' => 'Data mahasiswa_id tidak boleh kosong.'], 400);
        }

        // Cek apakah mahasiswa valid untuk menghindari Database Constraint Error (500)
        $mahasiswa = Mahasiswa::find($mahasiswaId);
        if (! $mahasiswa) {
            return response()->json(['success' => false, 'message' => 'Mahasiswa tidak ditemukan di database.'], 404);
        }

        // Record attendance directly in Laravel
        $attendance = Attendance::where('mahasiswa_id', $mahasiswaId)
            ->where('date', Carbon::today()->format('Y-m-d'))
            ->first();

        if ($attendance) {
            // Update existing attendance
            $attendance->update([
                'status' => $status,
                'check_in' => $attendance->check_in ?? Carbon::now()->toDateTimeString(),
                'check_out' => $status === 'present' ? Carbon::now()->toDateTimeString() : null,
            ]);
        } else {
            // Create new attendance
            Attendance::create([
                'mahasiswa_id' => $mahasiswaId,
                'date' => Carbon::today()->format('Y-m-d'),
                'status' => $status,
                'check_in' => Carbon::now()->toDateTimeString(),
                'check_out' => null,
                'created_at' => Carbon::now()->toDateTimeString(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Attendance recorded successfully',
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to record attendance: '.$e->getMessage(),
        ], 500);
    }
});

// API untuk Models (Public - untuk browse models folder)
Route::get('/api/models/list', function () {
    $modelsDir = base_path('models');
    $models = [];

    if (is_dir($modelsDir)) {
        $files = scandir($modelsDir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'pt') {
                $filePath = $modelsDir.'/'.$file;
                $models[] = [
                    'name' => $file,
                    'path' => 'models/'.$file,
                    'size' => formatBytes(filesize($filePath)),
                ];
            }
        }
    }

    return response()->json([
        'success' => true,
        'data' => $models,
    ]);
});

Route::middleware(['auth'])->group(function () {
    Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

    // Rute API untuk mengambil data user yang sedang login
    Route::get('/api/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
});

// Routes untuk Admin Only (bukan timdis)
Route::middleware(['auth', 'role:admin'])->group(function () {
    // Settings RTSP (Admin Only)
    Route::post('/api/settings/rtsp', [AdminController::class, 'saveRtspSettings']);
    // Settings YOLO (Admin Only)
    Route::post('/api/settings/yolo', [AdminController::class, 'saveYoloSettings']);
    Route::get('/api/settings/yolo', [AdminController::class, 'getYoloSettings']);
});

// Routes untuk Mahasiswa (hanya role mahasiswa)
Route::middleware(['auth', 'role:mahasiswa'])->group(function () {
    Route::get('/mahasiswa/dashboard', [MahasiswaController::class, 'dashboard'])->name('mahasiswa.dashboard');

    // Rute API Data Mahasiswa
    Route::get('/api/mahasiswa/{id}/statistics', [MahasiswaController::class, 'getStatistics']);
    Route::get('/api/mahasiswa/{id}/chart/weekly', [MahasiswaController::class, 'getWeeklyChart']);
    Route::get('/api/mahasiswa/{id}/chart/monthly', [MahasiswaController::class, 'getMonthlyChart']);
    Route::get('/api/mahasiswa/{id}/activity', [MahasiswaController::class, 'getRecentActivity']);
    Route::get('/api/mahasiswa/{id}/today-attendance', [MahasiswaController::class, 'getTodayAttendanceStatus']);
    Route::get('/api/mahasiswa/{id}/qr-code', [MahasiswaController::class, 'getQRCode']);

    // Rute untuk data mahasiswa
    Route::get('/api/mahasiswa/{id}', [MahasiswaController::class, 'getProfile']);
    Route::put('/api/mahasiswa/{id}', [MahasiswaController::class, 'updateProfile']);
    Route::get('/api/mahasiswa/{id}/riwayat', [MahasiswaController::class, 'getRiwayat']);
    Route::get('/api/mahasiswa/{id}/riwayat/export', [MahasiswaController::class, 'exportRiwayat']);

    // Rute untuk pengajuan izin mahasiswa
    Route::post('/api/izin/submit', [IzinController::class, 'submit']);
    Route::get('/api/izin/mahasiswa/{id}', [IzinController::class, 'history']);
    Route::get('/api/izin/bukti/{filename}', [IzinController::class, 'getBukti']);

    // Rute untuk kehadiran mahasiswa
    Route::post('/api/kehadiran/submit', [KehadiranController::class, 'submit']);
    Route::get('/api/kehadiran/mahasiswa/{id}', [KehadiranController::class, 'history']);
    Route::get('/api/kehadiran/bukti/{filename}', [KehadiranController::class, 'getBukti']);

    // Sertifikat (Mahasiswa)
    Route::post('/api/mahasiswa/{mahasiswaId}/sertifikat/preview', [SertifikatController::class, 'preview']);
    Route::post('/api/mahasiswa/{mahasiswaId}/sertifikat/preview-image', [SertifikatController::class, 'previewImage']);
    Route::post('/api/mahasiswa/{mahasiswaId}/sertifikat/generate', [SertifikatController::class, 'generate']);
    Route::post('/api/mahasiswa/{mahasiswaId}/sertifikat/preview-pdf', [SertifikatController::class, 'previewPdf']);
    Route::get('/api/mahasiswa/{mahasiswaId}/sertifikat/history', [SertifikatController::class, 'history']);
});

// Routes untuk Admin, Timdis & Garda (Lihat daftar pengajuan)
Route::middleware(['auth', 'role:admin,timdis,garda'])->group(function () {
    Route::get('/api/izin/list', [AdminController::class, 'getIzinSubmissions']);
    Route::get('/api/kehadiran/list', [AdminController::class, 'getKehadiranSubmissions']);
});

// Routes untuk Timdis & Garda (Verifikasi aksi)
Route::middleware(['auth', 'role:timdis,garda'])->group(function () {
    Route::post('/api/izin/verify', [AdminController::class, 'verifyIzin']);
    Route::post('/api/kehadiran/verify', [AdminController::class, 'verifyKehadiran']);
});

// Routes untuk Garda (Dashboard verifikasi saja)
Route::middleware(['auth', 'role:garda'])->group(function () {
    Route::get('/garda/dashboard', [AdminController::class, 'dashboard_admin'])->name('garda.dashboard');
});

// Routes untuk Admin Only (bukan timdis)
Route::middleware(['auth', 'role:admin'])->group(function () {
    // Settings RTSP (Admin Only)
    Route::post('/api/settings/rtsp', [AdminController::class, 'saveRtspSettings']);
    // Settings YOLO (Admin Only)
    Route::post('/api/settings/yolo', [AdminController::class, 'saveYoloSettings']);
    Route::get('/api/settings/yolo', [AdminController::class, 'getYoloSettings']);

    // CRUD Mahasiswa (Admin)
    Route::post('/api/mahasiswa', [AdminController::class, 'storeMahasiswa']);
    Route::delete('/api/mahasiswa/{id}', [AdminController::class, 'deleteMahasiswa']);
    Route::get('/api/mahasiswa/{id}/qr', [AdminController::class, 'getMahasiswaQR']);
    Route::post('/api/mahasiswa/bulk-update-kompi', [AdminController::class, 'bulkUpdateKompi']);

    // CRUD Users Management (Admin)
    Route::get('/api/users', [AdminController::class, 'getAllUsers']);
    Route::post('/api/users', [AdminController::class, 'storeUser']);
    Route::put('/api/users/{id}', [AdminController::class, 'updateUser']);
    Route::post('/api/users/{id}/activate', [AdminController::class, 'activateUser']);
    Route::post('/api/users/{id}/deactivate', [AdminController::class, 'deactivateUser']);
    Route::post('/api/users/{id}/reset-password', [AdminController::class, 'resetUserPassword']);

    // CRUD Kamera RTSP (Admin)
    Route::get('/api/cameras', [AdminController::class, 'getCameras']);
    Route::get('/api/cameras/available', [AdminController::class, 'getAvailableWebcams']);
    Route::post('/api/cameras', [AdminController::class, 'storeCamera']);
    Route::put('/api/cameras/{id}', [AdminController::class, 'updateCamera']);
    Route::delete('/api/cameras/{id}', [AdminController::class, 'deleteCamera']);

    // Sertifikat (Admin - untuk download)
    Route::get('/api/sertifikat/download/{historyId}', [SertifikatController::class, 'download']);
});

// Routes untuk Admin & Timdis (Dashboard akses)
Route::middleware(['auth', 'role:admin,timdis'])->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard_admin'])->name('admin.dashboard');
    Route::get('/timdis/dashboard', [AdminController::class, 'dashboard_admin'])->name('timdis.dashboard');
    Route::get('/api/dashboard', [AdminController::class, 'getDashboardData'])->name('api.dashboard.data');
    Route::get('/api/attendance/today', [AdminController::class, 'getAttendanceToday']);
    Route::get('/api/attendance/history', [AdminController::class, 'getAttendanceHistory']);
    Route::get('/api/attendance/export', [AdminController::class, 'exportAttendance']);
    Route::get('/api/mahasiswa', [AdminController::class, 'getAllMahasiswa']);
});
