/** Convierte 'YYYY-MM-DD' en 'DD/MM/YYYY' sin depender de la zona horaria. */
export function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const [y, m, d] = value.slice(0, 10).split('-');

    return `${d}/${m}/${y}`;
}

/** Periodo de una edición; las ediciones históricas sin fechas muestran el año. */
export function formatPeriod(
    start: string | null | undefined,
    end: string | null | undefined,
    year: number,
): string {
    if (!start) {
        return `Año ${year} (sin fechas)`;
    }

    return `${formatDate(start)} – ${formatDate(end)}`;
}
