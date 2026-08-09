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
const { buildLicenseArtifactAuditMetadata } = require('../license-artifact-audit');
const {
  createLicenseUpdateGuardError,
  listChangedLicenseFields,
} = require('../license-update-policy');
const {
  createLicenseDownloadGuardError,
  getDownloadHardwareId,
} = require('../license-download-policy');
const {
  computeRenewedExpiry,
  createLicenseRenewGuardError,
  parseRenewPayload,
} = require('../license-renew-policy');
const {
  createLicenseRebindGuardError,
  parseRebindPayload,
} = require('../license-rebind-policy');
const {
  buildReplacementNotes,
  createLicenseReplaceGuardError,
  parseReplacePayload,
  resolveReplacementExpiry,
} = require('../license-replace-policy');
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
const { assertPermission, requirePermission } = require('../require-permission');

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

router.get('/', requirePermission('licenses.read'), async (req, res) => {
  try {
    const {
      status,
      customerId,
      search,
      page,
      limit,
      offset,
      bound,
      expiringWithinDays,
      staleCheckinDays,
      format,
    } = parseLicensesListQuery(req.query);

    if (format === 'csv') {
      assertPermission(req.admin, 'licenses.export');
    }
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

    if (bound === true) {
      conditions.push(`l.hardware_id IS NOT NULL AND btrim(l.hardware_id) <> ''`);
    } else if (bound === false) {
      conditions.push(`(l.hardware_id IS NULL OR btrim(l.hardware_id) = '')`);
    }

    if (expiringWithinDays !== undefined) {
      params.push(expiringWithinDays);
      conditions.push(
        `${LICENSE_SQL_ACTIVE_CONDITION} AND l.expiry <= CURRENT_DATE + ($${params.length}::int * INTERVAL '1 day')`
      );
    }

    if (staleCheckinDays !== undefined) {
      params.push(staleCheckinDays);
      conditions.push(`l.hardware_id IS NOT NULL AND btrim(l.hardware_id) <> ''`);
      conditions.push(
        `(ci.last_check_in_at IS NULL OR ci.last_check_in_at < NOW() - ($${params.length}::int * INTERVAL '1 day'))`
      );
    }

    if (search) {
      params.push(`%${search}%`);
      conditions.push(
        `(l.license_key ILIKE $${params.length} OR c.name ILIKE $${params.length} OR COALESCE(l.hardware_id, '') ILIKE $${params.length} OR COALESCE(l.notes, '') ILIKE $${params.length})`
      );
    }

    const whereClause = `WHERE ${conditions.join(' AND ')}`;
    const fromClause = `
         FROM licenses l
         LEFT JOIN customers c ON c.id = l.customer_id
         LEFT JOIN LATERAL (
           SELECT MAX(created_at) AS last_check_in_at
             FROM check_ins_log
            WHERE license_id = l.id
         ) ci ON TRUE`;

    if (format === 'csv') {
      const csvResult = await pool.query(
        `SELECT l.id, l.license_key, c.name AS customer_name, l.features, l.expiry,
                l.status, l.hardware_id, l.notes, ci.last_check_in_at, l.created_at
                ${fromClause}
                ${whereClause}
         ORDER BY l.created_at DESC
         LIMIT 5000`,
        params
      );
      const header = [
        'id', 'license_key', 'customer_name', 'features', 'expiry',
        'status', 'hardware_id', 'notes', 'last_check_in_at', 'created_at',
      ];
      const escapeCsv = (value) => {
        if (value === null || value === undefined) return '';
        const text = String(value);
        if (/[",\n]/.test(text)) {
          return `"${text.replace(/"/g, '""')}"`;
        }
        return text;
      };
      const lines = [header.join(',')];
      for (const row of csvResult.rows.map(applyEffectiveLicenseState)) {
        lines.push(header.map((key) => escapeCsv(row[key])).join(','));
      }
      res.setHeader('Content-Type', 'text/csv; charset=utf-8');
      res.setHeader('Content-Disposition', 'attachment; filename="licenses.csv"');
      return res.send(lines.join('\n'));
    }

    const countResult = await pool.query(
      `SELECT COUNT(*)
         ${fromClause}
         ${whereClause}`,
      params
    );
    const total = Number.parseInt(countResult.rows[0].count, 10);

    const result = await pool.query(
      `SELECT l.*, c.name AS customer_name, ci.last_check_in_at
         ${fromClause}
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

router.post('/', requirePermission('licenses.create'), async (req, res) => {
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

router.get('/:id', requirePermission('licenses.read'), async (req, res) => {
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

    const checkInsResult = await pool.query(
      `SELECT *
         FROM check_ins_log
        WHERE license_id = $1
        ORDER BY created_at DESC
        LIMIT 50`,
      [licenseId]
    );

    return res.json({
      license: applyEffectiveLicenseState(licenseResult.rows[0]),
      activations: activationsResult.rows,
      check_ins: checkInsResult.rows,
    });
  } catch (error) {
    if (isHttpError(error)) {
      return res.status(error.status).json({ error: error.message });
    }

    console.error('[LICENSES] Detail error:', error.message);
    return res.status(500).json({ error: 'Erro ao buscar licenca.' });
  }
});

router.put('/:id', requirePermission('licenses.update'), async (req, res) => {
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
      const updateGuardError = createLicenseUpdateGuardError(existingLicense, changedFields);

      if (updateGuardError) {
        throw updateGuardError;
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

router.post('/:id/revoke', requirePermission('licenses.revoke'), async (req, res) => {
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

router.post('/:id/renew', requirePermission('licenses.renew'), async (req, res) => {
  try {
    const licenseId = parseIdParam(req.params.id, 'license_id');
    const { days } = parseRenewPayload(req.body);

    const renewed = await runInTransaction(async (client) => {
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
      const renewGuard = createLicenseRenewGuardError(existingLicense);
      if (renewGuard) {
        throw renewGuard;
      }

      const previousExpiry = existingLicense.expiry;
      const nextExpiry = computeRenewedExpiry(previousExpiry, days);

      const result = await client.query(
        `UPDATE licenses
            SET expiry = $1,
                status = CASE WHEN status = 'expired' THEN 'active' ELSE status END,
                updated_at = NOW()
          WHERE id = $2
          RETURNING *`,
        [nextExpiry, licenseId]
      );

      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_renewed',
        adminId: req.admin.id,
        actorIdentifier: req.admin.email,
        req,
        result: 'success',
        reason: 'license_renewed',
        metadata: {
          license_id: licenseId,
          days,
          previous_expiry: previousExpiry,
          new_expiry: nextExpiry,
          bound: Boolean(existingLicense.hardware_id),
        },
        client,
        strict: true,
      });

      return result.rows[0];
    });

    return res.json(applyEffectiveLicenseState(renewed));
  } catch (error) {
    if (isHttpError(error)) {
      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_renew_denied',
        adminId: req.admin?.id || null,
        actorIdentifier: req.admin?.email || null,
        req,
        result: 'denied',
        reason: error.status === 404 ? 'license_not_found' : 'license_renew_rejected',
        metadata: { license_id: Number.parseInt(req.params.id, 10) || null, status: error.status, error: error.message },
      });
      return res.status(error.status).json({ error: error.message });
    }

    console.error('[LICENSES] Renew error:', error.message);
    await auditAdminEvent({
      component: 'licenses',
      eventType: 'license_renew_error',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'error',
      reason: 'license_renew_exception',
      metadata: { license_id: Number.parseInt(req.params.id, 10) || null },
    });
    return res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

router.post('/:id/rebind', requirePermission('licenses.rebind'), async (req, res) => {
  try {
    const licenseId = parseIdParam(req.params.id, 'license_id');
    const payload = parseRebindPayload(req.body);

    const rebound = await runInTransaction(async (client) => {
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
      const rebindGuard = createLicenseRebindGuardError(existingLicense, payload);
      if (rebindGuard) {
        throw rebindGuard;
      }

      const previousHardwareId = normalizeStoredHardwareId(existingLicense.hardware_id)
        || existingLicense.hardware_id
        || null;

      let result;
      if (payload.mode === 'unbind') {
        result = await client.query(
          `UPDATE licenses
              SET hardware_id = NULL,
                  activated_at = NULL,
                  updated_at = NOW()
            WHERE id = $1
            RETURNING *`,
          [licenseId]
        );
      } else {
        result = await client.query(
          `UPDATE licenses
              SET hardware_id = $1,
                  activated_at = COALESCE(activated_at, NOW()),
                  updated_at = NOW()
            WHERE id = $2
            RETURNING *`,
          [payload.newHardwareId, licenseId]
        );
      }

      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_rebound',
        adminId: req.admin.id,
        actorIdentifier: req.admin.email,
        req,
        result: 'success',
        reason: 'license_rebound',
        metadata: {
          license_id: licenseId,
          mode: payload.mode,
          admin_reason: payload.reason,
          previous_hardware_id: previousHardwareId,
          new_hardware_id: payload.mode === 'set' ? payload.newHardwareId : null,
        },
        client,
        strict: true,
      });

      return result.rows[0];
    });

    return res.json(applyEffectiveLicenseState(rebound));
  } catch (error) {
    if (isHttpError(error)) {
      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_rebind_denied',
        adminId: req.admin?.id || null,
        actorIdentifier: req.admin?.email || null,
        req,
        result: 'denied',
        reason: error.status === 404 ? 'license_not_found' : 'license_rebind_rejected',
        metadata: { license_id: Number.parseInt(req.params.id, 10) || null, status: error.status, error: error.message },
      });
      return res.status(error.status).json({ error: error.message });
    }

    console.error('[LICENSES] Rebind error:', error.message);
    await auditAdminEvent({
      component: 'licenses',
      eventType: 'license_rebind_error',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'error',
      reason: 'license_rebind_exception',
      metadata: { license_id: Number.parseInt(req.params.id, 10) || null },
    });
    return res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

router.post('/:id/replace', requirePermission('licenses.replace'), async (req, res) => {
  try {
    const licenseId = parseIdParam(req.params.id, 'license_id');
    const payload = parseReplacePayload(req.body);

    const result = await runInTransaction(async (client) => {
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

      const previousLicense = existingResult.rows[0];
      const replaceGuard = createLicenseReplaceGuardError(previousLicense);
      if (replaceGuard) {
        throw replaceGuard;
      }

      await ensureVisibleCustomer(client, previousLicense.customer_id);

      const licenseKey = crypto.randomBytes(16).toString('hex');
      const expiry = resolveReplacementExpiry(previousLicense, payload.expiry);
      const notes = buildReplacementNotes(previousLicense, payload.reason);

      const createdResult = await client.query(
        `INSERT INTO licenses (customer_id, license_key, expiry, features, notes)
         VALUES ($1, $2, $3, $4, $5)
         RETURNING *`,
        [
          previousLicense.customer_id,
          licenseKey,
          expiry,
          previousLicense.features,
          notes,
        ]
      );

      const archivedResult = await client.query(
        `UPDATE licenses
            SET archived_at = NOW(),
                archived_by_admin_id = $1,
                updated_at = NOW()
          WHERE id = $2
          RETURNING id, license_key, status, revoked_at, archived_at`,
        [req.admin.id, licenseId]
      );

      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_replaced',
        adminId: req.admin.id,
        actorIdentifier: req.admin.email,
        req,
        result: 'success',
        reason: 'license_replaced',
        metadata: {
          previous_license_id: licenseId,
          new_license_id: createdResult.rows[0].id,
          reason: payload.reason,
          previous_license_key_prefix: String(previousLicense.license_key || '').slice(0, 8),
          new_license_key_prefix: String(createdResult.rows[0].license_key || '').slice(0, 8),
        },
        client,
        strict: true,
      });

      return {
        previous: archivedResult.rows[0],
        license: createdResult.rows[0],
      };
    });

    return res.status(201).json({
      previous: result.previous,
      license: applyEffectiveLicenseState(result.license),
      message: 'Licenca substituida. A chave antiga ficou arquivada; use a nova chave.',
    });
  } catch (error) {
    if (isHttpError(error)) {
      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_replace_denied',
        adminId: req.admin?.id || null,
        actorIdentifier: req.admin?.email || null,
        req,
        result: 'denied',
        reason: error.status === 404 ? 'license_not_found' : 'license_replace_rejected',
        metadata: {
          license_id: Number.parseInt(req.params.id, 10) || null,
          status: error.status,
          error: error.message,
        },
      });
      return res.status(error.status).json({ error: error.message });
    }

    console.error('[LICENSES] Replace error:', error.message);
    await auditAdminEvent({
      component: 'licenses',
      eventType: 'license_replace_error',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'error',
      reason: 'license_replace_exception',
      metadata: { license_id: Number.parseInt(req.params.id, 10) || null },
    });
    return res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

router.delete('/:id', requirePermission('licenses.archive'), async (req, res) => {
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

router.get('/:id/download', requirePermission('licenses.download'), async (req, res) => {
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
    const downloadGuard = createLicenseDownloadGuardError(license);
    if (downloadGuard) {
      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_download_denied',
        adminId: req.admin.id,
        actorIdentifier: req.admin.email,
        req,
        result: 'denied',
        reason: downloadGuard.reason,
        metadata: downloadGuard.metadata,
      });
      return res.status(downloadGuard.error.status).json({ error: downloadGuard.error.message });
    }

    const effectiveHardwareId = getDownloadHardwareId(license);

    const signed = generateSignedLicense({
      hardware_id: effectiveHardwareId,
      expiry: new Date(license.expiry).toISOString().slice(0, 10),
      customer: license.customer_name || 'Unknown',
      features: license.features || 'base',
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
      metadata: buildLicenseArtifactAuditMetadata({
        license,
        signedLicense: signed,
        flow: 'admin_download',
        emissionKind: 'admin_download_reissue',
        effectiveStatus: license.status,
        effectiveHardwareId,
        customerName: license.customer_name || 'Unknown',
      }),
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
