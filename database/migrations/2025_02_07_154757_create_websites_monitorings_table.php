<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('websitess', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('url')->unique();
            $table->string('status')->default('pending');
            $table->json('analysis_data')->nullable();
            $table->integer('check_interval')->default(5);
            $table->json('monitoring_settings')->nullable();
            $table->json('scan_results')->nullable();
            $table->decimal('uptime_7d', 5, 2)->default(100.00); // 99.99%
            
            // Perbaikan: Hapus duplikasi dan sesuaikan untuk SQLite
            if (config('database.default') === 'sqlite') {
                $table->text('notification_settings')->nullable()->default(json_encode([]));
            } else {
                $table->json('notification_settings')->nullable();
            }
            
            $table->timestamp('last_checked')->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index('last_checked');
        });

        Schema::create('ssl_detailss', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')
                  ->constrained('websitess') // Tambahkan nama tabel yang benar
                  ->onDelete('cascade');
            $table->boolean('is_valid')->default(false);
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_to')->nullable();
            $table->string('issuer')->nullable();
            $table->string('protocol')->nullable();
            $table->json('certificate_info')->nullable();
            $table->timestamps();
        });
        Schema::create('scan_histori', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained('websitess');
            $table->string('status'); // Kolom wajib
            $table->json('scan_results');
            $table->timestamp('scanned_at');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('scan_histori');
        Schema::dropIfExists('ssl_details');
        Schema::dropIfExists('websitess');
    }
};