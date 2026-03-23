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
            $table->boolean('is_controlled')->default(false);
            $table->timestamp('controlled_at')->nullable();
            $table->boolean('is_received_by_backoffice')->default(false);
            $table->timestamp('received_at_backoffice')->nullable();
            $table->boolean('is_received_by_agence_destination')->default(false);
            $table->timestamp('received_at_agence_destination')->nullable();
            $table->boolean('is_received_by_agence_depart')->default(false);
            $table->timestamp('received_at_agence_depart')->nullable();
            $table->boolean('is_collected_by_client')->default(false);
            $table->timestamp('collected_at')->nullable();
            $table->string('code_validation_retrait')->nullable();
            $table->timestamp('code_validation_retrait_expires_at')->nullable();
            $table->timestamp('date_limite_retrait')->nullable();
            $table->boolean('is_retard_retrait')->default(false);
            $table->decimal('frais_retard_retrait', 12, 2)->default(0);
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
