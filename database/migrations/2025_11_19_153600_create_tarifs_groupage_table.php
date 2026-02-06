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
            $table->enum('type_expedition', array_column(TypeExpedition::cases(), 'value'));
            $table->string('mode')->nullable();
            $table->string('ligne')->nullable();
            $table->decimal('montant_base', 10, 2)->nullable();
            $table->decimal('pourcentage_prestation', 10, 2)->nullable();
            $table->decimal('montant_prestation', 10, 2)->nullable();
            $table->decimal('montant_expedition', 10, 2)->nullable();
            $table->boolean('actif')->default(true);
            $table->string('pays')->nullable();
            $table->uuid('backoffice_id')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('category_products')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('backoffice_id')->references('id')->on('backoffices')->nullOnDelete()->cascadeOnUpdate();
            $table->index(['category_id']);
            $table->index(['backoffice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifs_groupage');
    }
};
