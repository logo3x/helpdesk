<?php

namespace App\Exports;

use Carbon\CarbonInterface;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Export del reporte de Chatbot Metrics a Excel.
 *
 * Genera UN .xlsx con varias hojas — una por cada bloque del reporte
 * en pantalla — para que el operador pueda compartir la métrica a
 * stakeholders no técnicos sin tener que sacar screenshots ni copiar
 * tablas a mano.
 *
 * Hojas:
 *   - Resumen: KPIs globales con delta vs periodo anterior.
 *   - Origen: distribución por source_kind + CSAT.
 *   - Top KB fallidos: artículos con más 👎.
 *   - Gaps KB: preguntas que terminaron en fallback.
 *   - Negativos recientes: últimos mensajes 👎 para drill-down manual.
 */
class ChatbotMetricsExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $summaryPrev
     * @param  array<int, array<string, mixed>>  $sourceBreakdown
     * @param  array<int, array<string, mixed>>  $topUnhelpful
     * @param  array<int, array{question: string, count: int}>  $fallbackQuestions
     * @param  array<int, array<string, mixed>>  $recentNegatives
     */
    public function __construct(
        public int $window,
        public ?string $departmentId,
        public array $summary,
        public array $summaryPrev,
        public array $sourceBreakdown,
        public array $topUnhelpful,
        public array $fallbackQuestions,
        public array $recentNegatives,
    ) {}

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            new ChatbotMetricsSummarySheet($this->window, $this->summary, $this->summaryPrev),
            new ChatbotMetricsSourceSheet($this->sourceBreakdown),
            new ChatbotMetricsTopUnhelpfulSheet($this->topUnhelpful),
            new ChatbotMetricsGapsSheet($this->fallbackQuestions),
            new ChatbotMetricsNegativesSheet($this->recentNegatives),
        ];
    }
}

/**
 * Hoja 1 — Resumen con deltas vs periodo anterior.
 */
class ChatbotMetricsSummarySheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $summaryPrev
     */
    public function __construct(
        public int $window,
        public array $summary,
        public array $summaryPrev,
    ) {}

    public function title(): string
    {
        return 'Resumen';
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Métrica', 'Periodo actual', 'Periodo anterior', 'Delta absoluto', 'Delta %'];
    }

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        $rows = [];

        $metrics = [
            'Sesiones' => 'sessions',
            'Mensajes del bot' => 'assistant_messages',
            'Mensajes votados' => 'rated',
            'Votos 👍' => 'helpful',
            'Votos 👎' => 'not_helpful',
            'CSAT %' => 'csat_pct',
            'Auto-resolución %' => 'auto_resolution_pct',
        ];

        foreach ($metrics as $label => $key) {
            $cur = $this->summary[$key] ?? null;
            $prev = $this->summaryPrev[$key] ?? null;

            $absDelta = ($cur !== null && $prev !== null) ? round($cur - $prev, 1) : null;
            $pctDelta = ($cur !== null && $prev !== null && $prev != 0)
                ? round((($cur - $prev) / $prev) * 100, 1).'%'
                : '—';

            $rows[] = [
                $label,
                $cur ?? '—',
                $prev ?? '—',
                $absDelta ?? '—',
                $pctDelta,
            ];
        }

        $rows[] = [];
        $rows[] = ['Ventana (días)', $this->window, '', '', ''];
        $rows[] = ['Generado', now()->format('Y-m-d H:i'), '', '', ''];

        return $rows;
    }
}

/**
 * Hoja 2 — Origen de las respuestas.
 */
class ChatbotMetricsSourceSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /** @param  array<int, array<string, mixed>>  $rows */
    public function __construct(public array $rows) {}

    public function title(): string
    {
        return 'Origen';
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Origen', 'Total', 'Helpful', 'Not Helpful', 'CSAT %'];
    }

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        return collect($this->rows)->map(function (array $row) {
            $rated = $row['helpful'] + $row['not_helpful'];
            $csat = $rated > 0 ? round(($row['helpful'] / $rated) * 100, 1) : null;

            return [
                $row['source_kind'] ?? 'Sin clasificar',
                $row['total'],
                $row['helpful'],
                $row['not_helpful'],
                $csat ?? '—',
            ];
        })->all();
    }
}

/**
 * Hoja 3 — Top KB con más 👎.
 */
class ChatbotMetricsTopUnhelpfulSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /** @param  array<int, array<string, mixed>>  $rows */
    public function __construct(public array $rows) {}

    public function title(): string
    {
        return 'Top KB fallidos';
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['ID', 'Artículo', 'Usado', 'Helpful', 'Not helpful', 'CSAT %'];
    }

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        return collect($this->rows)->map(fn (array $row) => [
            $row['article_id'],
            $row['title'] ?? '(borrado)',
            $row['total'],
            $row['helpful'],
            $row['not_helpful'],
            $row['csat'] ?? '—',
        ])->all();
    }
}

/**
 * Hoja 4 — Gaps de KB (preguntas en fallback).
 */
class ChatbotMetricsGapsSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /** @param  array<int, array{question: string, count: int}>  $rows */
    public function __construct(public array $rows) {}

    public function title(): string
    {
        return 'Gaps KB';
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['Pregunta', 'Veces que apareció'];
    }

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        return collect($this->rows)->map(fn (array $row) => [
            $row['question'],
            $row['count'],
        ])->all();
    }
}

/**
 * Hoja 5 — Últimos mensajes negativos.
 */
class ChatbotMetricsNegativesSheet implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    /** @param  array<int, array<string, mixed>>  $rows */
    public function __construct(public array $rows) {}

    public function title(): string
    {
        return 'Negativos recientes';
    }

    /** @return array<int, string> */
    public function headings(): array
    {
        return ['ID', 'Fecha voto', 'Artículo KB', 'Respuesta del bot'];
    }

    /** @return array<int, array<int, mixed>> */
    public function array(): array
    {
        return collect($this->rows)->map(fn (array $row) => [
            $row['id'],
            $row['created_at'] instanceof CarbonInterface
                ? $row['created_at']->format('Y-m-d H:i')
                : (string) $row['created_at'],
            $row['kb_title'] ?? '—',
            $row['content'],
        ])->all();
    }
}
