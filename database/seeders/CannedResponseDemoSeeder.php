<?php

namespace Database\Seeders;

use App\Models\CannedResponse;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds 5 canned responses per department (25 total).
 * All are marked as shared so any agent of the department can use them.
 */
class CannedResponseDemoSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::where('email', 'admin@confipetrol.local')->first();

        $responses = [
            // ── TI ────────────────────────────────────────────────────
            ['ti-hardware', 'Ticket recibido — TI', "Hola,\n\nHemos recibido tu solicitud y ya fue asignada a un técnico de nuestro equipo. Te contactaremos en breve para coordinar la atención.\n\nSi tu caso es urgente, puedes llamar a la extensión interna de TI.\n\nGracias por tu paciencia.\n\n— Equipo de Soporte TI"],
            ['ti-cuentas-y-accesos', 'Credenciales enviadas', "Hola,\n\nYa creamos tu usuario corporativo:\n- Usuario: [CORREO]\n- Contraseña temporal: te llega por correo separado\n\nAl primer login te pedirá cambiarla. Asegúrate de cumplir la política de contraseñas (mínimo 12 caracteres, mayúscula, número, símbolo).\n\nTambién configuramos acceso a:\n- Microsoft 365 (Teams, correo, OneDrive)\n- VPN corporativa\n- Helpdesk\n\nSi algún acceso no funciona, reabre este ticket.\n\n— Soporte TI"],
            ['ti-redes-y-wifi', 'Solución aplicada — red', "Hola,\n\nHemos identificado y corregido la falla de red. Por favor valida desde tu equipo:\n\n1. Internet funciona: abre google.com\n2. Recursos internos: abre el portal interno\n3. Impresora: prueba una impresión\n\nSi algo sigue sin funcionar, házmelo saber en este mismo ticket para no perder el contexto.\n\nSi todo está bien, marcaré el ticket como resuelto en 24 horas.\n\nGracias."],
            ['ti-hardware', 'Esperando proveedor', "Hola,\n\nTu solicitud requiere la llegada de un repuesto/equipo del proveedor. El tiempo estimado de entrega es:\n\n- Proveedor: [NOMBRE]\n- Tiempo estimado: [5-7 días hábiles]\n- Orden de compra: [OC-XXXX]\n\nTe notificaré en cuanto llegue el material para programar la instalación.\n\nGracias por la paciencia.\n\n— Soporte TI"],
            ['ti-software', 'Software instalado — validar', "Hola,\n\nYa instalamos el software solicitado en tu equipo. Por favor:\n\n1. Reinicia el computador\n2. Busca el programa en el menú de inicio\n3. Ábrelo y verifica que funciona correctamente\n4. Si requiere licencia, ya está activada\n\nSi el software no abre o da error, comenta aquí y vamos a revisar. De lo contrario cerramos el ticket en 48 horas.\n\nGracias."],

            // ── RRHH ──────────────────────────────────────────────────
            ['rrhh-nomina', 'Certificado en proceso', "Hola,\n\nRecibimos tu solicitud de certificado laboral. Los tiempos de entrega son:\n\n- Certificado simple: 1 día hábil\n- Con salario: 2 días hábiles\n- En inglés: 3-5 días hábiles\n\nUna vez firmado, llegará a tu correo en PDF. Si necesitas copia impresa, pásala a recoger en RRHH (piso 2).\n\nGracias.\n\n— Recursos Humanos"],
            ['rrhh-vacaciones', 'Vacaciones aprobadas', "Hola,\n\nTu solicitud de vacaciones fue aprobada.\n\n- Fechas: [INICIO] al [FIN]\n- Días hábiles: [N]\n- Días restantes tras estas vacaciones: [N]\n\nRecuerda:\n- Coordinar entrega de pendientes con tu reemplazo\n- Configurar respuesta automática en correo y Teams\n- Avisar a clientes/contactos externos\n\n¡Que disfrutes!\n\n— Recursos Humanos"],
            ['rrhh-nomina', 'Ajuste de nómina realizado', "Hola,\n\nYa realizamos el ajuste que solicitaste. Podrás ver el cambio reflejado en:\n\n- [ ] Tu próximo recibo de nómina\n- [ ] Recibo actual (reemitido)\n- [ ] Retroactivo desde [FECHA]\n\nValor del ajuste: $[MONTO]\n\nAdjunto el detalle del cálculo.\n\nSi algo no cuadra, reabre este ticket y lo revisamos.\n\n— Nómina RRHH"],
            ['rrhh-contratos', 'Documentación pendiente', "Hola,\n\nPara continuar con tu trámite necesitamos los siguientes documentos:\n\n- [ ] Copia de cédula\n- [ ] Soporte EPS\n- [ ] Certificación bancaria\n- [ ] Hoja de vida actualizada\n- [ ] Otros: [listar]\n\nPor favor súbelos al portal en los próximos 3 días hábiles. Sin los documentos completos no podemos procesar.\n\nSi tienes alguna duda sobre qué incluir, responde este ticket.\n\n— RRHH"],
            ['rrhh-afiliaciones', 'Afiliación confirmada', "Hola,\n\nTu afiliación quedó formalizada:\n\n- Entidad: [NOMBRE EPS/AFP]\n- Fecha de inicio: [FECHA]\n- Número de afiliado: [NÚMERO]\n\nEn las próximas 48 horas podrás usar los servicios de la entidad. Si necesitas atención médica/consulta urgente antes, avisamos.\n\nAdjunto el comprobante oficial.\n\n— RRHH"],

            // ── Compras ───────────────────────────────────────────────
            ['compras-solicitud-de-compra', 'Cotización solicitada a proveedores', "Hola,\n\nTu solicitud ya está en cotización con nuestros proveedores aprobados. El tiempo estimado para recibir las cotizaciones es de 3-5 días hábiles.\n\nProveedores contactados:\n- [Proveedor 1]\n- [Proveedor 2]\n- [Proveedor 3]\n\nUna vez tengamos las cotizaciones, te enviamos la matriz de comparación para tu aprobación.\n\n— Compras"],
            ['compras-cotizaciones', 'Cotizaciones listas', "Hola,\n\nYa recibimos las cotizaciones. Aquí el resumen:\n\n| Proveedor | Precio | Entrega | Garantía |\n|---|---|---|---|\n| [P1] | $[X] | [Y días] | [Z] |\n| [P2] | $[X] | [Y días] | [Z] |\n| [P3] | $[X] | [Y días] | [Z] |\n\nRecomendación de Compras: [PROVEEDOR] por [razón].\n\nPor favor confirma o elige otro. Una vez aprobado, emitimos la orden de compra.\n\n— Compras"],
            ['compras-solicitud-de-compra', 'Orden de compra emitida', "Hola,\n\nEmitimos la orden de compra:\n\n- Proveedor: [NOMBRE]\n- Número de OC: [OC-XXXXX]\n- Valor total: $[MONTO]\n- Fecha estimada de entrega: [FECHA]\n\nEl proveedor tiene la OC y comenzará el proceso. En cuanto tengamos fecha confirmada de entrega te avisamos.\n\n— Compras"],
            ['compras-proveedores', 'Proveedor aprobado', "Hola,\n\nEl proveedor [NOMBRE] fue aprobado y ya está activo en el sistema.\n\nDatos de contacto:\n- NIT: [NIT]\n- Contacto: [NOMBRE]\n- Correo: [correo]\n- Teléfono: [tel]\n- Categorías: [lista]\n\nYa puedes solicitar cotizaciones con ellos.\n\n— Compras"],
            ['compras-solicitud-de-compra', 'Solicitud rechazada', "Hola,\n\nLamentablemente la solicitud no puede ser aprobada por el siguiente motivo:\n\n[MOTIVO DE RECHAZO]\n\nRecomendaciones:\n- Ajustar el presupuesto\n- Esperar al siguiente mes fiscal\n- Re-justificar con mayor detalle\n- Reducir alcance\n\nSi quieres replantear la solicitud, crea un nuevo ticket con los ajustes necesarios.\n\nQuedo pendiente.\n\n— Compras"],

            // ── Mantenimiento ─────────────────────────────────────────
            ['mantenimiento-mantenimiento-locativo', 'Técnico en ruta', "Hola,\n\nTu reporte fue asignado a un técnico. Se estima su llegada en [TIEMPO].\n\nTécnico asignado: [NOMBRE]\n\nMientras llega:\n- Si es fuga: pon recipiente y aleja equipos electrónicos\n- Si es eléctrico: no toques el equipo, baja el breaker si sabes cuál es\n- Si es temperatura: mantén puertas cerradas\n\n— Mantenimiento"],
            ['mantenimiento-equipos', 'Reparación completada', "Hola,\n\nLa reparación fue completada. Resumen:\n\n- Equipo: [TAG]\n- Falla encontrada: [descripción]\n- Acción tomada: [qué se hizo]\n- Repuestos cambiados: [lista]\n- Tiempo total: [HH:MM]\n\nPor favor:\n1. Verifica que el equipo funciona correctamente\n2. Si hay anomalía, reabre este ticket\n3. Si todo bien, en 24 horas cerramos\n\n— Mantenimiento"],
            ['mantenimiento-mantenimiento-locativo', 'Programación del trabajo', "Hola,\n\nTu solicitud requiere coordinación previa. Se realizará:\n\n- Fecha: [FECHA]\n- Hora: [HORA]\n- Duración estimada: [HH:MM]\n- Impacto: [¿requiere desalojar área?]\n\nPor favor:\n- Libera el área con anticipación\n- Guarda objetos delicados/frágiles\n- Avisa a tu equipo del piso\n\nSi necesitas reprogramar, avisa con mínimo 1 día.\n\n— Mantenimiento"],
            ['mantenimiento-equipos', 'Se requiere repuesto externo', "Hola,\n\nLa reparación requiere un repuesto que debemos pedir al proveedor.\n\n- Repuesto: [NOMBRE/MODELO]\n- Tiempo estimado de llegada: [5-7 días hábiles]\n- Proveedor: [NOMBRE]\n\nMientras tanto, dejamos el equipo en modo seguro y buscaremos una solución temporal si es crítico.\n\nTe notificaré en cuanto llegue el repuesto.\n\n— Mantenimiento"],
            ['mantenimiento-servicios-generales', 'Mantenimiento preventivo realizado', "Hola,\n\nCompletamos el mantenimiento preventivo programado.\n\n- Equipo/Área: [NOMBRE]\n- Acciones realizadas: [listar]\n- Próximo mantenimiento: [FECHA]\n\nEl equipo quedó operativo y revisado. Si notas algo anormal, reabre este ticket.\n\nGracias por permitirnos trabajar con anticipación — así se extiende la vida útil de los equipos.\n\n— Mantenimiento"],

            // ── Operaciones ───────────────────────────────────────────
            ['operaciones-soporte-operativo', 'Turno de trabajo coordinado', "Hola,\n\nYa coordinamos la atención de tu reporte con el turno operativo.\n\n- Turno asignado: [A/B/C]\n- Supervisor responsable: [NOMBRE]\n- Inicio de atención: [FECHA HORA]\n- Equipo afectado: [TAG]\n\nMantén el equipo en modo seguro hasta que llegue el técnico. Si hay riesgo crítico, evacúa y llama a seguridad industrial.\n\n— Operaciones"],
            ['operaciones-documentacion', 'PON actualizado y publicado', "Hola,\n\nEl procedimiento operativo [CÓDIGO] fue actualizado a la versión [NUEVA VERSIÓN].\n\nCambios principales:\n- [Cambio 1]\n- [Cambio 2]\n\nDisponible en:\nSharePoint → Operaciones → [Planta] → Procedimientos\n\nAcciones requeridas:\n- [ ] Todo operador lea la nueva versión en los próximos 3 días\n- [ ] Cambios aplicables desde [FECHA EFECTIVA]\n- [ ] Se realizará capacitación el [FECHA] a las [HORA]\n\nLa versión anterior queda en histórico.\n\n— Operaciones"],
            ['operaciones-soporte-operativo', 'Parada resuelta — reinicio', "Hola,\n\nLa parada no programada fue resuelta. Resumen:\n\n- Causa raíz: [DESCRIPCIÓN]\n- Acciones correctivas: [lista]\n- Duración de la parada: [HH:MM]\n- Toneladas no producidas: [N]\n- Reinicio: [FECHA HORA]\n\nEl equipo está operando con normalidad. Se programó inspección adicional en:\n- [FECHA]: [qué revisar]\n- [FECHA]: [qué revisar]\n\nReporte de RCA (análisis de causa raíz) disponible en 72 horas.\n\n— Operaciones"],
            ['operaciones-soporte-operativo', 'Refuerzo de personal aprobado', "Hola,\n\nEl refuerzo de personal fue aprobado.\n\n- Personas asignadas: [N]\n- Perfil: [DESCRIPCIÓN]\n- Fechas de apoyo: [INICIO] al [FIN]\n- Turno: [A/B/C]\n- Centro de costo: [CC]\n\nEl personal reporta con el supervisor [NOMBRE] a las [HORA] del día [FECHA].\n\nRecuerda incluirlos en la bitácora y briefing de seguridad.\n\n— Operaciones / RRHH"],
            ['operaciones-documentacion', 'Reporte diario recibido', "Hola,\n\nRecibido el reporte diario del día [FECHA] turno [A/B/C].\n\nEstado general:\n- Cumplimiento de producción: [%]\n- Paradas: [HH:MM]\n- Incidentes: [N]\n- Observaciones: [breve]\n\nSi hay novedades que requieren seguimiento adicional, se crearán tickets derivados.\n\nContinúa la buena operación.\n\n— Operaciones"],
        ];

        foreach ($responses as $sort => [$catSlug, $title, $body]) {
            $category = Category::where('slug', $catSlug)->first();

            CannedResponse::updateOrCreate(
                ['title' => $title],
                [
                    'body' => $body,
                    'category_id' => $category?->id,
                    'created_by_id' => $creator?->id,
                    'is_shared' => true,
                    'is_active' => true,
                    'sort_order' => $sort,
                ],
            );
        }
    }
}
