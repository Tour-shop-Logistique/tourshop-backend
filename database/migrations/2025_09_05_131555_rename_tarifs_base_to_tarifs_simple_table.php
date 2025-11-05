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
        Schema::rename('tarifs_base', 'tarifs_simple');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('tarifs_simple', 'tarifs_base');
    }
};
