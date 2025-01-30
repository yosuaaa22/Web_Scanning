<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScanHistoriesTable extends Migration
{
    public function up()
    {
        Schema::create('scan_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('scan_result_id');
            $table->string('url');
            $table->string('risk_level');
            $table->json('detected_threats');
            $table->timestamp('scan_timestamp');
            $table->timestamps();

            $table->foreign('scan_result_id')
                  ->references('id')
                  ->on('scan_results')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('scan_histories');
    }
}
