@php
    // Montserrat (la misma letra del sistema). dompdf la registra desde estos archivos
    // y la guarda en storage/fonts. La URL es «file://» + ruta absoluta con barras normales
    // (en Windows queda file://C:/..., en Linux file:///var/...): con una barra de más, dompdf
    // no encuentra el archivo y vuelve a otra letra sin avisar.
    $fonts = str_replace(chr(92), '/', resource_path('fonts/montserrat'));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Certificado {{ $enrollment->registration_code }}</title>
    <style>
        /*
         * Hoja A4 horizontal: 297 × 210 mm. dompdf no respeta box-sizing ni
         * height: 100%, y calcula "bottom" contra la página aunque el bloque esté
         * anidado. Por eso cada bloque es hermano y se ubica con top/left/width/
         * height explícitos en mm: así nada se desborda ni crea una segunda página.
         */
        /* dompdf solo distingue los pesos "normal" y "bold" al elegir la variante. */
        @font-face { font-family: 'Montserrat'; font-style: normal; font-weight: normal; src: url('file://{{ $fonts }}/Montserrat-Regular.ttf') format('truetype'); }
        @font-face { font-family: 'Montserrat'; font-style: normal; font-weight: bold; src: url('file://{{ $fonts }}/Montserrat-Bold.ttf') format('truetype'); }
        @page { margin: 0; }
        html, body { margin: 0; padding: 0; }
        body { font-family: "Montserrat", "DejaVu Sans", sans-serif; color: #1f2937; }
        .frame { position: absolute; top: 12mm; left: 12mm; width: 271mm; height: 184mm; border: 3px solid #1e3a5f; }
        .frame-inner { position: absolute; top: 16mm; left: 16mm; width: 263mm; height: 176mm; border: 1px solid #c9a227; }
        .content { position: absolute; top: 28mm; left: 30mm; width: 237mm; }
        .brand { font-size: 13pt; letter-spacing: 3px; color: #1e3a5f; font-weight: bold; text-align: center; }
        .title { margin-top: 8mm; font-size: 34pt; color: #1e3a5f; text-align: center; letter-spacing: 2px; }
        .lead { margin-top: 6mm; font-size: 12pt; text-align: center; color: #4b5563; }
        .name { margin-top: 4mm; font-size: 26pt; font-weight: bold; text-align: center; color: #111827; }
        .course { margin-top: 4mm; font-size: 16pt; text-align: center; color: #1e3a5f; font-weight: bold; }
        .dates { margin-top: 3mm; font-size: 11pt; text-align: center; color: #4b5563; }
        .footer { position: absolute; top: 152mm; left: 30mm; width: 237mm; }
        .footer table { width: 100%; border-collapse: collapse; }
        .meta { font-size: 9pt; color: #374151; line-height: 1.6; vertical-align: bottom; }
        .meta strong { color: #111827; }
        .hash { font-family: "DejaVu Sans Mono", monospace; font-size: 7pt; color: #6b7280; }
        .qr { width: 32mm; text-align: center; vertical-align: bottom; }
        .qr img { width: 28mm; height: 28mm; }
        .qr small { display: block; font-size: 7pt; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
<div class="frame"></div>
<div class="frame-inner"></div>

<div class="content">
    <div class="brand">HORUS GROUP SRL</div>
    <div class="title">CERTIFICADO</div>
    <div class="lead">Se otorga el presente certificado a</div>
    <div class="name">{{ $student->full_name }}</div>
    <div class="lead">
        @if ($enrollment->isSpeaker())
            por su participación como ponente en la capacitación
        @else
            por haber aprobado satisfactoriamente la capacitación
        @endif
    </div>
    <div class="course">{{ $course->name }}</div>
    <div class="dates">
        Realizada {{ $edition->periodText() }}
        @if ($enrollment->grade !== null)
            · Nota final: {{ number_format((float) $enrollment->grade, 2) }}
        @endif
    </div>
</div>

<div class="footer">
    <table>
        <tr>
            <td class="meta">
                <strong>Código de registro:</strong> {{ $enrollment->registration_code }}<br>
                <strong>Fecha de emisión:</strong> {{ $certificate->issued_at->format('d/m/Y') }}<br>
                <strong>Verificación:</strong> {{ $verifyUrl }}<br>
                <span class="hash">ID {{ $certificate->unique_hash }}</span>
            </td>
            <td class="qr">
                <img src="{{ $qrDataUri }}" alt="Código QR de verificación">
                <small>Escanee para verificar</small>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
