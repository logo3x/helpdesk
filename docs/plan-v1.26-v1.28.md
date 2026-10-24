# Plan de trabajo — v1.26 a v1.28

**Fecha:** 2026-10-24
**Base:** HEAD `865e4af` (Microsoft Graph mail)
**Origen:** 11 ítems revisados tras testeo en producción (Luis Oviedo)

Lista agrupada en **4 tandas** por dependencia funcional y riesgo. Dentro de cada tanda se trabaja en el orden listado; cada ítem termina con un commit propio con tests.

---

## 🧭 Resumen ejecutivo

| Tanda | Versión | Alcance | Ítems | Esfuerzo relativo |
|-------|---------|---------|-------|-------------------|
| **1** | v1.26 | Backend/datos simple | 3 | S |
| **2** | v1.27 | Módulo Mantenimientos | 3 | M |
| **3** | v1.28 | UX dashboards y portal | 4 | M-L |
| **4** | — | Material informativo (sin código) | 1 | S (contenido) |

Total: **11 ítems**.

---

## 🟢 Tanda 1 — Backend/datos simple (v1.26)

Cambios pequeños y aislados que desbloquean reportes y mejoran la administración.

### 1.1 Encuestas identificables por origen (ticket vs mantenimiento)

**Estado hoy:** existen dos tablas separadas: `satisfaction_surveys` (relacionada a `tickets`) y `maintenance_surveys` (relacionada a `assets` + `scheduled_maintenances` implícitamente). Al listar/reportar encuestas no hay forma unificada de ver el origen; cada reporte filtra por su propia tabla.

**Qué hace falta:**
- Agregar columna `source_type` enum (`ticket` | `maintenance`) a **ambas** tablas, con default según corresponda, para que un reporte unificado pueda hacer `UNION ALL` y filtrar por origen.
- Alternativa simpler y recomendada: dejar las tablas separadas, pero crear una **vista Eloquent `UnifiedSurveyView`** que combine ambas y exponga `source_type`, `rating`, `comment`, `responded_at`, `related_label` (número de ticket o TAG de activo).
- Nuevo reporte `/soporte/surveys` con filtro **Origen** (todas / ticket / mantenimiento) y columna badge.
- Si prefieren un modelo único:
  - Crear tabla `surveys` nueva + migración de datos.
  - Deprecar `satisfaction_surveys` y `maintenance_surveys` (mantener como vistas o eliminarlas con back-fill).

**Archivos tocados:**
- `app/Models/SatisfactionSurvey.php`, `app/Models/MaintenanceSurvey.php`
- Nuevo: `app/Services/UnifiedSurveyReportService.php`
- Nuevo: `app/Filament/Soporte/Pages/SurveysReport.php` + vista Blade
- Nueva migración `add_source_type_to_*_surveys`

**Tests:** feature test que lista encuestas y filtra por origen; verifica que el badge en Blade muestra el label correcto.

---

### 1.2 Categorías con `parent_id` (subcategorías de 1 nivel)

**Estado hoy:** `categories` ya tiene `department_id`, `name`, `slug`, `icon`, `is_active`, `sort_order` pero **no** `parent_id`. El modelo KB (`kb_categories`) sí lo tiene — se puede replicar el patrón.

**Qué hace falta:**
- Migración `add_parent_id_to_categories` con `foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete()` + índice.
- `Category` model: agregar `parent()` BelongsTo y `children()` HasMany + scope `roots()`.
- `CategoryForm` (admin): `Select::make('parent_id')` con options filtrados por mismo `department_id`, hidden si ya tiene hijos (anti-ciclo), y validación de solo 2 niveles (si `parent_id` != null → no puede tener hijos).
- `CategoriesTable` admin: columna "Padre" + ordenar por `parent_id, sort_order`.
- Portal `CreateTicket`: Select de categoría agrupado por padre (opcionales de Flux `<flux:select.optgroup>`).

**Reglas de negocio:**
- Solo 2 niveles (padre → hija). Validar en form y en migración (opcional).
- La categoría hija hereda el `department_id` del padre.
- Al desactivar un padre, no se desactivan hijos automáticamente, pero se avisa.

**Tests:** verifica cadena padre → hija, validación de 2 niveles, hereda departamento, scope `roots()`.

---

### 1.3 Icon picker visual para categorías

**Estado hoy:** [CategoryForm.php:47](app/Filament/Resources/Categories/Schemas/CategoryForm.php#L47) usa `TextInput` libre esperando que el usuario escriba `heroicon-o-computer-desktop`. Nadie recuerda los nombres → quedan vacíos o mal escritos → en la tabla no se renderiza.

**Qué hace falta:**
- Instalar `guava/filament-icon-picker` (ya hay otros `guava/*` instalados — Calendar).
- Reemplazar `TextInput('icon')` por `IconPicker::make('icon')` con sets `['heroicons']` y `columns` 4.
- Verificar que la tabla admin usa `IconColumn` o renderiza con `@svg(...)` correctamente cuando el valor existe.
- Fallback por si no se instala el plugin: `Select` con lista curada de ~40 Heroicons comunes (document-text, computer-desktop, wrench-screwdriver, ticket, etc.).

**Tests:** feature test que guarda una categoría con icono y verifica que se renderiza en la tabla.

**Commit sugerido:** `feat(categorias): icon picker visual con guava/filament-icon-picker`

---

## 🟡 Tanda 2 — Módulo Mantenimientos (v1.27)

Todos los cambios al módulo `ScheduledMaintenance` se agrupan para no tocar los mismos archivos en commits distintos.

### 2.1 Adjunto al completar mantenimiento

**Estado hoy:** `ScheduledMaintenanceForm` permite marcar status `cumplido` con `observations` libre. No se puede adjuntar foto, acta firmada ni PDF de evidencia.

**Qué hace falta:**
- Agregar relación Spatie MediaLibrary a `ScheduledMaintenance` con collection `'evidence'` (ya hay precedente en KbArticle y Asset).
- En `ScheduledMaintenanceForm`, cuando `status === 'cumplido'`:
  - `SpatieMediaLibraryFileUpload::make('evidence')` → multiple, accept `pdf, image/*`, max 10MB cada uno, hasta 5 archivos.
  - Required cuando `status === 'cumplido'` (regla condicional).
- `ScheduledMaintenanceObserver` → al pasar a Cumplido, exigir al menos 1 adjunto.
- Página de detalle: galería compacta con thumbnails + descarga.
- Reporte PDF del informe incluye tabla con cantidad de adjuntos por mantenimiento.

**Tests:** feature test que falla si se intenta cumplir sin adjunto, y pasa cuando se incluye uno.

---

### 2.2 Masivo: cascada gerencia → proyectos + quitar zona y departamento

**Estado hoy:** [ScheduledMaintenanceForm.php:127-193](app/Filament/Soporte/Resources/ScheduledMaintenances/Schemas/ScheduledMaintenanceForm.php#L127) tiene 5 filtros bulk independientes: Gerencia, Campo, Zona, Proyecto, Departamento. No se cascada ninguno.

**Qué hace falta:**
- **Quitar** del formulario: `bulk_filter_location_zone` y `bulk_filter_department_id`.
- **Cascadear** `bulk_filter_project_id`: sus `options()` deben filtrar por `bulk_filter_management_area` cuando esté seleccionada.
  - Como `projects` no tiene columna `management_area`, derivarlo via `Asset`: proyectos cuyos activos tienen esa gerencia.
  - Query: `Project::whereHas('assets', fn ($q) => $q->when($managementArea, fn ($qq) => $qq->where('management_area', $managementArea)))`.
- Al limpiar gerencia, el select de proyecto se abre de nuevo.
- Mantener orden visual: Tipo → Gerencia → Campo → Proyecto.

**Tests:** test de la query que filtra proyectos por gerencia, y feature test del form que verifica reactividad.

---

### 2.3 Reporte de mantenimientos con gráficos y filtros avanzados

**Estado hoy:** [MaintenancesReport.php](app/Filament/Soporte/Pages/MaintenancesReport.php) solo tiene filtro de ventana (`$window` días). Hay gráfico de torta por estado (en v1.21-ish).

**Qué hace falta:**
- Agregar filtros al blade (bindeados a propiedades Livewire `#[Url]`):
  - `managementArea` (select)
  - `projectId` (select cascadeado por gerencia)
  - `locationZone` (select)
  - `fieldName` (select)
  - `agentId` (select de usuarios con rol técnico/agente)
  - `contractCode` → ¿tenemos "contrato"? Mapear a `project.code`. Si "contrato" es otra entidad, aclarar antes.
- Nuevos widgets/gráficos:
  - **Barras**: cumplimiento (%) por agente.
  - **Torta**: mantenimientos por gerencia.
  - **Barras apiladas**: cumplidos vs no_cumplidos por mes.
  - **Línea**: tendencia de progreso promedio en el tiempo.
- KPIs ajustados: % cumplimiento, cantidad por estado, promedio progreso, atrasados (`scheduled_at < today && status = pendiente`).
- Export PDF debe reflejar los filtros aplicados en el encabezado.

**Archivos tocados:**
- `app/Filament/Soporte/Pages/MaintenancesReport.php`
- `resources/views/filament/soporte/pages/maintenances-report.blade.php`
- `resources/views/pdfs/maintenances-report.blade.php`
- Posible nuevo `MaintenancesService` para extraer lógica de métricas.

**Tests:** feature test del controlador con cada filtro, test del service que calcula las métricas.

---

## 🔵 Tanda 3 — UX dashboards y portal (v1.28)

### 3.1 Card "SLA compliance por categoría" en dashboard admin

**Estado hoy:** `AdminStatsWidget` ya muestra stats globales. El SLA compliance global aparece en el reporte SLA pero no está discriminado por categoría ni departamento en el dashboard.

**Qué hace falta:**
- Nuevo widget `App\Filament\Widgets\SlaComplianceByCategoryWidget` (TableWidget o StatsOverviewWidget).
- Query: para cada `category` activa:
  - Total tickets resueltos en últimos 30 días.
  - Breached: `resolution_breached = true`.
  - Compliance: `(total - breached) / total * 100`.
- Agrupar por departamento (sección colapsable o color por depto).
- Semáforo: ≥90% verde, ≥70% ámbar, <70% rojo.
- Visible solo super_admin/admin.
- Opción adicional: ordenable, clickable que lleve al reporte SLA con filtro de categoría pre-aplicado.

**Nota:** este widget depende de T1.2 (categorías con parent_id). Si una categoría tiene hija, se agrupa colapsable con hijas abajo.

**Tests:** test del cálculo de compliance por categoría + canView con rol.

---

### 3.2 Portal: mostrar departamento al elegir categoría en crear ticket

**Estado hoy:** [CreateTicket.php](app/Livewire/Portal/CreateTicket.php) tiene `$category_id` pero no muestra a qué depto pertenece. El usuario no sabe quién lo atenderá.

**Qué hace falta:**
- En el blade del CreateTicket, debajo del select de categoría agregar un `<flux:callout>` reactivo que muestre:
  - "Este ticket llegará al equipo de **{Departamento}**"
  - Con el ícono/color del departamento.
- Computed property `getSelectedDepartmentProperty()` que devuelve el `Department` del `Category::find($this->category_id)`.
- Opcional: también mostrar el icono de la categoría (si se cargó bien con T1.3).

**Tests:** test Livewire que setea category_id y verifica que el callout aparece con el nombre del depto.

---

### 3.3 Inventario: botón "Siguiente ítem" respetando el filtro

**Estado hoy:** Al editar un activo (`/admin/assets/{id}/edit`) no hay forma de ir al siguiente activo de la lista filtrada; hay que volver a `/admin/assets?...` y clickear.

**Qué hace falta:**
- En `EditAsset` y `AssetLifecycle`:
  - Header actions "← Anterior" y "Siguiente →".
  - Se busca el siguiente ID basado en los **IDs cacheados en sesión** al salir del `ListAssets`.
- Implementación:
  - `ListAssets::getTableQuery()` → después de obtener la paginación, guardar `session(['assets_filtered_ids' => $ids])`.
  - En `EditAsset::getHeaderActions()`:
    - `$ids = session('assets_filtered_ids', [])`
    - Buscar `array_search($record->id, $ids)` → `$next = $ids[$index + 1] ?? null`.
    - Si existe, botón `Siguiente` con url a `edit` del siguiente.
    - Mostrar posición "3 / 47" como título.
- Si el usuario entra directo por URL sin pasar por la lista, los botones se ocultan.

**Tests:** test que al editar un asset dentro de una lista filtrada, el botón "Siguiente" redirige al siguiente ID correcto; test que si no hay sesión cacheada no aparece el botón.

---

### 3.4 Portal: velocidad + home=asistente + animaciones

**Estado hoy:** `/portal` carga `Portal\Dashboard` (saludo + stats + accesos rápidos + últimos tickets). El asistente está en `/portal/chatbot`. No hay `wire:navigate` consistente ni animaciones de entrada.

**Qué hace falta:**

**(a) Home = Asistente**
- Opción recomendada: **el dashboard sigue existiendo** pero el asistente se vuelve el bloque principal arriba (ya existe el widget de asistente del v1.25). Alternativa dura: cambiar la ruta `portal.home` para que sea `Chatbot` directamente, moviendo dashboard a `/portal/dashboard`.
- Confirmar con Luis: ¿`/portal` = asistente puro, o `/portal` = asistente arriba + dashboard reducido abajo?

**(b) Velocidad de navegación**
- Verificar que todos los links del navbar portal usan `wire:navigate` (ya lo hacen, revisar).
- Convertir las imágenes de avatar/logo a `loading="lazy"` donde aplique.
- Precargar links con `wire:navigate.hover` en el navbar.
- Opcional: habilitar Livewire `persist` para `NotificationsBell` entre navegaciones.

**(c) Animaciones**
- Transiciones de entrada con Alpine.js `x-transition` en los cards principales.
- Mensajes del chatbot con animación de "aparecer" (slide-up + fade-in).
- Botón "Crear ticket" con hover de scale sutil (`hover:scale-105 transition`).
- Toast/notificación bell con animación `pulse` cuando llega una nueva notificación.
- Librería recomendada: Alpine Transitions (ya está instalado) + Tailwind `@tailwindcss/forms`. No introducir GSAP/Framer para no inflar bundle.

**Archivos tocados:**
- `routes/web.php` (si cambia home)
- `resources/views/layouts/portal.blade.php`
- `resources/views/livewire/portal/chatbot.blade.php`
- `resources/views/livewire/portal/dashboard.blade.php`

**Tests:** feature test de la nueva ruta home + pest browser smoke test si está habilitado.

---

## 📄 Tanda 4 — Material informativo (paralelo, sin código)

### 4.1 Presentación informativa para otras áreas (Compras, Talento Humano)

**Estado hoy:** no hay material oficial para socializar el uso del helpdesk a áreas no-TI. Compras y Talento Humano no saben cómo pedir tickets, qué es el asistente IA, qué pueden hacer sus supervisores/agentes, cómo crear KB.

**Qué se entrega:**
- **PDF detallado** (`docs/manual-usuarios-areas.pdf`), ~15–20 páginas:
  1. Qué es el Helpdesk Confipetrol y para qué sirve.
  2. Cómo acceder (SSO Azure AD / login manual).
  3. Flujo del usuario final: crear ticket, consultar estado, encuesta.
  4. Asistente IA: cómo preguntar, cómo escalar a ticket, limitaciones.
  5. Roles por área:
     - **Supervisor**: gestiona equipo, aprueba KB, ve reportes del depto.
     - **Agente**: toma tickets, responde, transfiere.
  6. Centro de ayuda (KB): cómo buscar, cómo marcar útil.
  7. Para supervisores: cómo crear/publicar artículos KB.
  8. Buenas prácticas (asunto claro, descripción detallada, adjuntos).
  9. FAQ y contactos.
- **Presentación de referencia** (`docs/presentacion-helpdesk.pptx` o Canva link), ~12–15 slides, formato ejecutivo para reunión con cada área (30 min).

**Responsables:**
- Luis + redacción con asistencia de Claude en Markdown → export a PDF (`php artisan` + dompdf o Pandoc) y a PPTX.
- Capturas de pantalla reales del sistema por cada área con usuarios demo.

**Entrega:** 2 archivos subidos a `docs/`. No requiere versión de app.

**Fuera de alcance:** la ejecución de la reunión / grabación de la capacitación.

---

## 📊 Priorización sugerida

| Prioridad | Tanda | Razón |
|-----------|-------|-------|
| 🔴 Alta | T1 | Bloqueantes para reportes y jerarquía de categorías |
| 🟡 Media | T2 | Pedido explícito del equipo de mantenimientos (ya en operación) |
| 🟢 Baja-Media | T3 | Mejora de UX, no bloquea pero suma mucho valor percibido |
| ⚪ Paralela | T4 | Puede avanzar en simultáneo con T1-T3 (es contenido) |

---

## 🧪 Reglas de ejecución

- **Un ítem = un commit**, con tests Pest que cubran el cambio.
- Antes de cerrar la tanda: `php artisan test --compact` sin regresiones.
- Antes de push: `vendor/bin/pint --dirty --format agent`.
- Convención de mensajes:
  - `feat(area): descripción corta (#X Tanda Y, vA.B.C)`
  - `fix(area): descripción corta (#X Tanda Y, vA.B.C)`

---

## 🙋 Preguntas abiertas para Luis antes de arrancar

1. **T1.1 encuestas**: ¿preferís 2 tablas + vista unificada (menos invasivo) o migrar a tabla única `surveys` (más limpio pero migración de datos)?
2. **T2.3 "contrato"**: ¿contrato = `project.code` o es un campo nuevo que falta?
3. **T3.4 home del portal**: ¿asistente puro o asistente arriba + dashboard reducido abajo?
4. **T3.3 siguiente ítem**: ¿cache en sesión está bien o preferís query params (URL compartible)?
5. **T4 material informativo**: ¿lo redactamos juntos en una sesión aparte o preparo un draft completo y revisás?

---

*Documento generado 2026-10-24. Fuente: lista de 11 ítems recibida de Luis Oviedo tras testeo en producción.*
