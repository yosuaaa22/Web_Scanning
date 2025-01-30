<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Cek dulu apakah kolom sudah ada
        if (!Schema::hasColumn('scan_results', 'detailed_report')) {
            Schema::table('scan_results', function (Blueprint $table) {
                $table->longText('detailed_report')->nullable();
            });
        }
    }

    public function down()
    {
        // Cek dulu apakah kolom ada sebelum menghapus
        if (Schema::hasColumn('scan_results', 'detailed_report')) {
            Schema::table('scan_results', function (Blueprint $table) {
                $table->dropColumn('detailed_report');
            });
        }
    }
};
