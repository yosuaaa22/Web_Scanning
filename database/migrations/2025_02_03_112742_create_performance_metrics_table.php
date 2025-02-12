<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('memory_usage')->nullable();
            $table->integer('timestamp');
            $table->string('php_memory_limit')->nullable();
            $table->bigInteger('php_memory_usage')->nullable();
            $table->float('cpu_usage')->nullable();
            $table->json('disk_free_space')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_metrics');
    }
};