const test = require('node:test');
const assert = require('node:assert/strict');
const {
  buildReplacementNotes,
  createLicenseReplaceGuardError,
  parseReplacePayload,
  resolveReplacementExpiry,
} = require('./license-replace-policy');

test('parseReplacePayload requires reason and accepts optional expiry', () => {
  assert.throws(() => parseReplacePayload({}), /reason obrigatorio/);
  assert.throws(() => parseReplacePayload({ reason: 'curto' }), /pelo menos/);
  assert.throws(
    () => parseReplacePayload({ reason: 'Motivo valido longo', foo: 1 }),
    /nao permitidos/
  );

  const parsed = parseReplacePayload({
    reason: 'Cliente precisa de nova chave apos revogacao',
    expiry: '2027-06-01',
  });
  assert.equal(parsed.reason, 'Cliente precisa de nova chave apos revogacao');
  assert.equal(parsed.expiry, '2027-06-01');
});

test('createLicenseReplaceGuardError only allows revoked', () => {
  assert.equal(
    createLicenseReplaceGuardError({ status: 'active', expiry: '2027-01-01' })?.status,
    409
  );
  assert.equal(
    createLicenseReplaceGuardError({ status: 'expired', expiry: '2020-01-01' })?.status,
    409
  );
  assert.equal(
    createLicenseReplaceGuardError({ status: 'revoked', expiry: '2027-01-01', revoked_at: '2026-01-01' }),
    null
  );
});

test('buildReplacementNotes and resolveReplacementExpiry', () => {
  const notes = buildReplacementNotes(
    { id: 12, notes: 'Contrato A' },
    'Troca apos comprometimento de chave'
  );
  assert.match(notes, /Substitui #12/);
  assert.match(notes, /Contrato A/);

  assert.equal(
    resolveReplacementExpiry({ expiry: '2026-12-31' }, undefined),
    '2026-12-31'
  );
  assert.equal(
    resolveReplacementExpiry({ expiry: '2026-12-31' }, '2028-01-15'),
    '2028-01-15'
  );
});
