<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('category_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom', 150);
            $table->boolean('actif')->default(true);
            $table->string('pays')->nullable();
            $table->decimal('prix_kg', 12, 2);
            $table->uuid('backoffice_id')->nullable();
            $table->timestamps();

            $table->foreign('backoffice_id')->references('id')->on('backoffices')->nullOnDelete();
            $table->index(['backoffice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_products');
    }
};
