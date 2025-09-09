<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            // Type d'agence: mere ou partenaire
            $table->enum('type', ['mere', 'partenaire'])->default('partenaire')->after('promotions');
            // Agence mère (hiérarchie), nullable
            $table->foreignUuid('agence_mere_id')->nullable()->after('type')->constrained('agences')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agence_mere_id');
            $table->dropColumn('type');
        });
    }
};
