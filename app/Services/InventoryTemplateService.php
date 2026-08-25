<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Plantilla .xlsx para la carga masiva del inventario.
 *
 * Reglas de la plantilla (Sprint 5, 2026-08-25):
 *  - TAG o Serial es obligatorio (al menos uno).
 *  - Identificación (cédula del custodio) es la clave de matching —
 *    el importer prioriza cédula sobre email para localizar al custodio.
 *  - Se removieron columnas históricas (Último Mtto, Mtto Días,
 *    Responsable). Esos datos ahora viven en el módulo Mantenimientos
 *    Programados — cargarlos acá creaba inconsistencias.
 *  - Los dropdowns de Tipo Activo y Estado reflejan los enums reales
 *    del sistema (Sprint 5 corrige la divergencia previa).
 */
class InventoryTemplateService
{
    /**
     * Tipos válidos — deben coincidir con app/Filament/Resources/Assets/
     * Schemas/AssetForm.php. Cualquier cambio ahí requiere sincronizar
     * este dropdown y `InventoryImportService::normalizeType()`.
     */
    public const VALID_TYPES = [
        'desktop', 'laptop', 'all_in_one', 'monitor', 'server',
        'printer', 'phone', 'tablet', 'radio', 'antenna',
        'network_kit', 'ups', 'other',
    ];

    public const VALID_STATUSES = [
        'active', 'fair', 'in_repair', 'retired',
    ];

    /**
     * @var array<int, array{0: string, 1: int, 2: string, 3: string, 4: bool}>
     *                                                                          Estructura: [header, ancho, ejemplo, descripción, keyed]
     *                                                                          `keyed=true` → columna en negrita porque es clave de matching.
     */
    protected const COLUMNS = [
        ['TAG', 14, 'TAG-001', 'Identificador único interno del activo. TAG o Serial es obligatorio.', true],
        ['Serial', 18, 'SN123456', 'Número de serie del fabricante. TAG o Serial es obligatorio.', true],
        ['Fabricante', 16, 'HP', 'Marca del equipo: HP, Dell, Lenovo, Apple, etc.', false],
        ['Modelo', 22, 'Elitebook 840 G8', 'Modelo del equipo.', false],
        ['Codigo SAP', 14, 'SAP-100', 'Código contable del activo en SAP.', false],
        ['Tipo Activo', 14, 'laptop', 'Uno de: desktop, laptop, all_in_one, monitor, server, printer, phone, tablet, radio, antenna, network_kit, ups, other.', false],
        ['Estado', 12, 'active', 'Uno de: active (bueno), fair (regular), in_repair, retired. También acepta variantes en español: activo, regular, mal_estado, baja.', false],
        ['Identificacion', 16, '1121898647', '⭐ CLAVE DE MATCHING — cédula del custodio. El importer busca por acá primero.', true],
        ['Custodio', 28, 'Luis Guillermo Oviedo', 'Nombre completo del custodio (opcional si la cédula ya existe en el sistema).', false],
        ['Cargo', 22, 'Líder de Proyecto', 'Cargo del custodio.', false],
        ['Correo', 32, 'luis.oviedo@confipetrol.com', 'Email del custodio (opcional si la cédula ya existe).', false],
        ['Departamento', 22, 'Tecnología', 'Departamento del custodio (se crea si no existe).', false],
        ['Proyecto', 16, '499015105', 'Código del proyecto/contrato. Se crea si no existe.', false],
        ['Nom_Proyecto', 28, 'PERENCO CARUPANA', 'Nombre del proyecto (solo si se crea uno nuevo).', false],
        ['Campo', 16, 'Curito', 'Campo o zona operativa donde está físicamente el equipo.', false],
        ['Ubicacion', 20, 'Bloque A', 'Subzona o piso dentro del campo.', false],
        ['Zona', 16, 'Meta', 'Zona geográfica más amplia (opcional).', false],
        ['Gerencia', 22, 'Tecnología', 'Área gerencial responsable del equipo.', false],
        ['Linea', 14, '3001234567', 'Línea telefónica asociada (para celulares/módems).', false],
        ['IMEI', 18, '350000000000001', 'IMEI del dispositivo móvil.', false],
        ['Observacion', 40, 'Sin novedad', 'Notas internas del activo.', false],
    ];

    public function build(): Spreadsheet
    {
        $sp = new Spreadsheet;
        $sp->getProperties()
            ->setCreator('Helpdesk Confipetrol')
            ->setTitle('Plantilla carga inventario')
            ->setDescription('Plantilla oficial para la carga masiva del inventario.');

        $this->buildDataSheet($sp);
        $this->buildInstructionsSheet($sp);

        $sp->setActiveSheetIndex(0);

        return $sp;
    }

    public function toBinary(): string
    {
        $writer = new Xlsx($this->build());

        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }

    protected function buildDataSheet(Spreadsheet $sp): void
    {
        $sheet = $sp->getActiveSheet();
        $sheet->setTitle('Inventario');

        $columnsCount = count(self::COLUMNS);
        $lastColLetter = $this->columnLetter($columnsCount);

        foreach (self::COLUMNS as $i => $col) {
            $letter = $this->columnLetter($i + 1);
            $sheet->setCellValue("{$letter}1", $col[0]);
            $sheet->getColumnDimension($letter)->setWidth($col[1]);
        }

        // Estilo de encabezados.
        $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0F4C81'],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->freezePane('A2');

        // Fila de ejemplo (2) en gris cursiva para que no se confunda con datos.
        foreach (self::COLUMNS as $i => $col) {
            $letter = $this->columnLetter($i + 1);
            $sheet->setCellValueExplicit(
                "{$letter}2",
                $col[2],
                DataType::TYPE_STRING,
            );
        }
        $sheet->getStyle("A2:{$lastColLetter}2")->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '666666']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F4F6F8'],
            ],
        ]);

        // Columnas clave (TAG, Serial, Identificación) en negrita a
        // partir de la fila 3 para que se destaquen al llenar.
        foreach (self::COLUMNS as $i => $col) {
            if ($col[4] === false) {
                continue;
            }
            $letter = $this->columnLetter($i + 1);
            $sheet->getStyle("{$letter}3:{$letter}500")->getFont()->setBold(true);
        }

        // Dropdowns — sincronizados con los enums reales.
        $this->applyDropdown(
            $sheet,
            column: 'F', // Tipo Activo
            list: '"'.implode(',', self::VALID_TYPES).'"',
            prompt: 'Tipo de activo',
            promptTitle: 'Tipo Activo',
        );
        $this->applyDropdown(
            $sheet,
            column: 'G', // Estado
            list: '"'.implode(',', self::VALID_STATUSES).'"',
            prompt: 'Estado / condición del equipo',
            promptTitle: 'Estado',
        );
    }

    protected function buildInstructionsSheet(Spreadsheet $sp): void
    {
        $sheet = $sp->createSheet();
        $sheet->setTitle('Instrucciones');

        $sheet->setCellValue('A1', 'Plantilla — Carga masiva de inventario Confipetrol');
        $sheet->mergeCells('A1:C1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
        ]);

        $sheet->setCellValue('A3', 'Antes de cargar activos, precargá las personas.');
        $sheet->getStyle('A3')->getFont()->setBold(true);
        $sheet->setCellValue('A4', 'El importer busca al custodio por CÉDULA (columna Identificación).');
        $sheet->setCellValue('A5', 'Si la persona ya está en el sistema (fue precargada desde /admin/users');
        $sheet->setCellValue('A6', 'con "Precargar personas" o entró antes por Azure), el activo se enlaza');
        $sheet->setCellValue('A7', 'sin crear duplicados.');

        $sheet->setCellValue('A9', '¿Qué pasa si el custodio NO existe todavía?');
        $sheet->getStyle('A9')->getFont()->setBold(true);
        $sheet->setCellValue('A10', 'Se crea un stub con la info que traiga la fila (nombre, cédula, cargo,');
        $sheet->setCellValue('A11', 'depto, email). Si el email termina en @confipetrol.com queda como');
        $sheet->setCellValue('A12', '"Azure pendiente" y se activa al primer login SSO. Si es otro dominio');
        $sheet->setCellValue('A13', 'o vacío, queda como cuenta local con password inicial = primeros 8');
        $sheet->setCellValue('A14', 'dígitos de la cédula.');

        $sheet->setCellValue('A16', 'Cómo cargar:');
        $sheet->getStyle('A16')->getFont()->setBold(true);
        $sheet->setCellValue('A17', "1. Completá la hoja 'Inventario' (podés borrar la fila de ejemplo).");
        $sheet->setCellValue('A18', '2. Guardá como .xlsx.');
        $sheet->setCellValue('A19', '3. En /admin/assets, botón "📤 Importar inventario".');
        $sheet->setCellValue('A20', '4. Recomendado: primero corré en modo "dry-run" para ver el reporte.');

        $sheet->setCellValue('A22', 'Columnas:');
        $sheet->getStyle('A22')->getFont()->setBold(true);

        $row = 23;
        $sheet->setCellValue("A{$row}", 'Columna');
        $sheet->setCellValue("B{$row}", 'Obligatorio');
        $sheet->setCellValue("C{$row}", 'Descripción');
        $sheet->getStyle("A{$row}:C{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E5E7EB'],
            ],
        ]);

        $requiredLabel = 'TAG o Serial';

        $row = 24;
        foreach (self::COLUMNS as $col) {
            $sheet->setCellValue("A{$row}", $col[0]);

            if (in_array($col[0], ['TAG', 'Serial'], true)) {
                $obligatorio = $requiredLabel;
            } elseif ($col[4] === true) {
                $obligatorio = 'Recomendado';
            } else {
                $obligatorio = 'No';
            }

            $sheet->setCellValue("B{$row}", $obligatorio);
            $sheet->setCellValue("C{$row}", $col[3]);
            $sheet->getStyle("C{$row}")->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($row)->setRowHeight(-1);
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(16);
        $sheet->getColumnDimension('C')->setWidth(80);

        $sheet->getStyle('A23:C'.($row - 1))->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_TOP],
        ]);
    }

    protected function applyDropdown(
        Worksheet $sheet,
        string $column,
        string $list,
        string $prompt,
        string $promptTitle,
    ): void {
        for ($r = 2; $r <= 500; $r++) {
            $validation = $sheet->getCell("{$column}{$r}")->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setPromptTitle($promptTitle);
            $validation->setPrompt($prompt);
            $validation->setFormula1($list);
        }
    }

    protected function columnLetter(int $index): string
    {
        $letter = '';
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }
}
