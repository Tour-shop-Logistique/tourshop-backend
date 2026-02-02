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
        Schema::create('colis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('expedition_id');
            $table->uuid('category_id')->nullable();
            $table->string('code_colis');
            $table->string('designation')->nullable();
            $table->jsonb('articles')->nullable();
            $table->string('photo')->nullable();
            $table->decimal('longueur', 10, 2)->nullable()->comment('en cm');
            $table->decimal('largeur', 10, 2)->nullable()->comment('en cm');
            $table->decimal('hauteur', 10, 2)->nullable()->comment('en cm');
            $table->decimal('volume', 10, 2)->nullable()->comment('en cm³');
            $table->decimal('poids', 10, 2)->comment('en kg');
            $table->decimal('prix_emballage', 10, 2)->nullable();
            $table->decimal('prix_unitaire', 10, 2)->nullable();
            $table->decimal('montant_colis_base', 10, 2)->nullable();
            $table->decimal('pourcentage_prestation', 10, 2)->nullable();
            $table->decimal('montant_colis_prestation', 10, 2)->nullable();
            $table->decimal('montant_colis_total', 10, 2)->nullable();
            $table->timestamps();

            $table->foreign('expedition_id')->references('id')->on('expeditions')->onDelete('set null');
            $table->foreign('category_id')->references('id')->on('category_products')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('colis');
    }
};
