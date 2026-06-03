<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El custodio confirma la recepción del activo desde /portal/assets.
 * received_confirmed_at = null mientras el handover está pendiente de
 * confirmación; cuando el usuario hace click en "Confirmar recepción"
 * se setea con now().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('asset_handovers', 'received_confirmed_at')) {
            Schema::table('asset_handovers', function (Blueprint $table): void {
                $table->timestamp('received_confirmed_at')->nullable()->after('delivered_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('asset_handovers', 'received_confirmed_at')) {
            Schema::table('asset_handovers', function (Blueprint $table): void {
                $table->dropColumn('received_confirmed_at');
            });
        }
    }
};
