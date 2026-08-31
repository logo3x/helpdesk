<?php

namespace App\Enums;

/**
 * Gerencias oficiales de Confipetrol (Sprint 7, 2026-08-25).
 *
 * Fuente única de verdad para el campo `management_area` en
 * `assets` y `users`. Usar en:
 *   - AssetForm  (Select cerrado)
 *   - UserResource form + tabla
 *   - PeopleTemplateService (dropdown en Excel)
 *   - PeopleImportService + InventoryImportService (validación)
 *
 * Los valores heredados (HSEQ, MANSAROVAR, ADMO, etc.) fueron
 * migrados a Administración por `gerencia:migrate` el 2026-08-25.
 */
enum ManagementArea: string
{
    case Zona1 = 'Zona 1';
    case Zona2 = 'Zona 2';
    case Zona3 = 'Zona 3';
    case Zona4 = 'Zona 4';
    case Zona5 = 'Zona 5';
    case Administracion = 'Administración';

    /**
     * Label legible para dropdowns. Devuelve el mismo valor porque
     * son labels autoexplicativos.
     */
    public function label(): string
    {
        return $this->value;
    }

    /**
     * @return array<string, string> value → label, para Select::options().
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /**
     * @return array<int, string> Solo valores (ideal para dropdowns en Excel).
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }

    /**
     * Normaliza un valor entrante — devuelve el enum si matchea (case
     * insensitive con la lista de valores) o null si no. Útil para
     * importers que reciben strings de Excel.
     */
    public static function tryNormalize(?string $value): ?self
    {
        if (blank($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        foreach (self::cases() as $case) {
            if (mb_strtolower($case->value) === mb_strtolower($normalized)) {
                return $case;
            }
        }

        return null;
    }
}
