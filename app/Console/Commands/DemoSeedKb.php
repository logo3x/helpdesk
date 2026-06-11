<?php

namespace App\Console\Commands;

use App\Models\KbArticle;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Seed/reset del KB de demo usado por el script Playwright
 * tests/e2e/kb-demo.spec.cjs.
 *
 * Borra el KB previo (si existía) y lo recrea con status=published para
 * que el supervisor del video lo encuentre listo y el chatbot del
 * usuario final lo matchee en la consulta.
 */
#[Signature('demo:seed-kb')]
#[Description('Crea el KB de demo "Acceso VPN" para el script Playwright')]
class DemoSeedKb extends Command
{
    public function handle(): int
    {
        $slug = 'solicitar-acceso-sap-s4hana-demo';
        KbArticle::where('slug', $slug)->forceDelete();

        $supervisor = User::where('email', 'demo-supervisor@confipetrol.local')->first();
        if (! $supervisor) {
            $this->error('Falta el user demo-supervisor@confipetrol.local. Crealo primero.');

            return self::FAILURE;
        }

        $body = <<<'MD'
## Cuándo aplica

Cuando un colaborador necesita acceso a **SAP S/4HANA** para registrar pedidos, consultar inventario o aprobar órdenes de compra.

## Requisitos previos

- Estar contratado y tener correo `@confipetrol.com` activo.
- Aprobación escrita del jefe directo indicando el rol funcional (compras, finanzas, almacén, etc.).

## Pasos a seguir

1. Tu jefe directo debe enviar un correo a **accesos.sap@confipetrol.com** con el rol solicitado.
2. El equipo de **TI - Aplicaciones** crea el usuario en SAP en máximo **24 horas hábiles**.
3. Recibirás un correo con tu usuario SAP y una **contraseña temporal**.
4. Al primer login en `https://sap.confipetrol.com` se te pedirá cambiarla.
5. Configura tu **token MFA** en Microsoft Authenticator escaneando el QR que te envían adjunto.

## Si el problema persiste

Crea un ticket en la categoría **TI - Aplicaciones SAP** indicando tu cédula y el rol funcional que te asignaron.
MD;

        $kb = KbArticle::create([
            'title' => 'Solicitar acceso a SAP S/4HANA en Confipetrol (Demo)',
            'slug' => $slug,
            'body' => $body,
            'status' => 'published',
            'department_id' => $supervisor->department_id ?? 1,
            'author_id' => $supervisor->id,
            'published_at' => now(),
        ]);

        $this->info("KB demo creado #{$kb->id} ({$kb->title})");

        return self::SUCCESS;
    }
}
