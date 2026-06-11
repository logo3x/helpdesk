# E2E demos con Playwright

Tests de extremo a extremo que **graban video** del flujo completo, útiles
para demos a stakeholders sin tener que grabar manualmente con OBS/Loom.

## Setup (una vez)

```bash
npm install
npx playwright install chromium
composer install
```

Crea los usuarios de demo (idempotente):

```bash
php artisan tinker --execute "..."  # ver users abajo
```

Usuarios esperados:

| Email | Pass | Rol |
|-------|------|-----|
| demo-supervisor@confipetrol.local | demo1234 | supervisor_soporte |
| demo-final@confipetrol.local      | demo1234 | usuario_final     |

Ambos deben tener `email_verified_at` y `asl_accepted_at` seteados.

## Correr un demo

1. Levanta la app:
   ```bash
   php artisan serve --host=127.0.0.1 --port=8000
   ```

2. En otra terminal:
   ```bash
   npx playwright test tests/e2e/kb-demo.spec.cjs --headed
   ```

3. El video queda en:
   ```
   tests/e2e/videos/<nombre-test>/video.webm
   ```

   Con `--headed` además se ve el browser en pantalla mientras corre,
   así podés grabarlo en simultáneo con OBS/Game Bar si querés audio.

## Demos disponibles

### `kb-demo.spec.cjs` — demo rápido (42 segundos)
- **Fase 1:** Supervisor inicia sesión, navega a la lista de KBs, abre
  el KB "Solicitar acceso a SAP S/4HANA" recién publicado.
- **Fase 2:** Usuario final inicia sesión, va al asistente virtual,
  pregunta "¿Cómo solicito acceso a SAP?".
- **Validación:** el RAG matchea el KB con similarity 1.0 y la respuesta
  incluye el contenido del KB (`accesos.sap@confipetrol`, etc.).

El KB se crea vía `php artisan demo:seed-kb` en el `beforeAll`. El
supervisor visita el artículo ya publicado (no escribe el body en el
editor CodeMirror).

### `kb-hse-demo-slow.spec.cjs` — demo lento con "Redactar con IA" (2 min 12 s)
- **Fase 1:** Supervisor inicia sesión (pausas largas para que se vea).
- **Fase 2:** Navega a Base de Conocimiento → Crear KB.
- **Fase 3:** Abre el modal **"✨ Redactar con IA"**, escribe la
  descripción en lenguaje natural y pulsa Generar.
- **Fase 4:** El asistente rellena título + body. Scroll para mostrar
  el contenido generado. Selecciona Estado=Publicado y guarda.
- **Fase 5:** Logout → login como usuario final.
- **Fase 6:** Asistente virtual → "¿Cómo reporto un incidente HSE?" →
  el bot responde con el contenido del KB recién creado.

Requiere `DEMO_LLM_MOCK=true` en `.env` (binding del `LlmService` al
`DemoLlmService`, que retorna borradores prefabricados al instante
sin depender del rate-limit de OpenRouter).

**Cómo correrlo:**
```bash
# 1. Activar el mock del LLM en .env
echo "DEMO_LLM_MOCK=true" >> .env
php artisan config:clear

# 2. Levantar app + correr test
php artisan serve --host=127.0.0.1 --port=8000   # terminal 1
npx playwright test tests/e2e/kb-hse-demo-slow.spec.cjs --headed   # terminal 2

# 3. Restaurar al terminar
sed -i "s/^DEMO_LLM_MOCK=.*/DEMO_LLM_MOCK=false/" .env
php artisan config:clear
```

## Tips para narrar el video

- Modo `--headed` con `slowMo: 400ms` configurado en `playwright.config.cjs`
  hace que cada acción se vea bien en el video.
- Si querés más tiempo en alguna pantalla, agregá `page.waitForTimeout(N)`
  en el step correspondiente.
- Para grabar audio narrado, abrí Windows Game Bar (Win+G) y dale Start
  Recording antes de lanzar el test.
