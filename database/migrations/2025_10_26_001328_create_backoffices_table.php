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
        Schema::create('backoffices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable(); // Admin créateur du backoffice
            $table->string('nom_organisation');
            $table->string('telephone');
            $table->string('localisation')->nullable();
            $table->string('adresse');
            $table->string('ville');
            $table->string('commune')->nullable();
            $table->string('pays');
            $table->string('email')->nullable();
            $table->string('logo')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backoffices');
    }
};
