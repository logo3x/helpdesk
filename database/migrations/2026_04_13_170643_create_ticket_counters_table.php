<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atomic per-year counter for ticket numbering (TK-YYYY-NNNNN).
 *
 * A dedicated table lets us wrap counter increments in a row-level lock
 * (SELECT ... FOR UPDATE) without locking the tickets table itself,
 * which is important once the system handles hundreds of concurrent writes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_counters', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_counters');
    }
};
