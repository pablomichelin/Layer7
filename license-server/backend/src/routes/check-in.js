const { Router } = require('express');
const rateLimit = require('express-rate-limit');
const pool = require('../db');
const {
  createActivationStateError,
  createHardwareBindingError,
} = require('../activation-policy');
const {
  buildActiveCheckInPayloadV2,
  buildActiveCheckInResponse,
  buildDeniedCheckInPayloadV2,
  buildDeniedCheckInResponse,
  getCheckInPolicy,
  mapActivationErrorToDeniedResponse,
  wrapSignedCheckInEnvelope,
} = require('../check-in-policy');
const { createHttpError, isHttpError, runInTransaction } = require('../crud-integrity');
const { loadLicenseForCheckIn } = require('../check-in-lookup');
const { getEffectiveLicenseState } = require('../license-state');
const { parseCheckInPayload } = require('../crud-validation');
const { getClientIp } = require('../session');

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

/**
 * 30.13 / D10: pedido com nonce → envelope assinado; sem nonce → JSON legado.
 */
function maybeSignCheckInBody(body, { nonce, hardwareId, nowSec, signFn } = {}) {
  if (!nonce || !hardwareId) {
    return body;
  }

  const status = body.status || 'fail';
  const errorMessage = body.error || 'Check-in negado.';
  return wrapSignedCheckInEnvelope(
    buildDeniedCheckInPayloadV2(status, errorMessage, {
      hardwareId,
      nonce,
      nowSec,
    }),
    { signFn, nowSec }
  );
}

router.post('/license/check-in', checkInLimiter, async (req, res) => {
  const ip = getClientIp(req);
  const ua = req.headers['user-agent'] || '';
  let requestedHardwareId = null;
  let requestedNonce = null;
  let effectiveState = null;

  try {
    const { key, hardwareId, nonce } = parseCheckInPayload(req.body);
    requestedHardwareId = hardwareId;
    requestedNonce = nonce;

    const responseBody = await runInTransaction(async (client) => {
      /* P1: serializa check-in com revoke/replace/rebind da mesma licenca. */
      const license = await loadLicenseForCheckIn(client, key, { forUpdate: true });
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
      await logCheckIn(client, license.id, hardwareId, ip, ua, 'active', null);

      if (nonce) {
        return wrapSignedCheckInEnvelope(
          buildActiveCheckInPayloadV2(license, policy, { hardwareId, nonce }),
          {}
        );
      }

      return buildActiveCheckInResponse(license, policy, { hardwareId });
    });

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

      const body = maybeSignCheckInBody(failure.body, {
        nonce: requestedNonce,
        hardwareId: requestedHardwareId,
      });

      return res.status(failure.status).json(body);
    }

    console.error('[CHECK-IN] Error:', error.message);
    const body = maybeSignCheckInBody(
      { error: 'Erro interno no check-in.' },
      {
        nonce: requestedNonce,
        hardwareId: requestedHardwareId,
      }
    );
    return res.status(500).json(body);
  }
});

module.exports = router;
