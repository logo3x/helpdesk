<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Acta de Entrega — {{ $handover->acta_number }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 14mm 12mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5pt;
            line-height: 1.35;
            color: #000;
            margin: 0;
        }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        .header-table td { border: 1px solid #000; padding: 4px 8px; vertical-align: middle; }
        .logo-cell { width: 22%; text-align: center; }
        .logo-cell img { max-height: 44px; max-width: 100px; }
        .title-cell { font-weight: bold; text-align: center; font-size: 11pt; }
        .meta-cell { width: 28%; font-size: 8pt; }
        .meta-cell div { line-height: 1.4; }

        .meta-table { width: 100%; border-collapse: collapse; margin-top: 0; }
        .meta-table td { border: 1px solid #000; padding: 4px 8px; }
        .meta-table .label { width: 18%; font-weight: bold; font-size: 9pt; }
        .meta-table .value { font-size: 9pt; }

        .equipment-block {
            border: 1px solid #000;
            border-top: none;
            padding: 8px 10px;
            margin-bottom: 0;
        }
        .equipment-title { font-weight: bold; font-size: 9pt; margin-bottom: 6px; }
        .equipment-grid { width: 100%; border-collapse: collapse; }
        .equipment-grid td {
            padding: 3px 6px;
            font-size: 9pt;
            border: none;
            vertical-align: top;
        }
        .equipment-grid .field-label { font-weight: normal; width: 14%; }
        .equipment-grid .field-value { font-weight: bold; width: 32%; }

        .legal-text {
            border: 1px solid #000;
            border-top: none;
            padding: 8px 10px;
            font-size: 8.5pt;
            text-align: justify;
            line-height: 1.4;
        }
        .legal-text p { margin: 0 0 6px 0; }
        .legal-text p:last-child { margin-bottom: 0; }

        .signatures-table { width: 100%; border-collapse: collapse; margin-top: 0; }
        .signatures-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            font-size: 9pt;
            vertical-align: top;
        }
        .sig-header { text-align: center; font-weight: bold; height: 22px; }
        .sig-line { height: 60px; text-align: center; padding-top: 38px; font-style: italic; color: #555; }
        .sig-row { padding: 4px 6px; }
    </style>
</head>
<body>

{{-- ── Encabezado: logo + título + metadata del formato ── --}}
<table class="header-table">
    <tr>
        <td class="logo-cell" rowspan="2">
            @if (file_exists(public_path('images/logo-confipetrol-dark.png')))
                <img src="{{ public_path('images/logo-confipetrol-dark.png') }}" alt="Confipetrol">
            @else
                <strong>CONFIPETROL</strong>
            @endif
        </td>
        <td class="title-cell">CONFIPETROL</td>
        <td class="meta-cell" rowspan="2">
            <div><strong>Código:</strong> IT-ADM1-F-5</div>
            <div><strong>Versión:</strong> 3</div>
            <div><strong>Fecha:</strong> 24/07/2024</div>
            <div><strong>Página 1 de 1</strong></div>
        </td>
    </tr>
    <tr>
        <td class="title-cell">ACTA ENTREGA EQUIPOS IT</td>
    </tr>
</table>

{{-- ── Metadata del acta ── --}}
<table class="meta-table">
    <tr>
        <td class="label">FECHA</td>
        <td class="value">{{ $handover->delivered_at->translatedFormat('d/m/Y') }}</td>
    </tr>
    <tr>
        <td class="label">REFERENCIA</td>
        <td class="value">{{ $handover->reference ?? ('Entrega de '.strtoupper($handover->asset_type_snapshot ?? '')) }}</td>
    </tr>
    <tr>
        <td class="label">RECIBE</td>
        <td class="value">{{ strtoupper($handover->receivedBy?->name ?? '—') }}</td>
    </tr>
    <tr>
        <td class="label">CARGO</td>
        <td class="value">{{ strtoupper($handover->receivedBy?->position ?? '—') }}</td>
    </tr>
    <tr>
        <td class="label">UBICACIÓN</td>
        <td class="value">{{ strtoupper($handover->field_snapshot ?? '—') }}</td>
    </tr>
</table>

{{-- ── Detalle del equipo ── --}}
<div class="equipment-block">
    <div class="equipment-title">Detalle equipamiento:</div>
    <table class="equipment-grid">
        <tr>
            <td class="field-label">TAG:</td>
            <td class="field-value">{{ $handover->asset_tag_snapshot ?? '—' }}</td>
            <td class="field-label">Tipo Activo:</td>
            <td class="field-value">{{ strtoupper($handover->asset_type_snapshot ?? '—') }}</td>
        </tr>
        <tr>
            <td class="field-label">Fabricante:</td>
            <td class="field-value">{{ strtoupper($handover->manufacturer_snapshot ?? '—') }}</td>
            <td class="field-label">Modelo:</td>
            <td class="field-value">{{ strtoupper($handover->model_snapshot ?? '—') }}</td>
        </tr>
        <tr>
            <td class="field-label">Serial:</td>
            <td class="field-value">{{ $handover->serial_snapshot ?? '—' }}</td>
            <td class="field-label">Código SAP:</td>
            <td class="field-value">{{ $handover->sap_code_snapshot ?? '—' }}</td>
        </tr>
        <tr>
            <td class="field-label">Condición:</td>
            <td class="field-value">{{ strtoupper($handover->condition_at_delivery) }}</td>
            <td class="field-label">Campo:</td>
            <td class="field-value">{{ strtoupper($handover->field_snapshot ?? '—') }}</td>
        </tr>
        <tr>
            <td class="field-label">Proyecto:</td>
            <td class="field-value" colspan="3">
                {{ $handover->project?->code ?? '—' }}
                @if ($handover->project?->name)
                    &nbsp;&nbsp;<strong>{{ strtoupper($handover->project->name) }}</strong>
                @endif
            </td>
        </tr>
        <tr>
            <td class="field-label" style="vertical-align: top;">Observaciones:</td>
            <td class="field-value" colspan="3" style="font-weight: normal;">
                {{ $handover->observations ?? '' }}
            </td>
        </tr>
    </table>
</div>

{{-- ── Texto legal — copiado tal cual del formato oficial ── --}}
<div class="legal-text">
    <p>
        En cumplimiento de mis funciones y para facilitar el desarrollo de mis labores, la Empresa
        me ha asignado a partir de la fecha los elementos descritos según relación anexa, en la cual
        también se describe el estado en el que estoy recibiendo todos y cada uno de los elementos
        allí relacionados.
    </p>
    <p>
        Manifiesto expresamente que me comprometo a devolver los anteriores elementos en el mismo
        estado en el que los estoy recibiendo, salvo el deterioro natural de estos. En caso de no
        ser así, autorizo al Empleador para descontar de mi salario y/o liquidación el valor de las
        pérdidas y/o daños que ocasione a dichos elementos y que sean atribuidos al suscrito, de
        conformidad con lo señalado en el Reglamento Interno de Trabajo y/o en los Contratos de
        Trabajo respectivos.
    </p>
    <p>
        Igualmente me comprometo a hacer entrega de dichos elementos a más tardar al siguiente día
        de la terminación de mi contrato de trabajo y retiro efectivo y definitivo de la Empresa.
    </p>
    <p>
        En caso de no realizar dicha entrega en la oportunidad antes descrita, se entenderá que los
        elementos de propiedad de la compañía que se encuentren en mi poder están bajo la modalidad
        de alquiler hasta el momento en que realice la entrega efectiva, cuyo valor diario será
        establecido por la empresa, suma que autorizo sea descontada de mi salario y/o liquidación
        por parte del Empleador.
    </p>
    <p>
        Acepto y reconozco que el incumplimiento y negligencia en la NO entrega de los elementos a
        mí asignados, me crea la obligación de pagar el valor equivalente a los mismos.
    </p>
    <p>
        En caso de que el trabajador no restituya los bienes que le fueron entregados en custodia
        para el ejercicio de sus funciones en los términos y condiciones anteriormente fijadas, la
        empresa tendrá la libertad de iniciar el trámite de las acciones legales pertinentes.
    </p>
    <p>
        La empresa notifica y manifiesta al trabajador a través del presente documento, que el
        software y todas sus aplicaciones instaladas en los equipos aquí relacionados cuentan con
        sus respectivas licencias, de conformidad con lo exigido por la ley; por tanto se advierte
        al custodio del equipo el sumo cuidado y diligencia frente a este activo y la responsabilidad
        personal que asume frente a las autoridades por todo software instalado sin licencia de
        forma ilegal, sin previo aviso ni autorización de la empresa.
    </p>
</div>

{{-- ── Firmas ── --}}
<table class="signatures-table">
    <tr>
        <td class="sig-header" style="width: 50%;">Entrega<br>(Firma)</td>
        <td class="sig-header" style="width: 50%;">Recibe<br>(Firma)</td>
    </tr>
    <tr>
        <td class="sig-line">&nbsp;</td>
        <td class="sig-line">&nbsp;</td>
    </tr>
    <tr>
        <td class="sig-row"><strong>N° Identidad:</strong> {{ $handover->deliveredBy?->identification ?? '' }}</td>
        <td class="sig-row"><strong>N° Identidad:</strong> {{ $handover->receivedBy?->identification ?? '' }}</td>
    </tr>
    <tr>
        <td class="sig-row"><strong>Nombre:</strong> {{ strtoupper($handover->deliveredBy?->name ?? '') }}</td>
        <td class="sig-row"><strong>Nombre:</strong> {{ strtoupper($handover->receivedBy?->name ?? '') }}</td>
    </tr>
</table>

</body>
</html>
