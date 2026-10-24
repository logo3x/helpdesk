# Templates — Flujo Git-first con Claude multi-dispositivo

Templates reutilizables para que cualquier proyecto nuevo use el mismo flujo de trabajo que el helpdesk: planificación en el repo, handoff entre dispositivos (PC/web/móvil) sin perder contexto.

## Qué contiene

| Archivo | Dónde copiarlo | Para qué |
|---------|----------------|----------|
| [`CLAUDE.md.template`](CLAUDE.md.template) | Raíz del proyecto nuevo como `CLAUDE.md` | Convenciones del proyecto que Claude lee en cada sesión |
| [`pendientes.md.template`](pendientes.md.template) | `docs/pendientes.md` del proyecto nuevo | Backlog priorizado por tandas |
| [`plan-version.md.template`](plan-version.md.template) | `docs/plan-vX.Y.md` del proyecto nuevo | Un plan por iteración/versión |

## Cómo usarlos

### 1. En un proyecto nuevo o existente

```bash
# Desde el proyecto nuevo (ej: C:\wamp64\www\legalweb)
cd C:\wamp64\www\legalweb

# Clonar los templates del helpdesk
curl -o CLAUDE.md https://raw.githubusercontent.com/logo3x/helpdesk/main/docs/templates/CLAUDE.md.template
mkdir -p docs
curl -o docs/pendientes.md https://raw.githubusercontent.com/logo3x/helpdesk/main/docs/templates/pendientes.md.template
curl -o docs/plan-v1.0.md https://raw.githubusercontent.com/logo3x/helpdesk/main/docs/templates/plan-version.md.template

# Editar los placeholders {{PROYECTO}}, {{STACK}}, etc.
```

### 2. Reemplazar los placeholders

Los templates tienen marcadores entre `{{llaves dobles}}` que debés reemplazar:

- `{{PROYECTO}}` → nombre del proyecto (ej: "Legalweb Confipetrol")
- `{{STACK}}` → stack técnico (ej: "Laravel 13 + Livewire 4 + Flux 2")
- `{{DOMINIO}}` → dominio del negocio (ej: "gestión legal", "inspecciones")
- `{{VERSION}}` → versión actual (ej: "v1.0")
- `{{FECHA}}` → fecha de hoy

### 3. Commit inicial

```bash
git add CLAUDE.md docs/
git commit -m "docs: setup flujo Git-first con templates reutilizables"
git push
```

## Flujo de trabajo esperado

```
┌─────────────────┐     push      ┌──────────────┐     pull     ┌─────────────┐
│  Claude Code    │ ────────────> │   GitHub     │ ───────────> │   PC local  │
│  (web/móvil)    │               │   (origin)   │              │  (pruebas)  │
└─────────────────┘               └──────────────┘              └─────────────┘
         ▲                              ▲                              │
         │                              │                              │
         └──────────── pull ────────────┴────────── push ──────────────┘
```

### Reglas del flujo

1. **El repo es la fuente de verdad.** No el chat de una sesión. Todo lo decidido queda en `docs/pendientes.md` o `docs/plan-vX.Y.md`.
2. **Un ítem = un commit.** Con tests cuando toca código.
3. **Antes de arrancar en otro dispositivo:** `git pull origin main` y leer el plan vigente.
4. **Al terminar la sesión:** push a `main` (o PR si trabajás con ramas).
5. **Memoria compartida entre agentes = los `.md`.** La memoria privada de Claude no viaja entre dispositivos.

## Variantes

- **Proyecto Laravel**: usar `CLAUDE.md.template` tal cual (incluye convenciones Laravel/Pest/Pint).
- **Proyecto Node/React**: borrar sección "Laravel-specific" y agregar la equivalente de tu stack.
- **Proyecto sin tests**: borrar sección "Testing" o reemplazarla por "Validación manual".

## Otros proyectos de Luis que podrían usar este flujo

Según el contexto del workspace:
- `C:\wamp64\www\legalweb`
- `C:\wamp64\www\rueda_proyectos`
- `C:\wamp64\www\Inspeccion26`

Para cada uno: copiar los 3 archivos, editar placeholders, commit inicial.

---

*Templates mantenidos en el repo [logo3x/helpdesk](https://github.com/logo3x/helpdesk/tree/main/docs/templates).*
