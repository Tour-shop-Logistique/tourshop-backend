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
        // Vérifier si la colonne n'existe pas déjà (pour éviter les erreurs en cas de rollback/relance)
        if (!Schema::hasColumn('users', 'agence_id')) {
            Schema::table('users', function (Blueprint $table) {
                // Colonne agence_id de même type que la clé primaire d'agences (uuid ou bigint)
                $table->uuid('agence_id')
                    ->nullable()
                    ->after('id'); // Positionner après la colonne id pour une meilleure lisibilité

                // Ajout de la contrainte de clé étrangère
                $table->foreign('agence_id')
                    ->references('id')
                    ->on('agences')
                    ->onDelete('set null') // Si l'agence est supprimée, mettre agence_id à null
                    ->onUpdate('cascade'); // Si l'id de l'agence change, mettre à jour la référence

                // Index pour optimiser les requêtes de jointure
                $table->index('agence_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'agence_id')) {
            Schema::table('users', function (Blueprint $table) {
                // Supprimer la contrainte de clé étrangère d'abord
                $table->dropForeign(['agence_id']);
                // Puis supprimer la colonne
                $table->dropColumn('agence_id');
            });
        }
    }
};
