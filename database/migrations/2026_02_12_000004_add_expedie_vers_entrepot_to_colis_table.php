<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * Colis expédiés vers l'entrepôt par l'agence de départ.
     */
    public function up(): void
    {
        Schema::table('colis', function (Blueprint $table) {
            $table->boolean('is_expedie_vers_entrepot')->default(false)->after('received_at_agence_depart');
            $table->timestamp('expedie_vers_entrepot_at')->nullable()->after('is_expedie_vers_entrepot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('colis', function (Blueprint $table) {
            $table->dropColumn(['is_expedie_vers_entrepot', 'expedie_vers_entrepot_at']);
        });
    }
};
