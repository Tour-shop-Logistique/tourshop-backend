<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('colis_articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('colis_id');
            $table->uuid('produit_id');
            $table->timestamps();

            $table->foreign('colis_id')->references('id')->on('colis')->onDelete('cascade');

            $table->foreign('produit_id')->references('id')->on('produits')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('colis_articles');
    }
};
