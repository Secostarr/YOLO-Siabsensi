<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Hanya MySQL/MariaDB yang punya tipe ENUM; pada driver lain kolom role
        // sudah berupa string/check sehingga tidak perlu diubah.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'timdis', 'mahasiswa', 'garda') NOT NULL DEFAULT 'mahasiswa'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'timdis', 'mahasiswa') NOT NULL DEFAULT 'mahasiswa'");
        }
    }
};
