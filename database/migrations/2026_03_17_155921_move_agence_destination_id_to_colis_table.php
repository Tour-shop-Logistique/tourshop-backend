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
            $table->dropColumn('agence_destination_id');
        });

        Schema::table('colis', function (Blueprint $table) {
            $table->uuid('agence_destination_id')->nullable()->after('expedition_id');
            $table->foreign('agence_destination_id')->references('id')->on('agences')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('colis', function (Blueprint $table) {
            $table->dropForeign(['agence_destination_id']);
            $table->dropColumn('agence_destination_id');
        });

        Schema::table('expeditions', function (Blueprint $table) {
            $table->uuid('agence_destination_id')->nullable()->after('agence_id');
            $table->foreign('agence_destination_id')->references('id')->on('agences')->onDelete('set null');
        });
    }
};
