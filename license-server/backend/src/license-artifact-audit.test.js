const test = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('crypto');

const { buildLicenseArtifactAuditMetadata } = require('./license-artifact-audit');

function sha256Hex(value) {
  return crypto.createHash('sha256').update(String(value), 'utf8').digest('hex');
}

test('buildLicenseArtifactAuditMetadata records activation artifact context', () => {
  const signedLicense = {
    data: JSON.stringify({
      hardware_id: 'a'.repeat(64),
      expiry: '2033-10-24',
      issued: '2026-04-14T17:00:00.000Z',
      customer: 'Systemup',
      features: 'full',
    }),
    sig: 'signed-payload',
  };

  const metadata = buildLicenseArtifactAuditMetadata({
    license: {
      id: 7,
      customer_id: 2,
      license_key: 'abcdef0123456789abcdef0123456789',
      activated_at: null,
      features: 'fallback-feature',
    },
    signedLicense,
    flow: 'public_activate',
    emissionKind: 'initial_issue',
    effectiveStatus: 'active',
    effectiveHardwareId: 'a'.repeat(64),
    customerName: 'Fallback Customer',
  });

  assert.equal(metadata.flow, 'public_activate');
  assert.equal(metadata.emission_kind, 'initial_issue');
  assert.equal(metadata.license_id, 7);
  assert.equal(metadata.customer_id, 2);
  assert.equal(metadata.license_key_prefix, 'abcdef01');
  assert.equal(metadata.effective_status, 'active');
  assert.equal(metadata.activated, true);
  assert.equal(metadata.bound, true);
  assert.equal(metadata.hardware_id, 'a'.repeat(64));
  assert.equal(metadata.expiry, '2033-10-24');
  assert.equal(metadata.issued_on, '2026-04-14T17:00:00.000Z');
  assert.equal(metadata.customer_name, 'Systemup');
  assert.equal(metadata.features, 'full');
  assert.equal(metadata.artifact_payload_sha256, sha256Hex(signedLicense.data));
  assert.equal(metadata.artifact_sig_sha256, sha256Hex(signedLicense.sig));
  assert.equal(metadata.artifact_envelope_sha256, sha256Hex(JSON.stringify(signedLicense)));
});

test('buildLicenseArtifactAuditMetadata records admin download reissue context with fallbacks', () => {
  const signedLicense = {
    data: JSON.stringify({
      expiry: '2026-12-31',
    }),
    sig: 'sig-only',
  };

  const metadata = buildLicenseArtifactAuditMetadata({
    license: {
      id: 8,
      customer_id: null,
      license_key: null,
      activated_at: '2026-04-14T12:00:00.000Z',
      features: 'reports,dns',
    },
    signedLicense,
    flow: 'admin_download',
    emissionKind: 'admin_download_reissue',
    effectiveStatus: 'active',
    effectiveHardwareId: null,
    customerName: 'Compasi',
  });

  assert.equal(metadata.flow, 'admin_download');
  assert.equal(metadata.emission_kind, 'admin_download_reissue');
  assert.equal(metadata.customer_id, null);
  assert.equal(metadata.license_key_prefix, null);
  assert.equal(metadata.activated, true);
  assert.equal(metadata.bound, false);
  assert.equal(metadata.hardware_id, null);
  assert.equal(metadata.customer_name, 'Compasi');
  assert.equal(metadata.features, 'reports,dns');
  assert.equal(metadata.artifact_payload_sha256, sha256Hex(signedLicense.data));
  assert.equal(metadata.artifact_sig_sha256, sha256Hex(signedLicense.sig));
  assert.equal(metadata.artifact_envelope_sha256, sha256Hex(JSON.stringify(signedLicense)));
});
