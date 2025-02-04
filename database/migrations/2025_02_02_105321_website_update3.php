<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Hanya menambahkan kolom yang belum ada
        // Gunakan if untuk setiap kolom untuk menghindari error duplikasi
        Schema::table('websites', function (Blueprint $table) {
            // Cek dan tambah kolom untuk monitoring kinerja
            if (!Schema::hasColumn('websites', 'uptime_percentage')) {
                $table->float('uptime_percentage')->default(100);
            }
            if (!Schema::hasColumn('websites', 'check_interval')) {
                $table->integer('check_interval')->default(300);
            }
            if (!Schema::hasColumn('websites', 'alert_threshold_response_time')) {
                $table->integer('alert_threshold_response_time')->default(1000);
            }

            // Cek dan tambah kolom untuk content verification
            if (!Schema::hasColumn('websites', 'expected_status_code')) {
                $table->integer('expected_status_code')->default(200);
            }
            if (!Schema::hasColumn('websites', 'expected_response_pattern')) {
                $table->string('expected_response_pattern')->nullable();
            }

            // Cek dan tambah kolom untuk notifikasi
            if (!Schema::hasColumn('websites', 'notification_channels')) {
                $table->json('notification_channels')->nullable();
            }
        });

        // Buat tabel uptime_reports jika belum ada
        if (!Schema::hasTable('uptime_reports')) {
            Schema::create('uptime_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained()->onDelete('cascade');
                $table->float('uptime_percentage');
                $table->integer('total_downtime_minutes');
                $table->integer('incidents_count');
                $table->timestamp('period_start');
                $table->timestamp('period_end');
                $table->timestamps();
            });
        }

        // Buat tabel security_scans jika belum ada
        if (!Schema::hasTable('security_scans')) {
            Schema::create('security_scans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('website_id')->constrained()->onDelete('cascade');
                $table->json('vulnerabilities');
                $table->json('headers_analysis');
                $table->json('ssl_details');
                $table->json('content_security_analysis');
                $table->timestamps();
            });
        }

        // Update tabel monitoring_logs yang sudah ada
        if (Schema::hasTable('monitoring_logs')) {
            Schema::table('monitoring_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('monitoring_logs', 'performance_metrics')) {
                    $table->json('performance_metrics')->nullable();
                }
            });
        }
    }

    public function down()
    {
        // Drop kolom yang ditambahkan
        Schema::table('websites', function (Blueprint $table) {
            $columns = [
                'uptime_percentage',
                'check_interval',
                'alert_threshold_response_time',
                'expected_status_code',
                'expected_response_pattern',
                'notification_channels'
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('websites', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('uptime_reports');
        Schema::dropIfExists('security_scans');
    }
};