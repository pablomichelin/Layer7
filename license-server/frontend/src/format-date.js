/**
 * Formata datas de calendário (expiry) sem deslocamento de fuso.
 * Aceita `YYYY-MM-DD`, ISO com tempo, ou Date do pg.
 */
export function formatCalendarDate(value, locale = 'pt-BR') {
  const iso = extractCalendarDate(value);
  if (!iso) {
    return '—';
  }

  const [year, month, day] = iso.split('-').map((part) => Number.parseInt(part, 10));
  if (!year || !month || !day) {
    return iso;
  }

  return new Intl.DateTimeFormat(locale, {
    timeZone: 'UTC',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(new Date(Date.UTC(year, month - 1, day)));
}

/**
 * Timestamps absolutos (created_at, activated_at) — locale local OK.
 */
export function formatDateTime(value, locale = 'pt-BR') {
  if (!value) {
    return '—';
  }

  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) {
    return '—';
  }

  return date.toLocaleString(locale);
}

export function extractCalendarDate(value) {
  if (!value) {
    return null;
  }

  if (value instanceof Date) {
    if (Number.isNaN(value.getTime())) {
      return null;
    }
    return value.toISOString().slice(0, 10);
  }

  if (typeof value === 'string') {
    const trimmed = value.trim();
    const match = trimmed.match(/^(\d{4}-\d{2}-\d{2})/);
    return match ? match[1] : null;
  }

  return null;
}
