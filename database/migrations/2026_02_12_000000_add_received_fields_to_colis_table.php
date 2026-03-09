<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('colis', function (Blueprint $table) {
            $table->timestamp('controlled_at')->nullable()->after('is_controlled');
            $table->boolean('is_received_by_backoffice')->default(false)->after('controlled_at');
            $table->timestamp('received_at_backoffice')->nullable()->after('is_received_by_backoffice');
            $table->boolean('is_received_by_agence')->default(false)->after('received_at');
            $table->timestamp('received_at_agence')->nullable()->after('is_received_by_agence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('colis', function (Blueprint $table) {
            $table->dropColumn(['is_received_by_backoffice', 'received_at_backoffice', 'is_received_by_agence', 'received_at_agence']);
        });
    }
};
