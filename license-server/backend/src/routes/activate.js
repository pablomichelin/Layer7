const { Router } = require('express');
const rateLimit = require('express-rate-limit');
const pool = require('../db');
const { auditAdminEvent } = require('../admin-surface');
const { generateSignedLicense } = require('../crypto');
const { createHttpError, isHttpError, runInTransaction } = require('../crud-integrity');
const { buildLicenseArtifactAuditMetadata } = require('../license-artifact-audit');
const { getEffectiveLicenseState } = require('../license-state');
const {
  normalizeStoredHardwareId,
  parseActivatePayload,
} = require('../crud-validation');

const router = Router();

const activateLimiter = rateLimit({
  windowMs: 60 * 1000,
  max: 10,
  standardHeaders: true,
  legacyHeaders: false,
  message: { error: 'Demasiadas tentativas. Tente novamente em 1 minuto.' },
});

async function logActivation(queryable, licenseId, hardwareId, ip, ua, result, errorMessage) {
  await queryable.query(
    `INSERT INTO activations_log (license_id, hardware_id, ip_address, user_agent, result, error_message)
     VALUES ($1, $2, $3, $4, $5, $6)`,
    [licenseId, hardwareId, ip, ua, result, errorMessage]
  );
}

async function logActivationBestEffort(licenseId, hardwareId, ip, ua, result, errorMessage) {
  try {
    await logActivation(pool, licenseId, hardwareId, ip, ua, result, errorMessage);
  } catch (error) {
    console.error('[ACTIVATE] Log error:', error.message);
  }
}

router.post('/activate', activateLimiter, async (req, res) => {
  const ip = req.headers['x-real-ip'] || req.headers['x-forwarded-for'] || req.ip;
  const ua = req.headers['user-agent'] || '';
  let requestedHardwareId = null;

  try {
    const { key, hardwareId } = parseActivatePayload(req.body);
    requestedHardwareId = hardwareId;

    const activationResult = await runInTransaction(async (client) => {
      const result = await client.query(
        `SELECT l.*, c.name AS customer_name
           FROM licenses l
           LEFT JOIN customers c ON c.id = l.customer_id
          WHERE l.license_key = $1
            AND l.archived_at IS NULL
            AND (c.id IS NULL OR c.archived_at IS NULL)
          FOR UPDATE`,
        [key]
      );

      if (result.rows.length === 0) {
        throw createHttpError(404, 'Licenca nao encontrada.');
      }

      const license = result.rows[0];
      const effectiveState = getEffectiveLicenseState(license);
      const emissionKind = effectiveState.activated && license.activated_at
        ? 'reactivation_reissue'
        : 'initial_issue';

      if (effectiveState.revoked) {
        const error = createHttpError(409, 'Licenca revogada.');
        error.licenseId = license.id;
        throw error;
      }

      if (effectiveState.effectiveStatus === 'expired') {
        const error = createHttpError(409, 'Licenca expirada.');
        error.licenseId = license.id;
        throw error;
      }

      if (license.hardware_id) {
        const normalizedStoredHardwareId = normalizeStoredHardwareId(license.hardware_id);

        if (normalizedStoredHardwareId && normalizedStoredHardwareId !== license.hardware_id) {
          const normalizedResult = await client.query(
            `UPDATE licenses
                SET hardware_id = $1,
                    updated_at = NOW()
              WHERE id = $2
            RETURNING hardware_id`,
            [normalizedStoredHardwareId, license.id]
          );

          if (normalizedResult.rows.length > 0) {
            license.hardware_id = normalizedResult.rows[0].hardware_id;
          }
        }
      }

      const boundHardwareId = normalizeStoredHardwareId(license.hardware_id) || license.hardware_id;
      let effectiveHardwareId = boundHardwareId || hardwareId;

      if (boundHardwareId && boundHardwareId !== hardwareId) {
        const error = createHttpError(409, 'Hardware ID nao corresponde.');
        error.licenseId = license.id;
        throw error;
      }

      if (!boundHardwareId || !license.activated_at) {
        const updateResult = await client.query(
          `UPDATE licenses
              SET hardware_id = COALESCE(hardware_id, $1),
                  activated_at = COALESCE(activated_at, NOW()),
                  updated_at = NOW()
            WHERE id = $2
              AND (hardware_id IS NULL OR hardware_id = $1)
            RETURNING hardware_id`,
          [hardwareId, license.id]
        );

        if (updateResult.rows.length === 0) {
          const error = createHttpError(409, 'Hardware ID nao corresponde.');
          error.licenseId = license.id;
          throw error;
        }

        effectiveHardwareId =
          normalizeStoredHardwareId(updateResult.rows[0].hardware_id) ||
          updateResult.rows[0].hardware_id ||
          effectiveHardwareId;
      }

      const signed = generateSignedLicense({
        hardware_id: effectiveHardwareId,
        expiry: new Date(license.expiry).toISOString().slice(0, 10),
        customer: license.customer_name || 'Unknown',
        features: license.features || 'full',
      });

      await logActivation(client, license.id, effectiveHardwareId, ip, ua, 'success', null);

      return {
        signedLicense: signed,
        artifactAuditMetadata: buildLicenseArtifactAuditMetadata({
          license,
          signedLicense: signed,
          flow: 'public_activate',
          emissionKind,
          effectiveStatus: effectiveState.effectiveStatus,
          effectiveHardwareId,
          customerName: license.customer_name || 'Unknown',
        }),
      };
    });

    await auditAdminEvent({
      component: 'activate',
      eventType: 'license_artifact_issued',
      req,
      result: 'success',
      reason: activationResult.artifactAuditMetadata.emission_kind,
      metadata: activationResult.artifactAuditMetadata,
    });

    return res.json(activationResult.signedLicense);
  } catch (error) {
    if (isHttpError(error)) {
      await logActivationBestEffort(
        error.licenseId || null,
        requestedHardwareId,
        ip,
        ua,
        'fail',
        error.message
      );
      return res.status(error.status).json({ error: error.message });
    }

    console.error('[ACTIVATE] Error:', error.message);
    return res.status(500).json({ error: 'Erro interno na activacao.' });
  }
});

module.exports = router;
