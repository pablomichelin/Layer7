const { Router } = require('express');
const crypto = require('crypto');
const pool = require('../db');
const auth = require('../auth');
const {
  ADMIN_INTERNAL_ERROR_MESSAGE,
  auditAdminEvent,
} = require('../admin-surface');
const { generateSignedLicense } = require('../crypto');
const { createHttpError, isHttpError, runInTransaction } = require('../crud-integrity');
const {
  assertEmptyBody,
  normalizeStoredHardwareId,
  parseIdParam,
  parseLicenseCreatePayload,
  parseLicensesListQuery,
  parseLicenseUpdatePayload,
} = require('../crud-validation');
const {
  applyEffectiveLicenseState,
  getEffectiveLicenseState,
  LICENSE_SQL_ACTIVE_CONDITION,
  LICENSE_SQL_EXPIRED_CONDITION,
  LICENSE_SQL_REVOKED_CONDITION,
} = require('../license-state');

const router = Router();
router.use(auth);

async function ensureVisibleCustomer(client, customerId) {
  const result = await client.query(
    `SELECT id
       FROM customers
      WHERE id = $1
        AND archived_at IS NULL`,
    [customerId]
  );

  if (result.rows.length === 0) {
    throw createHttpError(404, 'Cliente nao encontrado.');
  }
}

function listChangedLicenseFields(existingLicense, payload) {
  const changedFields = [];

  if (Object.prototype.hasOwnProperty.call(payload, 'customerId')
    && payload.customerId !== existingLicense.customer_id) {
    changedFields.push('customer_id');
  }
  if (Object.prototype.hasOwnProperty.call(payload, 'expiry')
    && payload.expiry !== existingLicense.expiry) {
    changedFields.push('expiry');
  }
  if (Object.prototype.hasOwnProperty.call(payload, 'features')
    && payload.features !== (existingLicense.features || 'full')) {
    changedFields.push('features');
  }
  if (Object.prototype.hasOwnProperty.call(payload, 'notes')
    && payload.notes !== existingLicense.notes) {
    changedFields.push('notes');
  }

  return changedFields;
}

router.get('/', async (req, res) => {
  try {
    const { status, customerId, search, page, limit, offset } = parseLicensesListQuery(req.query);
    const conditions = ['l.archived_at IS NULL', '(c.id IS NULL OR c.archived_at IS NULL)'];
    const params = [];

    if (status === 'active') {
      conditions.push(LICENSE_SQL_ACTIVE_CONDITION);
    } else if (status === 'expired') {
      conditions.push(LICENSE_SQL_EXPIRED_CONDITION);
    } else if (status === 'revoked') {
      conditions.push(LICENSE_SQL_REVOKED_CONDITION);
    }

    if (customerId) {
      params.push(customerId);
      conditions.push(`l.customer_id = $${params.length}`);
    }

    if (search) {
      params.push(`%${search}%`);
      conditions.push(`(l.license_key ILIKE $${params.length} OR c.name ILIKE $${params.length})`);
    }

    const whereClause = `WHERE ${conditions.join(' AND ')}`;

    const countResult = await pool.query(
      `SELECT COUNT(*)
         FROM licenses l
         LEFT JOIN customers c ON c.id = l.customer_id
         ${whereClause}`,
      params
    );
    const total = Number.parseInt(countResult.rows[0].count, 10);

    const result = await pool.query(
      `SELECT l.*, c.name AS customer_name
         FROM licenses l
         LEFT JOIN customers c ON c.id = l.customer_id
         ${whereClause}
        ORDER BY l.created_at DESC
        LIMIT $${params.length + 1} OFFSET $${params.length + 2}`,
      [...params, limit, offset]
    );

    return res.json({
      licenses: result.rows.map(applyEffectiveLicenseState),
      total,
      page,
      limit,
      pages: Math.max(1, Math.ceil(total / limit)),
    });
  } catch (error) {
    if (isHttpError(error)) {
      return res.status(error.status).json({ error: error.message });
    }

    console.error('[LICENSES] List error:', error.message);
    return res.status(500).json({ error: 'Erro ao listar licencas.' });
  }
});

router.post('/', async (req, res) => {
  try {
    const payload = parseLicenseCreatePayload(req.body);

    const license = await runInTransaction(async (client) => {
      await ensureVisibleCustomer(client, payload.customerId);

      const licenseKey = crypto.randomBytes(16).toString('hex');
      const result = await client.query(
        `INSERT INTO licenses (customer_id, license_key, expiry, features, notes)
         VALUES ($1, $2, $3, $4, $5)
         RETURNING *`,
        [payload.customerId, licenseKey, payload.expiry, payload.features, payload.notes ?? null]
      );

      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_created',
        adminId: req.admin.id,
        actorIdentifier: req.admin.email,
        req,
        result: 'success',
        reason: 'license_created',
        metadata: {
          license_id: result.rows[0].id,
          customer_id: result.rows[0].customer_id,
        },
        client,
        strict: true,
      });

      return result.rows[0];
    });

    return res.status(201).json(applyEffectiveLicenseState(license));
  } catch (error) {
    if (isHttpError(error)) {
      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_create_denied',
        adminId: req.admin?.id || null,
        actorIdentifier: req.admin?.email || null,
        req,
        result: 'denied',
        reason: error.status === 404 ? 'customer_not_found' : 'invalid_payload',
        metadata: { status: error.status, error: error.message },
      });
      return res.status(error.status).json({ error: error.message });
    }

    console.error('[LICENSES] Create error:', error.message);
    await auditAdminEvent({
      component: 'licenses',
      eventType: 'license_create_error',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'error',
      reason: 'license_create_exception',
    });
    return res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

router.get('/:id', async (req, res) => {
  try {
    const licenseId = parseIdParam(req.params.id, 'license_id');

    const licenseResult = await pool.query(
      `SELECT l.*, c.name AS customer_name, c.email AS customer_email
         FROM licenses l
         LEFT JOIN customers c ON c.id = l.customer_id
        WHERE l.id = $1
          AND l.archived_at IS NULL
          AND (c.id IS NULL OR c.archived_at IS NULL)`,
      [licenseId]
    );

    if (licenseResult.rows.length === 0) {
      return res.status(404).json({ error: 'Licenca nao encontrada.' });
    }

    const activationsResult = await pool.query(
      `SELECT *
         FROM activations_log
        WHERE license_id = $1
        ORDER BY created_at DESC
        LIMIT 50`,
      [licenseId]
    );

    return res.json({
      license: applyEffectiveLicenseState(licenseResult.rows[0]),
      activations: activationsResult.rows,
    });
  } catch (error) {
    if (isHttpError(error)) {
      return res.status(error.status).json({ error: error.message });
    }

    console.error('[LICENSES] Detail error:', error.message);
    return res.status(500).json({ error: 'Erro ao buscar licenca.' });
  }
});

router.put('/:id', async (req, res) => {
  try {
    const licenseId = parseIdParam(req.params.id, 'license_id');
    const payload = parseLicenseUpdatePayload(req.body);

    const updatedLicense = await runInTransaction(async (client) => {
      const existingResult = await client.query(
        `SELECT *
           FROM licenses
          WHERE id = $1
            AND archived_at IS NULL
          FOR UPDATE`,
        [licenseId]
      );

      if (existingResult.rows.length === 0) {
        throw createHttpError(404, 'Licenca nao encontrada.');
      }

      const existingLicense = existingResult.rows[0];
      const existingState = getEffectiveLicenseState(existingLicense);
      const changedFields = listChangedLicenseFields(existingLicense, payload);

      if (changedFields.includes('customer_id')
        && (existingState.activated || Boolean(existingLicense.activated_at))) {
        throw createHttpError(
          409,
          'Licenca activada/bindada nao permite mudar customer_id. Crie nova licenca para outro cliente.'
        );
      }

      if (Object.prototype.hasOwnProperty.call(payload, 'customerId')) {
        await ensureVisibleCustomer(client, payload.customerId);
      }

      const updates = [];
      const params = [];

      if (Object.prototype.hasOwnProperty.call(payload, 'expiry')) {
        params.push(payload.expiry);
        updates.push(`expiry = $${params.length}`);
      }
      if (Object.prototype.hasOwnProperty.call(payload, 'features')) {
        params.push(payload.features);
        updates.push(`features = $${params.length}`);
      }
      if (Object.prototype.hasOwnProperty.call(payload, 'customerId')) {
        params.push(payload.customerId);
        updates.push(`customer_id = $${params.length}`);
      }
      if (Object.prototype.hasOwnProperty.call(payload, 'notes')) {
        params.push(payload.notes);
        updates.push(`notes = $${params.length}`);
      }

      updates.push('updated_at = NOW()');
      params.push(licenseId);

      const result = await client.query(
        `UPDATE licenses
            SET ${updates.join(', ')}
          WHERE id = $${params.length}
          RETURNING *`,
        params
      );

      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_updated',
        adminId: req.admin.id,
        actorIdentifier: req.admin.email,
        req,
        result: 'success',
        reason: 'license_updated',
        metadata: {
          license_id: result.rows[0].id,
          changed_fields: changedFields,
          activated: existingState.activated || Boolean(existingLicense.activated_at),
          bound: Boolean(existingState.normalizedHardwareId),
        },
        client,
        strict: true,
      });

      return result.rows[0];
    });

    return res.json(applyEffectiveLicenseState(updatedLicense));
  } catch (error) {
    if (isHttpError(error)) {
      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_update_denied',
        adminId: req.admin?.id || null,
        actorIdentifier: req.admin?.email || null,
        req,
        result: 'denied',
        reason: error.status === 404 ? 'resource_not_found' : 'license_update_rejected',
        metadata: { license_id: Number.parseInt(req.params.id, 10) || null, status: error.status, error: error.message },
      });
      return res.status(error.status).json({ error: error.message });
    }

    console.error('[LICENSES] Update error:', error.message);
    await auditAdminEvent({
      component: 'licenses',
      eventType: 'license_update_error',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'error',
      reason: 'license_update_exception',
      metadata: { license_id: Number.parseInt(req.params.id, 10) || null },
    });
    return res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

router.post('/:id/revoke', async (req, res) => {
  try {
    const licenseId = parseIdParam(req.params.id, 'license_id');
    assertEmptyBody(req.body);

    const revokedLicense = await runInTransaction(async (client) => {
      const existingResult = await client.query(
        `SELECT *
           FROM licenses
          WHERE id = $1
            AND archived_at IS NULL
          FOR UPDATE`,
        [licenseId]
      );

      if (existingResult.rows.length === 0) {
        throw createHttpError(404, 'Licenca nao encontrada.');
      }

      if (existingResult.rows[0].status === 'revoked') {
        throw createHttpError(409, 'Licenca ja revogada.');
      }

      const result = await client.query(
        `UPDATE licenses
            SET status = 'revoked',
                revoked_at = COALESCE(revoked_at, NOW()),
                updated_at = NOW()
          WHERE id = $1
          RETURNING *`,
        [licenseId]
      );

      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_revoked',
        adminId: req.admin.id,
        actorIdentifier: req.admin.email,
        req,
        result: 'success',
        reason: 'license_revoked',
        metadata: { license_id: result.rows[0].id },
        client,
        strict: true,
      });

      return result.rows[0];
    });

    return res.json(applyEffectiveLicenseState(revokedLicense));
  } catch (error) {
    if (isHttpError(error)) {
      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_revoke_denied',
        adminId: req.admin?.id || null,
        actorIdentifier: req.admin?.email || null,
        req,
        result: 'denied',
        reason: error.status === 404 ? 'license_not_found' : 'license_revoke_rejected',
        metadata: { license_id: Number.parseInt(req.params.id, 10) || null, status: error.status, error: error.message },
      });
      return res.status(error.status).json({ error: error.message });
    }

    console.error('[LICENSES] Revoke error:', error.message);
    await auditAdminEvent({
      component: 'licenses',
      eventType: 'license_revoke_error',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'error',
      reason: 'license_revoke_exception',
      metadata: { license_id: Number.parseInt(req.params.id, 10) || null },
    });
    return res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

router.delete('/:id', async (req, res) => {
  try {
    assertEmptyBody(req.body);
    const licenseId = parseIdParam(req.params.id, 'license_id');

    const archivedLicense = await runInTransaction(async (client) => {
      const existingResult = await client.query(
        `SELECT *
           FROM licenses
          WHERE id = $1
            AND archived_at IS NULL
          FOR UPDATE`,
        [licenseId]
      );

      if (existingResult.rows.length === 0) {
        throw createHttpError(404, 'Licenca nao encontrada.');
      }

      const existingLicense = applyEffectiveLicenseState(existingResult.rows[0]);
      if (existingLicense.status === 'active') {
        throw createHttpError(409, 'Nao e possivel arquivar licenca activa. Revogue primeiro.');
      }

      const result = await client.query(
        `UPDATE licenses
            SET archived_at = NOW(),
                archived_by_admin_id = $1,
                updated_at = NOW()
          WHERE id = $2
          RETURNING id`,
        [req.admin.id, licenseId]
      );

      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_archived',
        adminId: req.admin.id,
        actorIdentifier: req.admin.email,
        req,
        result: 'success',
        reason: 'license_archived',
        metadata: { license_id: licenseId },
        client,
        strict: true,
      });

      return result.rows[0];
    });

    return res.json({ message: 'Licenca arquivada.', id: archivedLicense.id });
  } catch (error) {
    if (isHttpError(error)) {
      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_delete_denied',
        adminId: req.admin?.id || null,
        actorIdentifier: req.admin?.email || null,
        req,
        result: 'denied',
        reason: error.status === 404 ? 'license_not_found' : 'license_archive_rejected',
        metadata: { license_id: Number.parseInt(req.params.id, 10) || null, status: error.status, error: error.message },
      });
      return res.status(error.status).json({ error: error.message });
    }

    console.error('[LICENSES] Delete error:', error.message);
    await auditAdminEvent({
      component: 'licenses',
      eventType: 'license_delete_error',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'error',
      reason: 'license_archive_exception',
      metadata: { license_id: Number.parseInt(req.params.id, 10) || null },
    });
    return res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

router.get('/:id/download', async (req, res) => {
  try {
    const licenseId = parseIdParam(req.params.id, 'license_id');

    const result = await pool.query(
      `SELECT l.*, c.name AS customer_name
         FROM licenses l
         LEFT JOIN customers c ON c.id = l.customer_id
        WHERE l.id = $1
          AND l.archived_at IS NULL
          AND (c.id IS NULL OR c.archived_at IS NULL)`,
      [licenseId]
    );

    if (result.rows.length === 0) {
      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_download_denied',
        adminId: req.admin.id,
        actorIdentifier: req.admin.email,
        req,
        result: 'denied',
        reason: 'license_not_found',
        metadata: { license_id: licenseId },
      });
      return res.status(404).json({ error: 'Licenca nao encontrada.' });
    }

    const license = applyEffectiveLicenseState(result.rows[0]);
    const effectiveHardwareId = normalizeStoredHardwareId(license.hardware_id) || license.hardware_id;

    if (!effectiveHardwareId) {
      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_download_denied',
        adminId: req.admin.id,
        actorIdentifier: req.admin.email,
        req,
        result: 'denied',
        reason: 'license_not_activated',
        metadata: { license_id: license.id },
      });
      return res.status(409).json({ error: 'Licenca ainda nao foi activada.' });
    }

    if (license.status !== 'active') {
      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_download_denied',
        adminId: req.admin.id,
        actorIdentifier: req.admin.email,
        req,
        result: 'denied',
        reason: 'license_state_invalid_for_download',
        metadata: { license_id: license.id, status: license.status },
      });
      return res.status(409).json({ error: 'Licenca nao esta em estado valido para download.' });
    }

    const signed = generateSignedLicense({
      hardware_id: effectiveHardwareId,
      expiry: new Date(license.expiry).toISOString().slice(0, 10),
      customer: license.customer_name || 'Unknown',
      features: license.features || 'full',
    });

    res.setHeader('Content-Type', 'application/json');
    res.setHeader('Content-Disposition', `attachment; filename="layer7-${license.license_key.slice(0, 8)}.lic"`);

    await auditAdminEvent({
      component: 'licenses',
      eventType: 'license_downloaded',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'success',
      reason: 'license_downloaded',
      metadata: { license_id: license.id },
    });

    return res.json(signed);
  } catch (error) {
    if (isHttpError(error)) {
      return res.status(error.status).json({ error: error.message });
    }

    console.error('[LICENSES] Download error:', error.message);
    await auditAdminEvent({
      component: 'licenses',
      eventType: 'license_download_error',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'error',
      reason: 'license_download_exception',
      metadata: { license_id: Number.parseInt(req.params.id, 10) || null },
    });
    return res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

module.exports = router;
