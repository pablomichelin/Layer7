import test from 'node:test';
import assert from 'node:assert/strict';
import { extractCalendarDate, formatCalendarDate } from './format-date.js';

test('extractCalendarDate keeps YYYY-MM-DD from strings and Dates', () => {
  assert.equal(extractCalendarDate('2026-12-31'), '2026-12-31');
  assert.equal(extractCalendarDate('2026-12-31T00:00:00.000Z'), '2026-12-31');
  assert.equal(extractCalendarDate(new Date('2026-12-31T00:00:00.000Z')), '2026-12-31');
  assert.equal(extractCalendarDate(null), null);
});

test('formatCalendarDate does not shift calendar day in BRT-like environments', () => {
  // Sem timezone local: formata via UTC explícito → 31/12/2026
  assert.equal(formatCalendarDate('2026-12-31'), '31/12/2026');
  assert.equal(formatCalendarDate('2026-12-31T00:00:00.000Z'), '31/12/2026');
  // Contraste: Date local pode mostrar 30/12 — o helper não deve.
  const naive = new Date('2026-12-31').toLocaleDateString('pt-BR');
  if (naive !== '31/12/2026') {
    assert.notEqual(formatCalendarDate('2026-12-31'), naive);
  }
});
