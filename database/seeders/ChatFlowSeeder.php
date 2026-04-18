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
                    ['prompt' => "**¿Qué contraseña necesitas resetear?**\n\nEscribe el número de la opción:\n\n- **1** — Windows (equipo)\n- **2** — Correo corporativo\n- **3** — Otra aplicación", 'type' => 'input'],
                    ['prompt' => "**Pasos para resetear tu contraseña de Windows:**\n\n1. Presiona **Ctrl + Alt + Supr**\n2. Selecciona **\"Cambiar contraseña\"**\n3. Ingresa tu contraseña actual\n4. Ingresa la nueva (mínimo 8 caracteres, con mayúsculas y números)\n5. Confirma la nueva contraseña\n\n¿Pudiste completar el cambio? Responde **sí** o **no**", 'type' => 'input'],
                    ['prompt' => "¡Perfecto! 🎉\n\nSi necesitas más ayuda, puedes:\n\n- Escribir **crear ticket** para hablar con un agente\n- Preguntarme sobre otro tema", 'type' => 'message'],
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
                    ['prompt' => "**Configuración de VPN de Confipetrol:**\n\n1. Abre el cliente **FortiClient** (menú inicio)\n2. Servidor: `vpn.confipetrol.com`\n3. Usuario: tu correo corporativo\n4. Contraseña: la misma de Windows\n\n¿Ya tienes **FortiClient** instalado? Responde **sí** o **no**", 'type' => 'input'],
                    ['prompt' => "Si no tienes **FortiClient** instalado, tienes dos opciones:\n\n- Descárgalo desde el portal interno de software\n- O escribe **crear ticket** y te lo instalamos remotamente\n\n¿Necesitas algo más?", 'type' => 'message'],
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
                    ['prompt' => "**¿Cuál es tu problema con la impresora?**\n\nEscribe el número de la opción:\n\n- **1** — No puedo agregar una impresora\n- **2** — La impresora no imprime\n- **3** — Atasco de papel u otro error físico", 'type' => 'input'],
                    ['prompt' => "**Pasos para agregar una impresora de red:**\n\n1. Ve a **Configuración → Dispositivos → Impresoras**\n2. Clic en **\"Agregar impresora\"**\n3. Busca por nombre (ej: `IMP-PISO3-HP`)\n4. Selecciónala y espera que se instalen los drivers\n\n---\n\nSi la impresora **no aparece** en la lista, escribe **crear ticket** indicando:\n\n- Tu piso y ubicación\n- Modelo de impresora si lo sabes\n\nY te la configuraremos.", 'type' => 'message'],
                ],
                'is_active' => true,
                'sort_order' => 3,
            ],
        );
    }
}
