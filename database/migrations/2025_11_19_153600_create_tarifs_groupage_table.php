<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\TypeExpedition;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tarifs_groupage', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('category_id')->nullable();
            $table->enum('type_expedition', [TypeExpedition::class]);
            $table->decimal('prix_unitaire', 12, 2)->nullable();
            $table->json('prix_modes')->nullable();
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
