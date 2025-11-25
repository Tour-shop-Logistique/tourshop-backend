<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('agence_id')->references('id')->on('agences')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('backoffice_id')->references('id')->on('backoffices')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['agence_id']);
            $table->dropForeign(['backoffice_id']);
        });
    }
};
