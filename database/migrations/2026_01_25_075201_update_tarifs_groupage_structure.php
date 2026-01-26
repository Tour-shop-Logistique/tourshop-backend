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
        Schema::table('tarifs_groupage', function (Blueprint $table) {
            // $table->dropColumn('prix_modes');
            $table->string('mode')->nullable()->after('type_expedition');
            $table->string('ligne')->nullable()->after('mode');
            $table->decimal('montant_base', 10, 2)->nullable()->after('ligne');
            $table->decimal('pourcentage_prestation', 10, 2)->nullable()->after('montant_base');
            $table->decimal('montant_prestation', 10, 2)->nullable()->after('pourcentage_prestation');
            $table->decimal('montant_expedition', 10, 2)->nullable()->after('montant_prestation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tarifs_groupage', function (Blueprint $table) {
            // $table->jsonb('prix_modes')->nullable()->after('type_expedition');
            $table->dropColumn([
                'mode',
                'ligne',
                'montant_base',
                'pourcentage_prestation',
                'montant_prestation',
                'montant_expedition'
            ]);
        });
    }
};
