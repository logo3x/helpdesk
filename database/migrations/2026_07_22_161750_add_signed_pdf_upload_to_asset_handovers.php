<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('asset_handovers', 'uploaded_signed_pdf_path')) {
            Schema::table('asset_handovers', function (Blueprint $table) {
                $table->string('uploaded_signed_pdf_path')->nullable()->after('signed_pdf_path');
                $table->timestamp('uploaded_signed_at')->nullable()->after('uploaded_signed_pdf_path');
                $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete()->after('uploaded_signed_at');
            });
        }
    }

    public function down(): void
    {
        Schema::table('asset_handovers', function (Blueprint $table) {
            $table->dropForeign(['uploaded_by_user_id']);
            $table->dropColumn(['uploaded_signed_pdf_path', 'uploaded_signed_at', 'uploaded_by_user_id']);
        });
    }
};
