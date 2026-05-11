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
 * Genera el archivo .xlsx plantilla para la carga masiva del inventario
 * (`inventory:import-from-xlsx`). Incluye:
 *  - Encabezados en el orden esperado por el importador.
 *  - Una fila de ejemplo para que IT vea el formato esperado.
 *  - Validación de datos (dropdowns) en columnas Tipo Activo y Estado.
 *  - Hoja de "Instrucciones" con la descripción de cada columna.
 */
class InventoryTemplateService
{
    /**
     * Columnas: [encabezado, ancho, ejemplo, descripción].
     *
     * @var array<int, array{0: string, 1: int, 2: string, 3: string}>
     */
    protected const COLUMNS = [
        ['TAG', 14, 'TAG-001', 'Identificador único interno del activo (obligatorio si no hay Serial).'],
        ['Serial', 18, 'SN123456', 'Número de serie del fabricante (obligatorio si no hay TAG).'],
        ['Fabricante', 16, 'HP', 'Marca del equipo: HP, Dell, Lenovo, Apple, etc.'],
        ['Modelo', 22, 'Elitebook 840 G8', 'Modelo del equipo.'],
        ['Codigo SAP', 14, 'SAP-100', 'Código contable del activo en SAP.'],
        ['Tipo Activo', 14, 'laptop', 'Uno de: laptop, desktop, server, printer, monitor, phone, tablet, network, peripheral, other.'],
        ['Estado', 12, 'activo', 'Uno de: activo, inactivo, regular, baja. Se mapea a active/inactive/fair/retired.'],
        ['Custodio', 28, 'Juan Pérez', 'Nombre completo del responsable del equipo. Se crea el usuario si no existe.'],
        ['Identificacion', 16, '1098765432', 'Cédula del custodio. Si el usuario ya existe, se busca por aquí.'],
        ['Cargo', 22, 'Técnico SR', 'Cargo del custodio en la empresa.'],
        ['Correo', 32, 'juan.perez@confipetrol.com', 'Email corporativo. Si existe en la BD, se reutiliza el usuario.'],
        ['Proyecto', 16, '499015105', 'Código del proyecto/contrato. Si no existe, se crea con el nombre indicado.'],
        ['Nom_Proyecto', 28, 'PERENCO CARUPANA', 'Nombre del proyecto (solo se usa si Proyecto no existía aún).'],
        ['Campo', 16, 'Curito', 'Campo o zona operativa donde está físicamente el equipo.'],
        ['Ubicacion', 20, 'Bloque A', 'Subzona o piso dentro del campo.'],
        ['Observacion', 40, 'Sin novedad', 'Notas internas del activo.'],
        ['Linea', 14, '3001234567', 'Línea telefónica asociada (para celulares/módems).'],
        ['IMEI', 18, '350000000000001', 'IMEI del dispositivo móvil.'],
        ['Gerencia', 22, 'Tecnología', 'Área gerencial responsable del equipo.'],
        ['Ultimo Mtto', 14, '2026-01-15', 'Fecha del último mantenimiento (YYYY-MM-DD).'],
        ['Mtto Dias', 10, '180', 'Intervalo en días para el próximo mantenimiento. Próx. mtto se calcula solo.'],
        ['Responsable', 24, 'Pedro Técnico', 'Responsable del mantenimiento. Se crea con rol técnico_campo si no existe.'],
    ];

    /**
     * Construye el Spreadsheet y lo devuelve. El llamador decide si lo
     * guarda en disco o lo streamea como respuesta HTTP.
     */
    public function build(): Spreadsheet
    {
        $sp = new Spreadsheet;
        $sp->getProperties()
            ->setCreator('Helpdesk Confipetrol')
            ->setTitle('Plantilla carga inventario')
            ->setDescription('Plantilla oficial para `inventory:import-from-xlsx`.');

        $this->buildDataSheet($sp);
        $this->buildInstructionsSheet($sp);

        $sp->setActiveSheetIndex(0);

        return $sp;
    }

    /**
     * Guarda el archivo en disco y devuelve la ruta absoluta.
     */
    public function saveTo(string $absolutePath): string
    {
        $writer = new Xlsx($this->build());
        $writer->save($absolutePath);

        return $absolutePath;
    }

    /**
     * Devuelve el binario del .xlsx para streamear como descarga.
     */
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

        // Headers.
        foreach (self::COLUMNS as $i => $col) {
            $letter = $this->columnLetter($i + 1);
            $sheet->setCellValue("{$letter}1", $col[0]);
            $sheet->getColumnDimension($letter)->setWidth($col[1]);
        }

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

        // Fila de ejemplo en la 2.
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

        $this->applyDropdown(
            $sheet,
            column: 'F',
            list: '"laptop,desktop,server,printer,monitor,phone,tablet,network,peripheral,other"',
            prompt: 'Tipo de activo',
            promptTitle: 'Tipo Activo',
        );
        $this->applyDropdown(
            $sheet,
            column: 'G',
            list: '"activo,inactivo,regular,baja"',
            prompt: 'Estado del equipo',
            promptTitle: 'Estado',
        );
    }

    protected function buildInstructionsSheet(Spreadsheet $sp): void
    {
        $sheet = $sp->createSheet();
        $sheet->setTitle('Instrucciones');

        $sheet->setCellValue('A1', 'Plantilla de carga masiva — Inventario Confipetrol');
        $sheet->mergeCells('A1:C1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
        ]);

        $sheet->setCellValue('A3', 'Cómo se carga:');
        $sheet->setCellValue('A4', "1. Completa la hoja 'Inventario' con tus equipos (puedes borrar la fila de ejemplo).");
        $sheet->setCellValue('A5', '2. Guarda el archivo como .xlsx.');
        $sheet->setCellValue('A6', '3. En el servidor, corre: php artisan inventory:import-from-xlsx ruta/al/archivo.xlsx');
        $sheet->setCellValue('A7', '4. Verifica con: --dry-run antes de cargar definitivo.');

        $sheet->setCellValue('A9', 'Columnas:');
        $sheet->getStyle('A9')->getFont()->setBold(true);

        $row = 10;
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

        $required = ['TAG', 'Serial']; // Al menos uno de los dos.

        $row = 11;
        foreach (self::COLUMNS as $col) {
            $sheet->setCellValue("A{$row}", $col[0]);
            $sheet->setCellValue(
                "B{$row}",
                in_array($col[0], $required, true) ? 'TAG o Serial' : 'No',
            );
            $sheet->setCellValue("C{$row}", $col[3]);
            $sheet->getStyle("C{$row}")->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($row)->setRowHeight(-1);
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(80);

        $sheet->getStyle('A10:C'.($row - 1))->applyFromArray([
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
        // Aplicamos validación de la fila 2 a la 500 para que el dropdown
        // aparezca cuando IT agregue filas nuevas debajo del ejemplo.
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

    /**
     * Convierte índice 1-based a letra de columna Excel (1 -> A, 27 -> AA).
     */
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
