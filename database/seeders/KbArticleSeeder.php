<?php

namespace Database\Seeders;

use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds example KB articles to give the chatbot real content for RAG.
 * All articles are published + public so they show up in searches.
 */
class KbArticleSeeder extends Seeder
{
    public function run(): void
    {
        $catTI = KbCategory::firstOrCreate(
            ['slug' => 'ti'],
            ['name' => 'Tecnología de la Información', 'description' => 'Artículos técnicos de TI', 'is_active' => true, 'sort_order' => 1],
        );
        $catRRHH = KbCategory::firstOrCreate(
            ['slug' => 'rrhh'],
            ['name' => 'Recursos Humanos', 'description' => 'Procesos de RRHH', 'is_active' => true, 'sort_order' => 2],
        );
        $catSegur = KbCategory::firstOrCreate(
            ['slug' => 'seguridad'],
            ['name' => 'Seguridad', 'description' => 'Políticas de seguridad informática', 'is_active' => true, 'sort_order' => 3],
        );

        $author = User::where('email', 'admin@confipetrol.local')->first();

        $articles = [
            [
                'title' => 'Cómo conectarse al WiFi corporativo CONFIPETROL-CORP',
                'category_id' => $catTI->id,
                'body' => "La red WiFi corporativa de Confipetrol se llama **CONFIPETROL-CORP** y está disponible en todas las oficinas.\n\n## Pasos para conectarse\n\n1. Abrir la configuración de red de tu equipo\n2. Seleccionar la red **CONFIPETROL-CORP**\n3. Ingresar tu usuario de dominio (sin el @confipetrol.com) y tu contraseña de Windows\n4. Aceptar el certificado de seguridad cuando aparezca\n\n## Red para invitados\n\nPara visitantes existe la red **CONFIPETROL-GUEST** con una contraseña que cambia semanalmente. Contacta a recepción para obtenerla.\n\n## Problemas comunes\n\n- No conecta: verifica que el equipo tenga el certificado de Confipetrol instalado. Crea un ticket en TI si no lo tiene.\n- Se desconecta frecuentemente: reinicia el adaptador WiFi desde Configuración → Red → Administrar redes conocidas.\n- Velocidad lenta: acércate al access point más cercano o usa cable Ethernet en tu puesto.",
            ],
            [
                'title' => 'Configurar VPN FortiClient para trabajo remoto',
                'category_id' => $catTI->id,
                'body' => "Para acceder a recursos internos de Confipetrol desde fuera de la oficina debes usar la VPN corporativa con **FortiClient**.\n\n## Instalación\n\n1. Descarga FortiClient desde el portal de software interno\n2. Instala siguiendo el asistente\n3. Abre FortiClient → pestaña Acceso remoto\n4. Click Configurar VPN\n\n## Configuración\n\n- Nombre de conexión: Confipetrol VPN\n- Tipo de conexión: SSL-VPN\n- Gateway remoto: vpn.confipetrol.com\n- Puerto SSL: 443\n\n## Autenticación\n\nUsa las mismas credenciales que usas para entrar a tu computador. La primera vez te pedirá un código 2FA que llegará a tu correo.\n\n## Problemas\n\n- No autentica: tu contraseña puede haber expirado, cámbiala en passwords.confipetrol.com\n- Desconexiones: si tu internet es inestable, la VPN se caerá cada 10 min. Usa una conexión cableada.",
            ],
            [
                'title' => 'Resetear contraseña de Windows',
                'category_id' => $catTI->id,
                'body' => "Si olvidaste tu contraseña de Windows o expiró, tienes 2 opciones.\n\n## Opción 1: Autoservicio (preferido)\n\n1. En la pantalla de login de Windows, click ¿Olvidaste tu contraseña?\n2. Ingresa tu correo corporativo @confipetrol.com\n3. Recibirás un código por SMS al celular registrado\n4. Ingresa el código y define una nueva contraseña\n\n## Opción 2: Ticket a TI\n\nSi no tienes celular registrado o el autoservicio no funciona, crea un ticket en la categoría Cuentas y accesos.\n\n## Requisitos de la nueva contraseña\n\n- Mínimo 12 caracteres\n- Al menos una mayúscula, una minúscula, un número y un símbolo\n- No puede ser igual a las últimas 5 contraseñas\n\n## Expiración\n\nLas contraseñas expiran cada 90 días. Recibirás recordatorios 14, 7 y 1 día antes.",
            ],
            [
                'title' => 'Configurar correo corporativo en celular (iOS y Android)',
                'category_id' => $catTI->id,
                'body' => "Para recibir correos de @confipetrol.com en tu teléfono, usa Microsoft Outlook (recomendado).\n\n## iOS (iPhone)\n\n1. Descarga Microsoft Outlook de la App Store\n2. Ábrelo y Agregar cuenta de correo\n3. Ingresa: tu_usuario@confipetrol.com\n4. Te redirigirá al login de Microsoft con tu contraseña de Windows\n5. Acepta los permisos de MDM (gestión de dispositivo)\n\n## Android\n\nLos pasos son idénticos a iOS, usando Google Play Store.\n\n## Políticas obligatorias\n\nAl configurar correo corporativo en un dispositivo móvil, debes aceptar:\n- PIN o biometría obligatoria\n- Borrado remoto si el teléfono se pierde\n- Encriptación del almacenamiento",
            ],
            [
                'title' => 'Agregar una impresora de red',
                'category_id' => $catTI->id,
                'body' => "Las impresoras de red de Confipetrol siguen el formato de nombre IMP-PISO-UBICACION.\n\n## Windows\n\n1. Configuración → Dispositivos → Impresoras y escáneres\n2. Click Agregar impresora o escáner\n3. Espera 30 segundos a que aparezca la lista\n4. Selecciona la impresora deseada y click Agregar dispositivo\n\nSi no aparece, selecciona La impresora que quiero no está en la lista y usa el nombre UNC: \\\\print.confipetrol.com\\IMP-PISO3-SALA-REUNIONES\n\n## Mac\n\n1. Preferencias del sistema → Impresoras y escáneres\n2. Click + y pestaña IP\n3. Protocolo: LPD, Dirección: print.confipetrol.com, Cola: IMP-PISO3-SALA-REUNIONES\n\n## Doble cara obligatoria\n\nPor política ambiental, la impresión a doble cara es obligatoria por defecto.",
            ],
            [
                'title' => 'Política de solicitud de vacaciones',
                'category_id' => $catRRHH->id,
                'body' => "Todas las vacaciones se gestionan a través del portal de RRHH en rrhh.confipetrol.com.\n\n## Cómo solicitar\n\n1. Ingresa al portal con tu usuario corporativo\n2. Menú Mis Solicitudes → Nueva Solicitud de Vacaciones\n3. Selecciona fecha de inicio, fecha de fin (días hábiles se calculan automáticamente)\n4. Click Enviar a aprobación\n\n## Tiempos de anticipación\n\n- Menos de 5 días: 15 días de anticipación\n- Entre 5 y 10 días: 30 días de anticipación\n- Más de 10 días: 60 días de anticipación\n\n## Aprobaciones\n\nTu solicitud pasa por 2 niveles: jefe directo (3 días hábiles) y RRHH (validación de saldo).\n\n## Saldo\n\nTienes 15 días hábiles anuales acumulados desde tu fecha de aniversario. Consúltalo en Mi Perfil → Saldo de Vacaciones.",
            ],
            [
                'title' => 'Cómo descargar tu colilla de pago',
                'category_id' => $catRRHH->id,
                'body' => "La colilla de pago se genera el día 28 de cada mes y queda disponible en el portal de RRHH.\n\n## Pasos\n\n1. Ingresa a rrhh.confipetrol.com\n2. Menú Mi Nómina → Colillas de pago\n3. Selecciona el mes que necesitas\n4. Click en el icono de descarga (PDF)\n\n## Formato\n\nLa colilla incluye devengados (salario básico, horas extras, bonificaciones), deducciones (salud, pensión, retención), aportes empleador y neto a pagar.\n\n## Si no aparece\n\nLas colillas se publican máximo 3 días hábiles después del pago. Si pasados 5 días hábiles no aparece, verifica tu saldo bancario y crea un ticket en Categoría Nómina.\n\n## Años anteriores\n\nLas colillas se conservan 10 años en el portal. Para años más antiguos, solicítalas por ticket a RRHH.",
            ],
            [
                'title' => 'Reportar un correo sospechoso o phishing',
                'category_id' => $catSegur->id,
                'body' => "Si recibes un correo que parece sospechoso, **no lo borres**. Repórtalo inmediatamente.\n\n## Señales de alerta (phishing)\n\n- Remitente externo que se hace pasar por interno\n- Urgencia artificial (tu cuenta será bloqueada en 24 horas)\n- Enlaces a dominios extraños (no confipetrol.com)\n- Solicita credenciales o datos bancarios\n- Archivos adjuntos inesperados (.exe, .zip, .xlsm)\n- Errores ortográficos inusuales\n\n## Cómo reportar\n\n### Opción 1: botón Reportar phishing\n1. Abre el correo en Outlook\n2. Click Reportar → Phishing\n3. Se envía automáticamente a seguridad@confipetrol.com\n\n### Opción 2: manual\nReenvía el correo completo como adjunto a phishing@confipetrol.com con asunto SOSPECHOSO.\n\n## Qué NO hacer\n\n- No hacer click en ningún enlace\n- No descargar adjuntos\n- No responder al remitente\n- No reenviar a compañeros sin contexto\n\n## Si ya hiciste click o ingresaste datos\n\nCrea un ticket URGENTE en categoría Seguridad y llama al 01-8000-CONFIPETROL ext 9911.",
            ],
            [
                'title' => 'Política de contraseñas seguras',
                'category_id' => $catSegur->id,
                'body' => "Las contraseñas son la primera línea de defensa de tu cuenta corporativa.\n\n## Reglas obligatorias\n\n- Mínimo 12 caracteres\n- Mínimo 1 mayúscula, 1 minúscula, 1 número, 1 símbolo\n- No usar palabras del diccionario completas\n- No incluir tu nombre ni fecha de nacimiento\n- Diferente a las últimas 5 contraseñas\n- Expira cada 90 días\n\n## Técnica recomendada: frase-contraseña\n\nMejor que 'Confi2026!' es algo como 'Mi_Gato_Come_Atun@7am'. Es fácil de recordar y tarda años en ser descifrada.\n\n## Gestor de contraseñas\n\nConfipetrol provee 1Password Business para todos los empleados. Descárgalo del portal de software.\n\n## Lo que NUNCA debes hacer\n\n- Compartir tu contraseña con nadie (ni con tu jefe, ni con TI)\n- Escribirla en papel o en un .txt\n- Usar la misma contraseña en cuentas personales\n- Ingresarla en un enlace de correo (usa siempre la URL oficial)\n\n## 2FA obligatorio\n\nTodas las cuentas corporativas requieren autenticación de dos factores en mfa.confipetrol.com.",
            ],
            [
                'title' => 'Solicitar equipo nuevo o reposición de hardware',
                'category_id' => $catTI->id,
                'body' => "Para solicitar un computador nuevo, monitor, periféricos o repuestos sigue este proceso.\n\n## Equipo nuevo (contratación)\n\nEl jefe directo debe solicitar el equipo 10 días antes de la fecha de inicio del colaborador:\n1. Crea ticket en Categoría Hardware\n2. Adjunta el formato F-TI-001 Solicitud de Equipo Nuevo\n3. Indica nombre, cargo, departamento, fecha de inicio, tipo de equipo\n\n## Reposición por daño\n\n1. Crea ticket en Categoría Hardware, prioridad Alta\n2. Describe el problema con detalle\n3. Adjunta fotos si el daño es físico\n4. TI evaluará en máximo 24 horas\n\n## Periféricos\n\n- Menores (teclado, mouse, < 500.000 COP): ticket + justificación + aprobación jefe\n- Mayores (monitores, docks, audífonos profesionales): ticket + aprobación jefe + gerente de área\n\n## Catálogo estándar\n\n- Administrativo: Laptop HP EliteBook 14, 16GB RAM, 512GB SSD\n- Desarrollo: Laptop Dell Precision 15, 32GB RAM, 1TB SSD\n- Operaciones campo: Laptop rugged Panasonic Toughbook\n- Ejecutivo: MacBook Pro 14",
            ],
        ];

        foreach ($articles as $data) {
            KbArticle::updateOrCreate(
                ['slug' => Str::slug($data['title'])],
                [
                    'title' => $data['title'],
                    'body' => $data['body'],
                    'kb_category_id' => $data['category_id'],
                    'author_id' => $author?->id,
                    'status' => 'published',
                    'visibility' => 'public',
                    'published_at' => now(),
                ],
            );
        }
    }
}
