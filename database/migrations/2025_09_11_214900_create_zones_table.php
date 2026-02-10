<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('nom');
            $table->jsonb('pays');
            $table->boolean('actif')->default(true);
            $table->string('backoffice_id')->nullable();
            $table->foreign('backoffice_id')->references('id')->on('backoffices')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
