<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add new columns to websites table
        Schema::table('websites', function (Blueprint $table) {
            if (!Schema::hasColumn('websites', 'ssl_expires_at')) {
                $table->timestamp('ssl_expires_at')->nullable();
            }
            if (!Schema::hasColumn('websites', 'domain_expires_at')) {
                $table->timestamp('domain_expires_at')->nullable();
            }
            if (!Schema::hasColumn('websites', 'uptime_percentage')) {
                $table->float('uptime_percentage')->default(100);
            }
            if (!Schema::hasColumn('websites', 'check_interval')) {
                $table->integer('check_interval')->default(300);
            }
            if (!Schema::hasColumn('websites', 'alert_threshold_response_time')) {
                $table->integer('alert_threshold_response_time')->default(1000);
            }
            if (!Schema::hasColumn('websites', 'expected_status_code')) {
                $table->integer('expected_status_code')->default(200);
            }
            if (!Schema::hasColumn('websites', 'expected_response_pattern')) {
                $table->string('expected_response_pattern')->nullable();
            }
            if (!Schema::hasColumn('websites', 'notification_channels')) {
                $table->json('notification_channels')->nullable();
            }
        });

        // Create uptime reports table if it doesn't exist
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

        // Create security scans table if it doesn't exist
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

        // Add new columns to monitoring_logs if it exists
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
        Schema::table('websites', function (Blueprint $table) {
            $table->dropColumn([
                'ssl_expires_at',
                'domain_expires_at',
                'uptime_percentage',
                'check_interval',
                'alert_threshold_response_time',
                'expected_status_code',
                'expected_response_pattern',
                'notification_channels'
            ]);
        });

        Schema::dropIfExists('uptime_reports');
        Schema::dropIfExists('security_scans');
    }
};