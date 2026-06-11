// @ts-check
const { test, expect } = require('@playwright/test');
const { execSync } = require('child_process');

/**
 * Demo de funcionalidad: muestra el flujo completo de un KB recién
 * creado por un supervisor + consulta del mismo desde el asistente del
 * portal por un usuario final.
 *
 * Estrategia: para que el demo sea reproducible y no se pelee con el
 * editor CodeMirror del MarkdownEditor de Filament, el KB se crea
 * programáticamente vía tinker en el beforeAll. El supervisor en el
 * video VISITA el KB ya publicado (muestra la pantalla, el contenido,
 * la lista de KBs) y narra el contexto. Luego el usuario final hace la
 * consulta real al asistente.
 *
 * Output: tests/e2e/videos/<nombre-test>/video.webm
 *
 * Pre-requisitos en BD (ya creados):
 *   demo-supervisor@confipetrol.local / demo1234
 *   demo-final@confipetrol.local      / demo1234
 */

const KB_TITLE = 'Solicitar acceso a SAP S/4HANA en Confipetrol (Demo)';
const KB_QUESTION = '¿Cómo solicito acceso a SAP en Confipetrol?';

test.beforeAll(() => {
    // Resetea el KB de demo vía comando artisan dedicado para evitar
    // pelearse con escapado de comillas y con CodeMirror en el video.
    try {
        execSync('php artisan demo:seed-kb', { stdio: 'pipe' });
    } catch (e) {
        throw new Error('demo:seed-kb falló — corré php artisan demo:seed-kb manualmente: ' + e.message);
    }
});

test('Supervisor revisa el KB publicado y usuario final lo consulta en el asistente', async ({ page, context }) => {
    test.setTimeout(180000);

    // ─────────────────────────────────────────────────────────────
    // FASE 1 — Supervisor revisa el KB publicado
    // ─────────────────────────────────────────────────────────────
    await test.step('1. Supervisor inicia sesión', async () => {
        await page.goto('/login');
        await page.fill('input[name="email"]', 'demo-supervisor@confipetrol.local');
        await page.fill('input[name="password"]', 'demo1234');
        await page.click('button[type="submit"]');
        await page.waitForURL(/\/(soporte|admin|dashboard)/, { timeout: 25000 });
        await page.waitForTimeout(1500);
    });

    await test.step('2. Lista de KBs en panel Soporte', async () => {
        await page.goto('/soporte/kb-articles');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2500); // pausa para que el video muestre la lista
    });

    await test.step('3. Click en el KB recién publicado', async () => {
        await page.getByText(KB_TITLE).first().click({ timeout: 10000 });
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000); // pausa para mostrar el form con contenido
    });

    // ─────────────────────────────────────────────────────────────
    // FASE 2 — Usuario final consulta el asistente
    // ─────────────────────────────────────────────────────────────
    await test.step('4. Cierre de sesión del supervisor', async () => {
        await context.clearCookies();
        await page.waitForTimeout(500);
    });

    await test.step('5. Usuario final inicia sesión', async () => {
        await page.goto('/login');
        await page.fill('input[name="email"]', 'demo-final@confipetrol.local');
        await page.fill('input[name="password"]', 'demo1234');
        await page.click('button[type="submit"]');
        await page.waitForURL(/\/portal/, { timeout: 25000 });
        await page.waitForTimeout(2000);
    });

    await test.step('6. Usuario abre el asistente virtual', async () => {
        await page.goto('/portal/chatbot');
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(2000);
    });

    await test.step('7. Usuario pregunta sobre SAP', async () => {
        const input = page.locator('input[wire\\:model="message"]').first();
        await input.click();
        await input.type(KB_QUESTION, { delay: 40 });
        await page.waitForTimeout(800);
        await page.keyboard.press('Enter');
        // El RAG procesa y muestra la respuesta del KB recién publicado.
        await page.waitForTimeout(6000);
    });

    await test.step('8. Verifica que la respuesta cite el KB recién publicado', async () => {
        await expect(
            page.locator('text=/accesos\\.sap@confipetrol|sap s\\/4hana|sap\\.confipetrol|microsoft authenticator/i').first()
        ).toBeVisible({ timeout: 15000 });
        // Pausa final para que el video cierre con la respuesta visible.
        await page.waitForTimeout(4000);
    });
});
