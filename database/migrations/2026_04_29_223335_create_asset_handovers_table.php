<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actas de entrega de equipos IT — formato oficial Confipetrol IT-ADM1-F-5.
 *
 * Cada vez que IT entrega físicamente un activo a un usuario (custodio),
 * se genera un PDF con todos los datos del equipo, el receptor y los
 * párrafos legales. Esta tabla guarda la traza para auditoría y para
 * la "Hoja de vida" del activo.
 *
 * Una vez creada el acta NO se modifica (auditoría inmutable). Si hay
 * un error, se crea otra acta nueva (anula+entrega) o se sube la versión
 * firmada con `signed_pdf_path`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_handovers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('acta_number')->unique()->comment('Número correlativo del acta (autogenerado)');

            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('delivered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('delivered_at')->useCurrent();

            // Snapshot de datos al momento de la entrega (no se actualizan
            // si el activo cambia después — el acta refleja el estado en
            // que se entregó).
            $table->string('asset_tag_snapshot', 50)->nullable();
            $table->string('asset_type_snapshot', 50)->nullable();
            $table->string('manufacturer_snapshot', 255)->nullable();
            $table->string('model_snapshot', 255)->nullable();
            $table->string('serial_snapshot', 255)->nullable();
            $table->string('sap_code_snapshot', 60)->nullable();
            $table->string('field_snapshot', 100)->nullable();
            $table->foreignId('project_id_snapshot')->nullable()->constrained('projects')->nullOnDelete();

            // Datos específicos del acta
            $table->string('condition_at_delivery', 30)->default('bueno')
                ->comment('bueno, regular, otra');
            $table->string('reference', 255)->nullable()
                ->comment('Texto "REFERENCIA" del acta — ej: "Entrega de LAPTOP"');
            $table->text('observations')->nullable()
                ->comment('Texto libre — ej: "Acta #: 1432 --- CON CARGADOR"');

            // Versión del template Blade que generó el PDF — sirve si el
            // formato oficial cambia y necesitas saber qué versión usó
            // cada acta histórica.
            $table->string('template_version', 20)->default('IT-ADM1-F-5_v3');

            // Rutas a archivos
            $table->string('pdf_path', 500)->nullable()
                ->comment('PDF generado al crear el acta');
            $table->string('signed_pdf_path', 500)->nullable()
                ->comment('PDF firmado y escaneado, subido luego');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['asset_id', 'delivered_at']);
            $table->index('received_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_handovers');
    }
};
