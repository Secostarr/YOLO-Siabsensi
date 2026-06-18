<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            // id: disesuaikan agar menjadi int(11) AUTO_INCREMENT
            $table->integer('id')->autoIncrement();

            $table->string('username', 50);
            $table->string('password_hash', 255);
            $table->string('full_name', 255);
            $table->string('email', 255)->nullable();
            $table->enum('role', ['admin', 'timdis', 'mahasiswa', 'garda'])->default('mahasiswa');
            $table->string('mahasiswa_id', 50)->nullable();
            $table->tinyInteger('is_active')->nullable()->default(1);
            $table->dateTime('last_login')->nullable();

            // Tambahkan baris ini untuk membuat kolom remember_token
            $table->rememberToken();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // ---------------------------------------------------------
            // DEFINISI INDEKS (Sesuai tabel indeks di gambar)
            // ---------------------------------------------------------
            $table->unique('username', 'username');
            $table->index('username', 'idx_username');
            $table->index('role', 'idx_role');
            $table->index('mahasiswa_id', 'idx_mahasiswa');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
