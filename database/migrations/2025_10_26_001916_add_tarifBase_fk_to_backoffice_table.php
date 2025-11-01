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
        Schema::table('tarifs_base', function (Blueprint $table) {
            $table->uuid('backoffice_id')->nullable()->after('actif');
            $table->string('pays')->nullable()->after('indice');
            $table->foreign('backoffice_id')->references('id')->on('backoffices')->onDelete('set null')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tarifs_base', function (Blueprint $table) {
            $table->dropForeign(['backoffice_id']);
            $table->dropColumn('backoffice_id');
            $table->dropColumn('pays');
        });
    }
};
