<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agence_id')->nullable();
            $table->uuid('backoffice_id')->nullable();
            $table->string('nom');
            $table->string('prenoms');
            $table->string('telephone')->unique();
            $table->string('email')->nullable();
            $table->enum('type', ['client', 'livreur', 'admin', 'agence', 'backoffice']);
            $table->timestamp('telephone_verified_at')->nullable();
            $table->string('password');
            $table->string('avatar')->nullable();
            $table->jsonb('adresses_favoris')->nullable(); // Pour clients
            $table->boolean('disponible')->default(true); // Pour livreurs
            $table->boolean('actif')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->string('role')->nullable();
            $table->rememberToken();
            $table->timestamps();
                    });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
