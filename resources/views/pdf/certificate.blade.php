<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Certificado {{ $enrollment->registration_code }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "DejaVu Sans", sans-serif; color: #1f2937; }
        .page { position: relative; width: 297mm; height: 210mm; padding: 14mm; }
        .frame { height: 100%; border: 3px solid #1e3a5f; padding: 12mm 16mm; position: relative; }
        .frame-inner { position: absolute; top: 3mm; right: 3mm; bottom: 3mm; left: 3mm; border: 1px solid #c9a227; }
        .brand { font-size: 13pt; letter-spacing: 3px; color: #1e3a5f; font-weight: bold; text-align: center; }
        .title { margin-top: 8mm; font-size: 34pt; color: #1e3a5f; text-align: center; letter-spacing: 2px; }
        .lead { margin-top: 6mm; font-size: 12pt; text-align: center; color: #4b5563; }
        .name { margin-top: 4mm; font-size: 26pt; font-weight: bold; text-align: center; color: #111827; }
        .course { margin-top: 4mm; font-size: 16pt; text-align: center; color: #1e3a5f; font-weight: bold; }
        .dates { margin-top: 3mm; font-size: 11pt; text-align: center; color: #4b5563; }
        .footer { position: absolute; left: 16mm; right: 16mm; bottom: 12mm; }
        .footer table { width: 100%; border-collapse: collapse; }
        .meta { font-size: 9pt; color: #374151; line-height: 1.6; vertical-align: bottom; }
        .meta strong { color: #111827; }
        .hash { font-family: "DejaVu Sans Mono", monospace; font-size: 7pt; color: #6b7280; word-break: break-all; }
        .qr { width: 32mm; text-align: right; vertical-align: bottom; }
        .qr img { width: 30mm; height: 30mm; }
        .qr small { display: block; font-size: 7pt; color: #6b7280; text-align: center; }
    </style>
</head>
<body>
<div class="page">
    <div class="frame">
        <div class="frame-inner"></div>

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
    </div>
</div>
</body>
</html>
