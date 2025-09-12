<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom'); // Ex: "Zone 1,2", "Zone 3,4,5"
            $table->string('code')->unique(); // Ex: "Z1_2", "Z3_4_5"
            $table->json('pays'); // Liste des pays de cette zone
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
