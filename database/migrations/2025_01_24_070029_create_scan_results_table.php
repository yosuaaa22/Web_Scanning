<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateScanResultsTable extends Migration
{
    public function up()
    {
        Schema::create('scan_results', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->string('backdoor_risk');
            $table->string('gambling_risk');
            $table->dateTime('scan_time');
            $table->text('detailed_report')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('scan_results');
    }
}
