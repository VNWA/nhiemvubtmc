// Backend stores datetimes in UTC and serialises them as ISO 8601 with offset
// (e.g. "2026-04-26T15:16:39+00:00"). On the frontend we always render them
// in Vietnam time so the displayed value is consistent regardless of the
// viewer's browser/system timezone.

const DISPLAY_TIME_ZONE = 'Asia/Ho_Chi_Minh';
const DISPLAY_LOCALE = 'vi-VN';

function parseToDate(input: string | number | Date | null | undefined): Date | null {
    if (input === null || input === undefined || input === '') {
        return null;
    }
    const d = input instanceof Date ? input : new Date(input);
    return Number.isNaN(d.getTime()) ? null : d;
}

/**
 * Full date-time in Vietnam timezone, 24h format. e.g. "26/04/2026 22:16:39".
 * Returns the original input (or empty string) when parsing fails.
 */
export function formatVnDateTime(
    input: string | number | Date | null | undefined,
    overrides: Intl.DateTimeFormatOptions = {},
): string {
    const date = parseToDate(input);
    if (!date) {
        return typeof input === 'string' ? input : '';
    }
    return date.toLocaleString(DISPLAY_LOCALE, {
        timeZone: DISPLAY_TIME_ZONE,
        hour12: false,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        ...overrides,
    });
}

/**
 * Date only in Vietnam timezone. e.g. "26/04/2026".
 */
export function formatVnDate(
    input: string | number | Date | null | undefined,
    overrides: Intl.DateTimeFormatOptions = {},
): string {
    const date = parseToDate(input);
    if (!date) {
        return typeof input === 'string' ? input : '';
    }
    return date.toLocaleDateString(DISPLAY_LOCALE, {
        timeZone: DISPLAY_TIME_ZONE,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        ...overrides,
    });
}

/**
 * Time only in Vietnam timezone, 24h. e.g. "22:16:39".
 */
export function formatVnTime(
    input: string | number | Date | null | undefined,
    overrides: Intl.DateTimeFormatOptions = {},
): string {
    const date = parseToDate(input);
    if (!date) {
        return typeof input === 'string' ? input : '';
    }
    return date.toLocaleTimeString(DISPLAY_LOCALE, {
        timeZone: DISPLAY_TIME_ZONE,
        hour12: false,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        ...overrides,
    });
}

export const VN_TIMEZONE = DISPLAY_TIME_ZONE;
