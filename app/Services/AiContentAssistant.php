<?php

namespace App\Services;

use App\Models\Category;

/**
 * Asistente IA para generar y mejorar contenido del helpdesk:
 *
 *  - Plantillas de ticket: a partir de una idea breve, devuelve un
 *    `subject` listo y una `description` en Markdown con la estructura
 *    típica de Confipetrol (Contexto / Impacto / Pasos / Resultado).
 *  - Canned responses (respuestas predeterminadas): genera un `title` y
 *    `body` con tono profesional + Markdown.
 *  - Refinamiento de un texto existente con una instrucción libre
 *    ("hazlo más corto", "agrega un disclaimer de seguridad", etc.).
 *
 * Devuelve `null` si la API no está configurada o el LLM falla.
 * Todas las salidas se parsean defensivamente (regex sobre marcadores
 * `SUBJECT:` / `DESCRIPTION:`) para no depender de JSON estricto que
 * algunos modelos no respetan al 100%.
 */
class AiContentAssistant
{
    public function __construct(
        protected LlmService $llm,
    ) {}

    /**
     * Genera el `subject` y `description` de una plantilla de ticket
     * a partir de una idea/brief del agente.
     *
     * @return array{subject: string, description: string}|null
     */
    public function generateTicketTemplate(string $brief, ?Category $category = null): ?array
    {
        $context = $category?->name
            ? "Categoría destino: {$category->name}.\n"
            : '';

        $systemPrompt = <<<'PROMPT'
Eres un asistente que ayuda a crear PLANTILLAS de tickets de soporte para
Confipetrol (empresa de servicios petroleros). Generas el asunto y la
descripción que un usuario rellenará al crear un ticket de este tipo.

REGLAS
- Responde SIEMPRE en español.
- El "subject" es corto (max 80 chars), claro, en mayúscula inicial.
- La "description" usa Markdown con esta estructura mínima:
  - Sección "## Contexto" — una línea explicando el escenario.
  - Sección "## Detalles a proporcionar" — lista de campos que el usuario
    debe completar (ej: PC afectada, hora del incidente, error exacto).
  - Sección "## Resultado esperado" — qué espera obtener el usuario al
    cerrarse el ticket.
- NO inventes nombres de personas, sistemas internos, IPs, teléfonos o
  proyectos. Usa placeholders genéricos como "[nombre]", "[fecha]",
  "[código de error]".
- No agregues comentarios fuera de los marcadores SUBJECT/DESCRIPTION.

FORMATO DE SALIDA (literal, respeta los marcadores)
SUBJECT: <asunto>
DESCRIPTION:
<descripción markdown multi-línea>
PROMPT;

        $userPrompt = "{$context}Idea de la plantilla: {$brief}";

        $raw = $this->llm->chat(
            [['role' => 'user', 'content' => $userPrompt]],
            $systemPrompt,
        );

        if (! filled($raw)) {
            return null;
        }

        return $this->parseSubjectDescription($raw);
    }

    /**
     * Genera el `title` y `body` de una canned response.
     *
     * @return array{title: string, body: string}|null
     */
    public function generateCannedResponse(string $brief, ?Category $category = null): ?array
    {
        $context = $category?->name
            ? "Categoría: {$category->name}.\n"
            : '';

        $systemPrompt = <<<'PROMPT'
Eres un asistente que ayuda a crear RESPUESTAS PREDETERMINADAS (canned
responses) que los agentes de soporte de Confipetrol usan para contestar
tickets repetitivos.

REGLAS
- Responde SIEMPRE en español, tono profesional pero cercano.
- El "title" es corto (max 80 chars) y describe internamente la situación,
  no es para el usuario final. Ej: "Confirmar recepción y solicitar PC".
- El "body" es Markdown listo para enviarse al usuario:
  - Saludo breve ("Hola [nombre],").
  - 1-3 párrafos cortos, fáciles de leer.
  - Usa listas y negritas para puntos importantes.
  - Cierra con una frase amable invitando a continuar.
- NO inventes pasos, políticas internas, ni nombres de personas/sistemas
  específicos. Usa placeholders entre corchetes: [nombre], [fecha], etc.

FORMATO DE SALIDA (literal)
TITLE: <título>
BODY:
<cuerpo markdown multi-línea>
PROMPT;

        $userPrompt = "{$context}Idea de la respuesta: {$brief}";

        $raw = $this->llm->chat(
            [['role' => 'user', 'content' => $userPrompt]],
            $systemPrompt,
        );

        if (! filled($raw)) {
            return null;
        }

        return $this->parseTitleBody($raw);
    }

    /**
     * Refina un texto existente siguiendo una instrucción libre.
     * Útil para "hazlo más corto", "agrega un disclaimer", "más formal".
     */
    public function refine(string $text, string $instruction): ?string
    {
        $systemPrompt = <<<'PROMPT'
Eres un editor que reescribe textos del helpdesk de Confipetrol siguiendo
una instrucción del agente. Devuelves ÚNICAMENTE el texto reescrito,
sin preámbulos, sin comentarios, sin envolverlo en bloques de código.
Conserva la estructura Markdown si la tenía. Responde en español.
PROMPT;

        $userPrompt = "Texto original:\n---\n{$text}\n---\n\nInstrucción: {$instruction}";

        $raw = $this->llm->chat(
            [['role' => 'user', 'content' => $userPrompt]],
            $systemPrompt,
        );

        if (! filled($raw)) {
            return null;
        }

        return trim($raw);
    }

    /**
     * Parsea la salida con marcadores SUBJECT / DESCRIPTION.
     *
     * @return array{subject: string, description: string}|null
     */
    protected function parseSubjectDescription(string $raw): ?array
    {
        if (! preg_match('/SUBJECT:\s*(?<subject>[^\n]+)\s*DESCRIPTION:\s*(?<description>.+)/si', $raw, $m)) {
            return null;
        }

        $subject = trim($m['subject']);
        $description = trim($m['description']);

        if ($subject === '' || $description === '') {
            return null;
        }

        return [
            'subject' => mb_substr($subject, 0, 255),
            'description' => $description,
        ];
    }

    /**
     * Parsea la salida con marcadores TITLE / BODY.
     *
     * @return array{title: string, body: string}|null
     */
    protected function parseTitleBody(string $raw): ?array
    {
        if (! preg_match('/TITLE:\s*(?<title>[^\n]+)\s*BODY:\s*(?<body>.+)/si', $raw, $m)) {
            return null;
        }

        $title = trim($m['title']);
        $body = trim($m['body']);

        if ($title === '' || $body === '') {
            return null;
        }

        return [
            'title' => mb_substr($title, 0, 255),
            'body' => $body,
        ];
    }
}
