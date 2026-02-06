<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('produits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('category_id');
            $table->string('designation', 150);
            $table->string('reference', 50);
            $table->uuid('backoffice_id')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('category_products')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('backoffice_id')->references('id')->on('backoffices')->nullOnDelete()->cascadeOnUpdate();
            $table->unique(['backoffice_id', 'reference']);
            $table->index(['category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
