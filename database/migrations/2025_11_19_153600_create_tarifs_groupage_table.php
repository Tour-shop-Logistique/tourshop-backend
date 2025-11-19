<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tarifs_groupage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('category_id');
            $table->string('mode_expedition', 10)->default('groupage');
            $table->decimal('tarif_minimum', 12, 2)->nullable();
            $table->json('prix_modes');
            $table->boolean('actif')->default(true);
            $table->string('pays')->nullable();
            $table->uuid('backoffice_id')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('category_products')->cascadeOnDelete();
            $table->foreign('backoffice_id')->references('id')->on('backoffices')->nullOnDelete();
            $table->index(['category_id']);
            $table->index(['backoffice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifs_groupage');
    }
};
