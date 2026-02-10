<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->uuid('backoffice_id')->nullable()->after('id');
            $table->foreign('backoffice_id')->references('id')->on('backoffices')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropForeign(['backoffice_id']);
            $table->dropColumn('backoffice_id');
        });
    }
};
