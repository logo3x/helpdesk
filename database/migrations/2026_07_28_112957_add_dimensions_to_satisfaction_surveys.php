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
        Schema::table('satisfaction_surveys', function (Blueprint $table) {
            // Ratings por dimensión (1-5 cada una), null hasta que el usuario responde
            $table->unsignedTinyInteger('rating_attention')->nullable()->after('rating')->comment('Atención general 1-5');
            $table->unsignedTinyInteger('rating_contact')->nullable()->after('rating_attention')->comment('Facilidad de contacto 1-5');
            $table->unsignedTinyInteger('rating_resolution')->nullable()->after('rating_contact')->comment('Resolución del incidente 1-5');
            $table->unsignedTinyInteger('rating_time')->nullable()->after('rating_resolution')->comment('Tiempo de solución 1-5');
            $table->unsignedTinyInteger('rating_knowledge')->nullable()->after('rating_time')->comment('Conocimiento técnico 1-5');
            $table->unsignedTinyInteger('rating_attitude')->nullable()->after('rating_knowledge')->comment('Amabilidad y disposición 1-5');
        });
    }

    public function down(): void
    {
        Schema::table('satisfaction_surveys', function (Blueprint $table) {
            $table->dropColumn([
                'rating_attention', 'rating_contact', 'rating_resolution',
                'rating_time', 'rating_knowledge', 'rating_attitude',
            ]);
        });
    }
};
