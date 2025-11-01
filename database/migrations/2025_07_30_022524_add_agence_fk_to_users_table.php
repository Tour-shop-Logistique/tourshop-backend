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
        Schema::table('users', function (Blueprint $table) {
            // S'assurer que la colonne existe et est nullable
            // $table->uuid('agence_id')->nullable()->change(); // décommentez si nécessaire

            // Ajouter la contrainte étrangère maintenant que 'agences' existe
            $table->foreign('agence_id')
                ->references('id')->on('agences')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            // Variante fluide:
            // $table->foreignUuid('agence_id')->nullable()->constrained('agences')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['agence_id']);
        });
    }
};
