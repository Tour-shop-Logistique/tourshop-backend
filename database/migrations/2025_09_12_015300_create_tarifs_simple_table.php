<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\TypeExpedition;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tarifs_simple', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('backoffice_id')->nullable();
            $table->foreign('backoffice_id')->references('id')->on('backoffices')->onDelete('set null')->onUpdate('cascade');
            $table->enum('type_expedition', [TypeExpedition::class])->default(TypeExpedition::LD);
            $table->decimal('indice', 5, 1);
            $table->string('pays')->nullable();
            $table->string('zone_destination_id');
            $table->decimal('montant_base', 12, 2);
            $table->decimal('pourcentage_prestation', 5, 2)->nullable();
            $table->decimal('montant_prestation', 12, 2)->nullable();
            $table->decimal('montant_expedition', 12, 2)->nullable();


            // Statut
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->index(['indice']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tarifs_simple');
    }
};
