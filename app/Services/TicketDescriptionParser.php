<?php

namespace App\Services;

/**
 * Separa la descripción de un ticket en partes legibles.
 *
 * Los tickets escalados desde el asistente virtual llegan con un
 * encabezado de datos de la sesión (resumen, departamento, prioridad
 * sugerida…) más la transcripción completa. Aquí se separan para que
 * el portal y los paneles muestren el resumen destacado y la
 * transcripción plegada, sin un muro de texto.
 */
class TicketDescriptionParser
{
    public const ASSISTANT_MARKER = '# Ticket escalado desde el asistente virtual';

    public const TRANSCRIPT_MARKER = '## Transcripción de la conversación';

    /**
     * @return array{fromAssistant: bool, body: string, summary: ?string, aiPriority: ?string, details: array<string, string>, transcript: ?string, messages: int}
     */
    public static function parse(?string $description): array
    {
        $text = (string) $description;
        $fromAssistant = str_starts_with(ltrim($text), self::ASSISTANT_MARKER);
        $transcript = null;
        $fields = [];

        if ($fromAssistant) {
            [$header, $transcript] = array_pad(explode(self::TRANSCRIPT_MARKER, $text, 2), 2, null);

            preg_match_all('/\*\*([^*]+):\*\*\s*(.+)/u', (string) $header, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $fields[trim($match[1])] = trim($match[2]);
            }
        }

        $details = array_filter(array_intersect_key(
            $fields,
            array_flip(['Departamento destino', 'Duración', 'Total mensajes', 'Sesión de chat']),
        ));

        return [
            'fromAssistant' => $fromAssistant,
            'body' => $text,
            'summary' => $fields['Resumen del usuario'] ?? null,
            'aiPriority' => $fields['Prioridad sugerida por la IA'] ?? null,
            'details' => $details,
            'transcript' => $transcript !== null ? trim($transcript) : null,
            'messages' => $transcript ? substr_count($transcript, '---') + 1 : 0,
        ];
    }
}
