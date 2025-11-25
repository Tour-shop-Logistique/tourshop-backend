<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('nom_agence');
            $table->text('description')->nullable();
            $table->string('adresse');
            $table->string('ville');
            $table->string('commune')->nullable();
            $table->string('pays');
            $table->string('telephone', 20)->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->json('horaires')->nullable();
            $table->json('photos')->nullable();
            $table->string('logo')->nullable();
            $table->boolean('actif')->default(true);
            $table->text('message_accueil')->nullable();
            $table->timestamps();
              $table->foreign('user_id')->references('id')->on('users')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agences');
    }
};
