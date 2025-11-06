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
        Schema::create('tarifs_personnalises', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->uuid('tarif_simple_id');
            $table->json('prix_zones');
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->foreign('tarif_simple_id')->references('id')->on('tarifs_simple');
            $table->index('code');
            $table->index('actif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tarifs_personnalises');
    }
};
