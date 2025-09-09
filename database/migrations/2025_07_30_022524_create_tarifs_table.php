<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tarifs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agence_id')->nullable()->constrained('agences')->onDelete('cascade');

            $table->string('nom')->nullable();  // Nom du tarif (ex: "Standard", "Express", "Urgent")
            $table->string('type_colis')->nullable();  // Type de colis concerné (ex: "Document", "Petit Colis", "Fragile", "Liquide")

            $table->decimal('prix_base', 10, 2); // Prix de base fixe pour ce tarif (hors poids/distance)
            $table->decimal('prix_par_km', 10, 2); // Coût additionnel par kilomètre
            $table->decimal('prix_par_kg', 10, 2); // Coût additionnel par kilogramme
            $table->decimal('poids_max_kg', 10, 2)->nullable(); // Poids maximum en kg auquel ce tarif s'applique (ou limite supérieure)
            
            $table->integer('distance_min_km')->nullable(); // Distance minimale en km pour laquelle ce tarif est applicable
            $table->integer('distance_max_km')->nullable(); // Distance maximale en km pour laquelle ce tarif est applicable

            $table->decimal('supplement_domicile', 10, 2)->default(0); // Supplément pour la livraison à domicile
            $table->decimal('supplement_express', 10, 2)->default(0); // Supplément pour un service express
            $table->boolean('actif')->default(true); // Indique si le tarif est actif et utilisable
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarifs');
    }
};
