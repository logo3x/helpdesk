<?php

namespace App\Services;

/**
 * Mock determinista del LlmService usado durante grabaciones de video
 * (tests/e2e/*.cjs).
 *
 * Se enlaza al container solo cuando DEMO_LLM_MOCK=true. Garantiza que
 * el demo del modal "Redactar con IA" no dependa del rate-limit de
 * OpenRouter y siempre devuelva un borrador presentable en pantalla.
 *
 * No reemplaza al LlmService real en producción — el AppServiceProvider
 * decide qué bindear según el env.
 */
class DemoLlmService extends LlmService
{
    /**
     * Devuelve un borrador prefabricado según el tema detectado en el
     * input. Acepta dos temas: "incidentes HSE" y un fallback genérico.
     *
     * @return array{title: string, body: string}
     */
    public function draftKbArticle(string $naturalLanguageInput, string $tone = 'formal', ?string $departmentName = null): array
    {
        $lc = mb_strtolower($naturalLanguageInput);

        if (str_contains($lc, 'incidente') || str_contains($lc, 'hse') || str_contains($lc, 'seguridad')) {
            return [
                'title' => 'Cómo reportar un incidente HSE en Confipetrol',
                'body' => <<<'MD'
## Cuándo aplica

Cuando ocurre cualquier evento que afecte la **seguridad, salud o ambiente** durante las operaciones de Confipetrol: derrames, lesiones, casi-accidentes, fugas, condiciones inseguras, etc.

## Pasos a seguir

1. **Asegura la zona** y a las personas. Si hay heridos, comunica al líder HSE más cercano antes de cualquier otra acción.
2. Llama al **número de emergencia HSE: ext. 2911**.
3. Ingresa al portal interno **hse.confipetrol.com** y diligencia el formulario **FR-HSE-001 Reporte de Incidente**.
4. Adjunta fotografías de la zona (sin exponer personas heridas).
5. El líder HSE de tu sede confirma el reporte en menos de **2 horas**.

## Información mínima requerida

- Fecha, hora y ubicación exacta del incidente
- Tipo de evento (derrame, lesión, fuga, casi-accidente)
- Personas involucradas y si requirieron atención médica
- Acciones inmediatas tomadas

## Si el problema persiste

Si no logras acceder al portal HSE, crea un ticket en la categoría **HSE - Reportes** y un agente registrará tu novedad por ti.
MD,
            ];
        }

        // Fallback genérico — el bot "redacta" lo que le digas con
        // estructura tipo KB.
        return [
            'title' => 'Procedimiento generado por IA',
            'body' => "## Resumen\n\n".$naturalLanguageInput."\n\n## Pasos a seguir\n\n1. Paso uno.\n2. Paso dos.\n3. Paso tres.\n\n## Si el problema persiste\n\nContacta al área correspondiente.",
        ];
    }
}
