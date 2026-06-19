/**
 * Operational datetimes are naive wall-clock values in UTC columns: render
 * exactly as stored, never shifted to the viewer's zone. Passing the serialized
 * +00:00 (or bare date) to new Date() would shift it; wallClock() strips that.
 * Audit instants (created_at, etc.) are real UTC moments - use new Date() so
 * they localize.
 */
export function wallClock(iso: string): Date {
    // bare "YYYY-MM-DD": anchor at local noon so the date can't roll back
    if (/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
        return new Date(`${iso}T12:00:00`);
    }

    // datetime with or without an offset: drop it, keep the wall-clock
    return new Date(iso.slice(0, 19));
}

export function fmtWallDateTime(
    iso: string | null,
    opts: Intl.DateTimeFormatOptions = {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    },
): string {
    if (!iso) {
        return '-';
    }

    return wallClock(iso).toLocaleString(undefined, opts);
}
