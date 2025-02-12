<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('url')->unique();
            $table->string('status')->default('pending');
            $table->json('analysis_data')->nullable();
            $table->integer('check_interval')->default(5);
            $table->json('monitoring_settings')->nullable();
            $table->timestamp('last_checked')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('status');
            $table->index('last_checked');
        });

        Schema::create('ssl_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('website_id')->constrained('websites')->onDelete('cascade');
            $table->boolean('is_valid');
            $table->dateTime('valid_from');
            $table->dateTime('valid_to');
            $table->string('issuer');
            $table->string('protocol');
            $table->json('certificate_info');
            $table->timestamps();
            
            // Indexes
            $table->index('valid_to');
            $table->index('is_valid');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ssl_details');
        Schema::dropIfExists('websites');
    }
};