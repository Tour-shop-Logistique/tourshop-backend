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
        Schema::create('expeditions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agence_id')->index();
            $table->uuid('user_id')->nullable()->index();

            // Livreurs associés à chaque étape
            $table->uuid('livreur_enlevement_id')->nullable()->index();
            $table->uuid('livreur_deplacement_id')->nullable()->index();
            $table->uuid('livreur_livraison_id')->nullable()->index();

            $table->string('reference')->unique();
            $table->string('code_suivi_expedition')->unique()->nullable();
            $table->string('code_validation_reception')->nullable();

            $table->json('expediteur');
            $table->json('destinataire');

            // Localisation
            $table->string('zone_depart_id')->nullable()->index();
            $table->string('pays_depart')->nullable();
            $table->string('zone_destination_id')->nullable()->index();
            $table->string('pays_destination')->nullable();

            // Type et Statuts
            $table->string('type_expedition'); // Enum mapping: simple, groupage_afrique, groupage_ca, groupage_dhd
            $table->string('statut_expedition')->default('EN_ATTENTE');
            $table->string('statut_paiement')->default('EN_ATTENTE');

            // Financier principal
            $table->decimal('montant_base', 12, 2)->default(0);
            $table->decimal('pourcentage_prestation', 5, 2)->nullable();
            $table->decimal('montant_prestation', 12, 2)->default(0);
            $table->decimal('montant_expedition', 12, 2)->default(0);

            // Détails des Frais
            $table->decimal('frais_enlevement_domicile', 12, 2)->default(0);
            $table->decimal('frais_livraison_domicile', 12, 2)->default(0);
            $table->decimal('frais_emballage', 12, 2)->default(0);
            $table->decimal('frais_enlevement_agence', 12, 2)->default(0);
            $table->decimal('frais_retard_retrait', 12, 2)->default(0);
            $table->decimal('frais_douane', 12, 2)->default(0);

            // Options de service
            $table->boolean('is_enlevement_domicile')->default(false);
            $table->string('coord_enlevement')->nullable();
            $table->text('instructions_enlevement')->nullable();
            $table->decimal('distance_domicile_agence', 8, 2)->nullable();

            $table->boolean('is_livraison_domicile')->default(false);
            $table->string('coord_livraison')->nullable();
            $table->text('instructions_livraison')->nullable();

            $table->string('delai_retrait')->nullable();
            $table->boolean('is_retard_retrait')->default(false);
            $table->boolean('is_paiement_credit')->default(false);

            // Dates du workflow
            $table->timestamp('date_prevue_enlevement')->nullable();
            $table->timestamp('date_enlevement_client')->nullable();
            $table->timestamp('date_livraison_agence')->nullable();
            $table->timestamp('date_deplacement_entrepot')->nullable();
            $table->timestamp('date_expedition_depart')->nullable();
            $table->timestamp('date_expedition_arrivee')->nullable();
            $table->timestamp('date_reception_agence')->nullable();
            $table->timestamp('date_limite_retrait')->nullable();
            $table->timestamp('date_reception_client')->nullable();
            $table->timestamp('date_livraison_reelle')->nullable();
            $table->timestamp('date_annulation')->nullable();
            $table->text('motif_annulation')->nullable();

            // Commissions calculées
            $table->decimal('commission_livreur_enlevement', 12, 2)->default(0);
            $table->decimal('commission_agence_enlevement', 12, 2)->default(0);
            $table->decimal('commission_livreur_livraison', 12, 2)->default(0);
            $table->decimal('commission_agence_livraison', 12, 2)->default(0);
            $table->decimal('commission_agence_retard', 12, 2)->default(0);
            $table->decimal('commission_tourshop_retard', 12, 2)->default(0);

            $table->timestamps();

            // Foreign Keys (principales)

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('agence_id')->references('id')->on('agences')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expeditions');
    }
};
