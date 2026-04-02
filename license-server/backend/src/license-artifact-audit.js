const crypto = require('crypto');

function sha256Hex(value) {
  return crypto.createHash('sha256').update(String(value), 'utf8').digest('hex');
}

function buildLicenseArtifactAuditMetadata({
  license,
  signedLicense,
  flow,
  emissionKind,
  effectiveStatus,
  effectiveHardwareId,
  customerName,
}) {
  const artifactPayload = JSON.parse(signedLicense.data);

  return {
    flow,
    emission_kind: emissionKind,
    license_id: license.id,
    customer_id: license.customer_id ?? null,
    license_key_prefix: typeof license.license_key === 'string'
      ? license.license_key.slice(0, 8)
      : null,
    effective_status: effectiveStatus,
    activated: Boolean(license.activated_at || effectiveHardwareId),
    bound: Boolean(effectiveHardwareId),
    hardware_id: effectiveHardwareId || null,
    expiry: artifactPayload.expiry || null,
    issued_on: artifactPayload.issued || null,
    customer_name: artifactPayload.customer || customerName || null,
    features: artifactPayload.features || license.features || 'full',
    artifact_payload_sha256: sha256Hex(signedLicense.data),
    artifact_sig_sha256: sha256Hex(signedLicense.sig),
    artifact_envelope_sha256: sha256Hex(JSON.stringify(signedLicense)),
  };
}

module.exports = {
  buildLicenseArtifactAuditMetadata,
};
