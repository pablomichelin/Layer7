const { Router } = require('express');
const rateLimit = require('express-rate-limit');
const pool = require('../db');
const {
  createActivationStateError,
  createHardwareBindingError,
} = require('../activation-policy');
const {
  buildActiveCheckInResponse,
  buildDeniedCheckInResponse,
  getCheckInPolicy,
  mapActivationErrorToDeniedResponse,
} = require('../check-in-policy');
const { createHttpError, isHttpError } = require('../crud-integrity');
const { getEffectiveLicenseState } = require('../license-state');
const { parseActivatePayload } = require('../crud-validation');

const router = Router();

const checkInLimiter = rateLimit({
  windowMs: 60 * 1000,
  max: 10,
  standardHeaders: true,
  legacyHeaders: false,
  message: { error: 'Demasiadas tentativas. Tente novamente em 1 minuto.' },
});

async function logCheckIn(queryable, licenseId, hardwareId, ip, ua, result, errorMessage) {
  await queryable.query(
    `INSERT INTO check_ins_log (license_id, hardware_id, ip_address, user_agent, result, error_message)
     VALUES ($1, $2, $3, $4, $5, $6)`,
    [licenseId, hardwareId, ip, ua, result, errorMessage]
  );
}

async function logCheckInBestEffort(licenseId, hardwareId, ip, ua, result, errorMessage) {
  try {
    await logCheckIn(pool, licenseId, hardwareId, ip, ua, result, errorMessage);
  } catch (error) {
    console.error('[CHECK-IN] Log error:', error.message);
  }
}

function resolveCheckInFailure(error, effectiveState) {
  const deniedResponse = mapActivationErrorToDeniedResponse(error);
  if (deniedResponse) {
    return {
      status: 409,
      body: deniedResponse,
      logResult: deniedResponse.status,
    };
  }

  if (error.message === 'Licenca nao activada.') {
    return {
      status: 409,
      body: { error: error.message },
      logResult: 'fail',
    };
  }

  if (error.message === 'Hardware ID nao corresponde.') {
    return {
      status: 409,
      body: { error: error.message },
      logResult: 'fail',
    };
  }

  if (effectiveState?.effectiveStatus === 'revoked') {
    return {
      status: 409,
      body: buildDeniedCheckInResponse('revoked', 'Licenca revogada.'),
      logResult: 'revoked',
    };
  }

  if (effectiveState?.effectiveStatus === 'expired') {
    return {
      status: 409,
      body: buildDeniedCheckInResponse('expired', 'Licenca expirada.'),
      logResult: 'expired',
    };
  }

  return {
    status: error.status || 500,
    body: { error: error.message || 'Erro interno no check-in.' },
    logResult: 'fail',
  };
}

router.post('/license/check-in', checkInLimiter, async (req, res) => {
  const ip = req.headers['x-real-ip'] || req.headers['x-forwarded-for'] || req.ip;
  const ua = req.headers['user-agent'] || '';
  let requestedHardwareId = null;
  let effectiveState = null;

  try {
    const { key, hardwareId } = parseActivatePayload(req.body);
    requestedHardwareId = hardwareId;

    const result = await pool.query(
      `SELECT l.*, c.name AS customer_name
         FROM licenses l
         LEFT JOIN customers c ON c.id = l.customer_id
        WHERE l.license_key = $1
          AND l.archived_at IS NULL
          AND (c.id IS NULL OR c.archived_at IS NULL)`,
      [key]
    );

    if (result.rows.length === 0) {
      throw createHttpError(404, 'Licenca nao encontrada.');
    }

    const license = result.rows[0];
    effectiveState = getEffectiveLicenseState(license);

    if (!effectiveState.activated) {
      const error = createHttpError(409, 'Licenca nao activada.');
      error.licenseId = license.id;
      throw error;
    }

    const activationStateError = createActivationStateError(license, effectiveState);
    if (activationStateError) {
      throw activationStateError;
    }

    const hardwareBindingError = createHardwareBindingError(license, hardwareId);
    if (hardwareBindingError) {
      throw hardwareBindingError;
    }

    const policy = getCheckInPolicy();
    const responseBody = buildActiveCheckInResponse(license, policy);

    await logCheckIn(pool, license.id, hardwareId, ip, ua, 'active', null);

    return res.json(responseBody);
  } catch (error) {
    if (isHttpError(error)) {
      const failure = resolveCheckInFailure(error, effectiveState);

      await logCheckInBestEffort(
        error.licenseId || null,
        requestedHardwareId,
        ip,
        ua,
        failure.logResult,
        failure.body.error || error.message
      );

      return res.status(failure.status).json(failure.body);
    }

    console.error('[CHECK-IN] Error:', error.message);
    return res.status(500).json({ error: 'Erro interno no check-in.' });
  }
});

module.exports = router;
