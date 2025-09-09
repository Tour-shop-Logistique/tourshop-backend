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
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id(); // L'ID du jeton lui-même peut rester un auto-incrément

            // Remplace $table->morphs('tokenable') par les champs UUID explicites
            $table->uuid('tokenable_id');
            $table->string('tokenable_type');

            // Ajoute une contrainte d'index pour l'accès polymorphique si elle n'est pas déjà présente via morphs
            $table->index(['tokenable_id', 'tokenable_type']);

            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};