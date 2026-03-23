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
        Schema::table('expeditions', function (Blueprint $table) {
            $table->dropColumn([
                'commission_livreur_enlevement',
                'commission_agence_enlevement',
                'commission_emballage_agence',
                'commission_emballage_backoffice',
                'commission_livreur_livraison',
                'commission_agence_livraison',
                'commission_agence_retard',
                'commission_tourshop_retard'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expeditions', function (Blueprint $table) {
            $table->decimal('commission_livreur_enlevement', 12, 2)->default(0);
            $table->decimal('commission_agence_enlevement', 12, 2)->default(0);
            $table->decimal('commission_emballage_agence', 12, 2)->default(0);
            $table->decimal('commission_emballage_backoffice', 12, 2)->default(0);
            $table->decimal('commission_livreur_livraison', 12, 2)->default(0);
            $table->decimal('commission_agence_livraison', 12, 2)->default(0);
            $table->decimal('commission_agence_retard', 12, 2)->default(0);
            $table->decimal('commission_tourshop_retard', 12, 2)->default(0);
        });
    }
};
