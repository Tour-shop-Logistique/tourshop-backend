<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('colis', function (Blueprint $table) {
            // Motif de refus et validation
            $table->text('refuse_reason')->nullable()->after('status');
            $table->timestamp('validated_at')->nullable()->after('refuse_reason');

            // Facturation
            $table->string('facture_numero')->nullable()->after('commission_agence');
            $table->decimal('montant_facture', 10, 2)->nullable()->after('facture_numero');
            $table->string('paiement_mode')->nullable()->after('montant_facture'); // ex: cash, momo, carte
            $table->string('paiement_status')->nullable()->after('paiement_mode'); // ex: pending, paid, failed

            // Preuves additionnelles
            $table->string('photo_scan')->nullable()->after('photo_livraison');
            $table->string('scan_code')->nullable()->after('photo_scan');
            $table->json('preuve_json')->nullable()->after('scan_code');
        });

        // Index utile sur le statut
        Schema::table('colis', function (Blueprint $table) {
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('colis', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn([
                'refuse_reason',
                'validated_at',
                'facture_numero',
                'montant_facture',
                'paiement_mode',
                'paiement_status',
                'photo_scan',
                'scan_code',
                'preuve_json',
            ]);
        });
    }
};
