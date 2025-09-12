<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarifs', function (Blueprint $table) {
            // Type de colis (pour simple et groupage)
            $table->enum('type_colis', [
                'document',
                'colis_standard',
                'colis_fragile',
                'colis_volumineux',
                'produit_alimentaire',
                'electronique',
                'vetement',
                'autre'
            ])->default('colis_standard')->after('mode_expedition');

            // Dimensions pour tarification simple uniquement (en cm)
            $table->decimal('longueur_max_cm', 8, 2)->nullable()->after('poids_max_kg');
            $table->decimal('largeur_max_cm', 8, 2)->nullable()->after('longueur_max_cm');
            $table->decimal('hauteur_max_cm', 8, 2)->nullable()->after('largeur_max_cm');

            // Indice de tranche (calculé selon poids ou volume)
            $table->decimal('indice_tranche', 5, 1)->nullable()->after('hauteur_max_cm');

            // Facteur de division pour le volume (par défaut 5000)
            $table->integer('facteur_division_volume')->default(5000)->after('indice_tranche');
        });
    }

    public function down(): void
    {
        Schema::table('tarifs', function (Blueprint $table) {
            $table->dropColumn([
                'type_colis',
                'longueur_max_cm',
                'largeur_max_cm',
                'hauteur_max_cm',
                'indice_tranche',
                'facteur_division_volume'
            ]);
        });
    }
};
