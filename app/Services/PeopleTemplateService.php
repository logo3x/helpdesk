<?php

namespace App\Services;

use App\Enums\ManagementArea;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Plantilla .xlsx para la precarga masiva de personas.
 *
 * Reglas del cargue (a leer en la hoja "Instrucciones"):
 *  - La columna Identificación es OBLIGATORIA y clave única.
 *  - Si el email termina en @confipetrol.com → la cuenta queda como
 *    "Azure pendiente" (no puede login local, espera botón azul).
 *  - Si el email es de otro dominio o está vacío → cuenta local con
 *    password inicial = primeros 8 dígitos de la cédula. Fuerza
 *    cambio en el primer login.
 *  - Rol por default es 'usuario_final'. Otros válidos:
 *    agente_soporte, supervisor_soporte, tecnico_campo, editor_kb,
 *    admin, super_admin.
 */
class PeopleTemplateService
{
    /**
     * @var array<int, array{0: string, 1: int, 2: string, 3: string}>
     *                                                                 Estructura: [header, ancho, ejemplo, descripción]
     */
    protected const COLUMNS = [
        ['Identificacion', 16, '1121898647', 'Cédula del empleado. Clave única — obligatoria.'],
        ['Nombre Completo', 32, 'Luis Guillermo Oviedo Ochoa', 'Nombre y apellidos como aparecen en nómina.'],
        ['Email', 32, 'luis.oviedo@confipetrol.com', 'Corporativo (@confipetrol.com) → cuenta Azure pendiente. Otro dominio o vacío → cuenta local.'],
        ['Cargo', 24, 'Líder de Proyecto', 'Cargo del empleado en la empresa.'],
        ['Departamento', 22, 'Tecnología', 'Nombre del departamento (se crea si no existe).'],
        ['Rol', 20, 'usuario_final', 'usuario_final | agente_soporte | supervisor_soporte | tecnico_campo | editor_kb | admin | super_admin.'],
        ['Telefono', 14, '3001234567', 'Teléfono de contacto (opcional).'],
        ['Gerencia', 22, 'Administración', 'Debe ser una de: Zona 1, Zona 2, Zona 3, Zona 4, Zona 5, Administración.'],
    ];

    protected const VALID_ROLES = [
        'usuario_final',
        'agente_soporte',
        'supervisor_soporte',
        'tecnico_campo',
        'editor_kb',
        'admin',
        'super_admin',
    ];

    public function build(): Spreadsheet
    {
        $sp = new Spreadsheet;
        $sp->getProperties()
            ->setCreator('Helpdesk Confipetrol')
            ->setTitle('Plantilla precarga de personas')
            ->setDescription('Plantilla oficial para precargar usuarios antes del inventario.');

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
        $sheet->setTitle('Personas');

        $columnsCount = count(self::COLUMNS);
        $lastColLetter = $this->columnLetter($columnsCount);

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

        // Fila de ejemplo (2) — en gris, para que se vea claro que no es dato real.
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

        // Dropdown de roles en columna F (Rol).
        $this->applyDropdown(
            $sheet,
            column: 'F',
            list: '"'.implode(',', self::VALID_ROLES).'"',
            prompt: 'Rol del usuario',
            promptTitle: 'Rol',
        );

        // Dropdown de gerencia en columna H — Sprint 7 lista cerrada.
        $this->applyDropdown(
            $sheet,
            column: 'H',
            list: '"'.implode(',', ManagementArea::values()).'"',
            prompt: 'Gerencia oficial de Confipetrol',
            promptTitle: 'Gerencia',
        );

        // Identificación en negrita en toda la columna A — es la clave.
        $sheet->getStyle('A2:A500')->getFont()->setBold(true);
    }

    protected function buildInstructionsSheet(Spreadsheet $sp): void
    {
        $sheet = $sp->createSheet();
        $sheet->setTitle('Instrucciones');

        $sheet->setCellValue('A1', 'Plantilla — Precarga de personas Confipetrol');
        $sheet->mergeCells('A1:C1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
        ]);

        $sheet->setCellValue('A3', '¿Para qué sirve esto?');
        $sheet->getStyle('A3')->getFont()->setBold(true);
        $sheet->setCellValue('A4', 'Precargar usuarios ANTES de subir el inventario. Cuando después');
        $sheet->setCellValue('A5', 'subas el archivo de activos, cada custodio quedará enlazado por');
        $sheet->setCellValue('A6', 'cédula sin crear duplicados.');

        $sheet->setCellValue('A8', 'Regla clave — tipo de cuenta según email:');
        $sheet->getStyle('A8')->getFont()->setBold(true);
        $sheet->setCellValue('A9', '• Email @confipetrol.com → cuenta "Azure pendiente".');
        $sheet->setCellValue('A10', '  El usuario NO puede login local. Cuando entre por primera vez');
        $sheet->setCellValue('A11', '  con el botón azul de Microsoft, la cuenta se enlaza sola.');
        $sheet->setCellValue('A12', '• Email de otro dominio (o vacío) → cuenta local.');
        $sheet->setCellValue('A13', '  Password inicial = primeros 8 dígitos de la cédula.');
        $sheet->setCellValue('A14', '  El usuario debe cambiarla en el primer login.');

        $sheet->setCellValue('A16', 'Cómo cargar:');
        $sheet->getStyle('A16')->getFont()->setBold(true);
        $sheet->setCellValue('A17', "1. Completá la hoja 'Personas' (podés borrar la fila de ejemplo).");
        $sheet->setCellValue('A18', '2. Guardá como .xlsx.');
        $sheet->setCellValue('A19', '3. En /admin/users, botón "📥 Precargar personas".');
        $sheet->setCellValue('A20', '4. Podés hacer un "dry-run" primero para ver qué pasaría sin escribir.');

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

        $required = ['Identificacion', 'Nombre Completo'];

        $row = 24;
        foreach (self::COLUMNS as $col) {
            $sheet->setCellValue("A{$row}", $col[0]);
            $sheet->setCellValue(
                "B{$row}",
                in_array($col[0], $required, true) ? 'Sí' : 'No',
            );
            $sheet->setCellValue("C{$row}", $col[3]);
            $sheet->getStyle("C{$row}")->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($row)->setRowHeight(-1);
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(14);
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
