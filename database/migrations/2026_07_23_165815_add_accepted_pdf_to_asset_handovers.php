<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_handovers', function (Blueprint $table) {
            $table->string('accepted_pdf_path')->nullable()->after('uploaded_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('asset_handovers', function (Blueprint $table) {
            $table->dropColumn('accepted_pdf_path');
        });
    }
};
