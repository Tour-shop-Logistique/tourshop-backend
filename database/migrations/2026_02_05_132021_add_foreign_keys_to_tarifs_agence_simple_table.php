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
        Schema::table('tarifs_agence_simple', function (Blueprint $table) {
            $table->foreign('zone_destination_id')->references('id')->on('zones')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tarifs_agence_simple', function (Blueprint $table) {
            $table->dropForeign(['zone_destination_id']);
        });
    }
};
