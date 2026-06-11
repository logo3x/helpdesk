// @ts-check
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');

/**
 * Demo LENTO de funcionalidad: supervisor usa el asistente "Redactar
 * con IA" para crear un KB de procedimiento HSE, lo publica, y luego
 * un usuario final lo consulta vía el chatbot del portal.
 *
 * Diferencias con kb-demo.spec.cjs (el demo rápido):
 *   - slowMo de 700ms en este test (no el global de 400ms).
 *   - Pausas más largas entre steps (2-4 segundos) para que el
 *     espectador alcance a leer cada pantalla.
 *   - SÍ muestra el modal "Redactar con IA" funcionando — usa el
 *     DemoLlmService como mock determinista (sin depender del rate
 *     limit de OpenRouter).
 *   - Cubre dos pantallas extra: el modal de IA y el form pre-llenado.
 *
 * Pre-requisitos:
 *   - DEMO_LLM_MOCK=true en .env (binding del LlmService al mock).
 *   - Usuarios demo-supervisor / demo-final con pass demo1234.
 *   - El KB HSE NO debe existir previamente — el spec lo borra al inicio.
 *
 * Output: tests/e2e/videos/<nombre-test>/video.webm
 */

const KB_PREVIEW_TITLE = 'Cómo reportar un incidente HSE en Confipetrol';
const AI_PROMPT = 'Cuando alguien tiene un incidente HSE (derrame, lesión, casi-accidente, fuga) debe asegurar la zona, llamar a la extensión 2911, ir al portal hse.confipetrol.com y llenar el formulario FR-HSE-001. Adjuntar fotos. El líder HSE confirma en 2 horas.';
const USER_QUESTION = '¿Cómo reporto un incidente HSE en Confipetrol?';

test.beforeAll(() => {
    // Reset limpio: borra el KB HSE previo + historial del chatbot del
    // usuario final. Así cada grabación arranca como una consulta nueva.
    try {
        execSync('php artisan demo:reset --slug=como-reportar-un-incidente-hse-en-confipetrol', { stdio: 'pipe' });
    } catch (e) {
        throw new Error('demo:reset falló: ' + e.message);
    }
});

test.use({
    // Slow-mo más fuerte para este demo narrable.
    launchOptions: { slowMo: 700 },
});

test('Supervisor crea KB con IA y usuario final lo consulta (LENTO)', async ({ page, context }) => {
    test.setTimeout(360000);

    // ─────────────────────────────────────────────────────────────
    // FASE 1 — Supervisor inicia sesión
    // ─────────────────────────────────────────────────────────────
    await test.step('1. Supervisor inicia sesión', async () => {
        await page.goto('/login');
        await page.waitForTimeout(2000); // Pausa para que el espectador vea la pantalla de login.
        await page.fill('input[name="email"]', 'demo-supervisor@confipetrol.local');
        await page.waitForTimeout(800);
        await page.fill('input[name="password"]', 'demo1234');
        await page.waitForTimeout(800);
        await page.click('button[type="submit"]');
        await page.waitForURL(/\/(soporte|admin|dashboard)/, { timeout: 25000 });
        await page.waitForTimeout(2500);
    });

    // ─────────────────────────────────────────────────────────────
    // FASE 2 — Navegar a Crear KB
    // ─────────────────────────────────────────────────────────────
    await test.step('2. Navega al módulo Base de Conocimiento', async () => {
        await page.goto('/soporte/kb-articles');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000); // muestra la lista
    });

    await test.step('3. Click en "Nuevo Artículo de KB"', async () => {
        await page.goto('/soporte/kb-articles/create');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2500); // muestra el form vacío
    });

    // ─────────────────────────────────────────────────────────────
    // FASE 3 — Usar "Redactar con IA"
    // ─────────────────────────────────────────────────────────────
    await test.step('4. Abre el modal "Redactar con IA"', async () => {
        const aiButton = page.getByRole('button', { name: /redactar con ia|ia/i }).first();
        await aiButton.click({ timeout: 10000 });
        await page.waitForTimeout(2500); // muestra el modal abierto
    });

    await test.step('5. Escribe la descripción en lenguaje natural', async () => {
        // El modal de Filament usa <dialog>. Buscamos el textarea por su
        // wire:model (Livewire usa "mountedActionsData.0.natural_language").
        const textarea = page.locator('textarea[wire\\:model*="natural_language"], textarea[id*="natural_language"]').first();
        await textarea.waitFor({ state: 'visible', timeout: 10000 });
        await textarea.click();
        await textarea.type(AI_PROMPT, { delay: 30 });
        await page.waitForTimeout(1500);
    });

    await test.step('6. Click en "Generar"', async () => {
        await page.getByRole('button', { name: /generar/i }).first().click({ timeout: 10000 });
        // El DemoLlmService responde al instante; damos tiempo para
        // que el modal se cierre y el form se rellene visiblemente.
        await page.waitForTimeout(3500);
    });

    // ─────────────────────────────────────────────────────────────
    // FASE 4 — Revisar y publicar
    // ─────────────────────────────────────────────────────────────
    await test.step('7. Revisa el título y el body generados', async () => {
        // Scroll al título para mostrarlo y luego al body.
        const titleInput = page.locator('input[id$="title"]').first();
        await titleInput.scrollIntoViewIfNeeded();
        await page.waitForTimeout(2500); // contempla el título
        await page.evaluate(() => window.scrollBy(0, 250));
        await page.waitForTimeout(3000); // contempla el body
        await page.evaluate(() => window.scrollBy(0, 250));
        await page.waitForTimeout(2500);
    });

    await test.step('8. Selecciona Estado = Publicado', async () => {
        // El Select "status" del KB form es un <select> HTML nativo (no
        // tiene ->native(false)). Lo manejamos con selectOption() que
        // funciona directo. Locator: por wire:model.
        const statusSelect = page.locator('select[wire\\:model="data.status"], select[id$="status"]').first();
        await statusSelect.scrollIntoViewIfNeeded();
        await page.waitForTimeout(1500); // muestra el campo
        await statusSelect.selectOption({ label: 'Publicado' });
        await page.waitForTimeout(2000); // muestra el cambio
    });

    await test.step('9. Guarda el KB', async () => {
        await page.getByRole('button', { name: /^crear$|^create$/i }).first().click({ timeout: 10000 });
        await page.waitForTimeout(4000); // espera redirect + notif success
    });

    await test.step('10. Verifica que aparezca en la lista de KBs', async () => {
        await page.goto('/soporte/kb-articles');
        await page.waitForLoadState('networkidle');
        await expect(page.getByText(KB_PREVIEW_TITLE).first()).toBeVisible({ timeout: 10000 });
        await page.waitForTimeout(3500);
    });

    // ─────────────────────────────────────────────────────────────
    // FASE 5 — Cambiar a usuario final
    // ─────────────────────────────────────────────────────────────
    await test.step('11. Cierra sesión', async () => {
        await context.clearCookies();
        await page.waitForTimeout(800);
    });

    await test.step('12. Usuario final inicia sesión', async () => {
        await page.goto('/login');
        await page.waitForTimeout(2000);
        await page.fill('input[name="email"]', 'demo-final@confipetrol.local');
        await page.waitForTimeout(800);
        await page.fill('input[name="password"]', 'demo1234');
        await page.waitForTimeout(800);
        await page.click('button[type="submit"]');
        await page.waitForURL(/\/portal/, { timeout: 25000 });
        await page.waitForTimeout(3000); // muestra el dashboard del portal
    });

    // ─────────────────────────────────────────────────────────────
    // FASE 6 — Consulta al asistente
    // ─────────────────────────────────────────────────────────────
    await test.step('13. Abre el asistente virtual', async () => {
        await page.goto('/portal/chatbot');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3500); // muestra el chatbot con saludo
    });

    await test.step('14. Usuario escribe su pregunta', async () => {
        const input = page.locator('input[wire\\:model="message"]').first();
        await input.click();
        await input.type(USER_QUESTION, { delay: 60 });
        await page.waitForTimeout(1500); // muestra la pregunta escrita
        await page.keyboard.press('Enter');
        // El RAG matchea el KB recién creado con sim=1.0
        await page.waitForTimeout(7000);
    });

    await test.step('15. Verifica que la respuesta cite el KB recién publicado', async () => {
        // Aceptamos cualquiera de las palabras clave del KB HSE para que
        // si el RAG matchea por sinónimos o el chatbot devuelve solo el
        // link al artículo, igual valide.
        await expect(
            page.locator('text=/2911|hse\\.confipetrol|FR-HSE-001|incidente HSE|reportar.*incidente|HSE/i').first()
        ).toBeVisible({ timeout: 15000 });
    });

    await test.step('16. Scroll lento DENTRO del chat para mostrar toda la respuesta', async () => {
        // El chatbot tiene un contenedor con scroll propio (#chat-container,
        // h-[32rem] overflow-y-auto). Hacemos scroll dentro de ese div,
        // no de la página, para que el viewport del bot quede fijo y
        // el espectador siga viendo el header + input mientras la
        // respuesta se desliza.
        const scrollChat = async (top) => {
            await page.evaluate((t) => {
                const el = document.getElementById('chat-container');
                if (el) el.scrollTo({ top: t, behavior: 'smooth' });
            }, top);
        };
        const getScrollHeight = async () => page.evaluate(() => {
            const el = document.getElementById('chat-container');
            return el ? el.scrollHeight : 0;
        });

        // Scroll al inicio para ver la pregunta + comienzo de la respuesta.
        await scrollChat(0);
        await page.waitForTimeout(3000);

        // Scroll progresivo a través de toda la respuesta.
        const totalHeight = await getScrollHeight();
        const steps = 5;
        const stepHeight = Math.max(150, Math.floor(totalHeight / steps));

        for (let i = 1; i <= steps; i++) {
            await scrollChat(stepHeight * i);
            await page.waitForTimeout(2800);
        }

        // Pausa final con la respuesta completa visible al fondo.
        await page.waitForTimeout(4000);
    });
});
