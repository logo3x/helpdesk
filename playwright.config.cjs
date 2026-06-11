// Configuración Playwright para grabar videos demo del helpdesk.
//
// Uso:
//   1) Levantar la app: php artisan serve
//   2) Correr: npx playwright test tests/e2e/kb-demo.spec.js --headed
//   3) Video sale en: tests/e2e/videos/<test-name>/<random>.webm
//
// Modo --headed muestra el browser en pantalla (útil para grabar con OBS también).

const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
    testDir: './tests/e2e',
    testMatch: '**/*.spec.cjs',
    timeout: 360000, // 6 min por test (KB form + RAG + LLM mock + pausas largas)
    expect: { timeout: 15000 },
    fullyParallel: false,
    workers: 1,
    reporter: [['list']],
    outputDir: './tests/e2e/videos',

    use: {
        baseURL: 'http://127.0.0.1:8000',
        // Video siempre on: queremos el archivo aunque el test pase.
        video: {
            mode: 'on',
            size: { width: 1280, height: 720 },
        },
        screenshot: 'only-on-failure',
        trace: 'on',
        actionTimeout: 15000,
        navigationTimeout: 30000,
        // Headed para que se vea en pantalla mientras se ejecuta.
        headless: false,
        viewport: { width: 1280, height: 720 },
        // Slow-mo para que el video sea narrable (cada acción se ve).
        launchOptions: {
            slowMo: 400,
        },
    },

    projects: [
        {
            name: 'chromium',
            use: {
                browserName: 'chromium',
            },
        },
    ],
});
