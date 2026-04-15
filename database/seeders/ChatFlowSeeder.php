<?php

namespace Database\Seeders;

use App\Models\ChatFlow;
use Illuminate\Database\Seeder;

class ChatFlowSeeder extends Seeder
{
    public function run(): void
    {
        ChatFlow::updateOrCreate(
            ['slug' => 'reset-password'],
            [
                'name' => 'Reset de contraseña',
                'description' => 'Guía paso a paso para resetear contraseña de Windows/correo.',
                'triggers' => ['contraseña', 'password', 'clave', 'olvidé mi contraseña', 'no puedo entrar', 'cambiar clave'],
                'steps' => [
                    ['prompt' => '¿Qué contraseña necesitas resetear? Escribe: **1** Windows / **2** Correo / **3** Otra aplicación', 'type' => 'input'],
                    ['prompt' => "Para resetear tu contraseña, sigue estos pasos:\n\n1. Presiona **Ctrl+Alt+Supr**\n2. Selecciona **\"Cambiar contraseña\"**\n3. Ingresa tu contraseña actual y la nueva (mínimo 8 caracteres, mayúsculas, números)\n\n¿Pudiste completar el cambio? (**sí** / **no**)", 'type' => 'input'],
                    ['prompt' => '¡Perfecto! Si necesitas más ayuda, puedes crear un ticket o preguntar por otro tema.', 'type' => 'message'],
                ],
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        ChatFlow::updateOrCreate(
            ['slug' => 'vpn-setup'],
            [
                'name' => 'Configuración VPN',
                'description' => 'Guía para conectarse a la VPN corporativa.',
                'triggers' => ['vpn', 'conexión remota', 'trabajar desde casa', 'acceso remoto'],
                'steps' => [
                    ['prompt' => "Para conectarte a la VPN de Confipetrol:\n\n1. Abre el cliente **FortiClient** (lo encuentras en el menú inicio)\n2. Ingresa el servidor: **vpn.confipetrol.com**\n3. Usa tu usuario y contraseña de Windows\n\n¿Ya tienes FortiClient instalado? (**sí** / **no**)", 'type' => 'input'],
                    ['prompt' => 'Si no lo tienes instalado, descárgalo del portal de software o crea un ticket y te lo instalaremos. ¿Necesitas algo más?', 'type' => 'message'],
                ],
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        ChatFlow::updateOrCreate(
            ['slug' => 'printer-setup'],
            [
                'name' => 'Configurar impresora',
                'description' => 'Guía para agregar o solucionar impresoras de red.',
                'triggers' => ['impresora', 'imprimir', 'no imprime', 'agregar impresora', 'printer'],
                'steps' => [
                    ['prompt' => "¿Cuál es tu problema con la impresora?\n\n**1** — No puedo agregar una impresora\n**2** — La impresora no imprime\n**3** — Atasco de papel u otro error físico", 'type' => 'input'],
                    ['prompt' => "Para agregar una impresora de red:\n\n1. Ve a **Configuración → Dispositivos → Impresoras**\n2. Clic en **\"Agregar impresora\"**\n3. Busca por nombre (ej: IMP-PISO3-HP)\n\nSi no aparece, crea un ticket indicando tu piso y ubicación y te la configuraremos.", 'type' => 'message'],
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
        );
    }
}
