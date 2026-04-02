const { Router } = require('express');
const pool = require('../db');
const auth = require('../auth');
const {
  ADMIN_INTERNAL_ERROR_MESSAGE,
  auditAdminEvent,
} = require('../admin-surface');
const { createHttpError, isHttpError, runInTransaction } = require('../crud-integrity');
const {
  assertEmptyBody,
  parseCustomerCreatePayload,
  parseCustomersListQuery,
  parseCustomerUpdatePayload,
  parseIdParam,
} = require('../crud-validation');
const {
  applyEffectiveLicenseState,
  LICENSE_SQL_ACTIVE_CONDITION,
} = require('../license-state');

const router = Router();
router.use(auth);

router.get('/', async (req, res) => {
  try {
    const { search, page, limit, offset } = parseCustomersListQuery(req.query);
    const conditions = ['c.archived_at IS NULL'];
    const params = [];

    if (search) {
      params.push(`%${search}%`);
      conditions.push(`(c.name ILIKE $${params.length} OR c.email ILIKE $${params.length})`);
    }

    const whereClause = `WHERE ${conditions.join(' AND ')}`;

    const countResult = await pool.query(
      `SELECT COUNT(*) FROM customers c ${whereClause}`,
      params
    );
    const total = Number.parseInt(countResult.rows[0].count, 10);

    const result = await pool.query(
      `SELECT c.*,
              (
                SELECT COUNT(*)
                  FROM licenses l
                 WHERE l.customer_id = c.id
                   AND l.archived_at IS NULL
              ) AS license_count
         FROM customers c
         ${whereClause}
        ORDER BY c.created_at DESC
        LIMIT $${params.length + 1} OFFSET $${params.length + 2}`,
      [...params, limit, offset]
    );

    return res.json({
      customers: result.rows,
      total,
      page,
      limit,
      pages: Math.max(1, Math.ceil(total / limit)),
    });
  } catch (error) {
    if (isHttpError(error)) {
      return res.status(error.status).json({ error: error.message });
    }

    console.error('[CUSTOMERS] List error:', error.message);
    return res.status(500).json({ error: 'Erro ao listar clientes.' });
  }
});

router.post('/', async (req, res) => {
  try {
    const payload = parseCustomerCreatePayload(req.body);

    const customer = await runInTransaction(async (client) => {
      const result = await client.query(
        `INSERT INTO customers (name, email, phone, notes)
         VALUES ($1, $2, $3, $4)
         RETURNING *`,
        [payload.name, payload.email ?? null, payload.phone ?? null, payload.notes ?? null]
      );

      await auditAdminEvent({
        component: 'customers',
        eventType: 'customer_created',
        adminId: req.admin.id,
        actorIdentifier: req.admin.email,
        req,
        result: 'success',
        reason: 'customer_created',
        metadata: { customer_id: result.rows[0].id },
        client,
        strict: true,
      });

      return result.rows[0];
    });

    return res.status(201).json(customer);
  } catch (error) {
    if (isHttpError(error)) {
      await auditAdminEvent({
        component: 'customers',
        eventType: 'customer_create_denied',
        adminId: req.admin?.id || null,
        actorIdentifier: req.admin?.email || null,
        req,
        result: 'denied',
        reason: 'invalid_payload',
        metadata: { status: error.status, error: error.message },
      });
      return res.status(error.status).json({ error: error.message });
    }

    console.error('[CUSTOMERS] Create error:', error.message);
    await auditAdminEvent({
      component: 'customers',
      eventType: 'customer_create_error',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'error',
      reason: 'customer_create_exception',
    });
    return res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

router.get('/:id', async (req, res) => {
  try {
    const customerId = parseIdParam(req.params.id, 'customer_id');

    const customerResult = await pool.query(
      `SELECT *
         FROM customers
        WHERE id = $1
          AND archived_at IS NULL`,
      [customerId]
    );

    if (customerResult.rows.length === 0) {
      return res.status(404).json({ error: 'Cliente nao encontrado.' });
    }

    const licensesResult = await pool.query(
      `SELECT *
         FROM licenses
        WHERE customer_id = $1
          AND archived_at IS NULL
        ORDER BY created_at DESC`,
      [customerId]
    );

    return res.json({
      customer: customerResult.rows[0],
      licenses: licensesResult.rows.map(applyEffectiveLicenseState),
    });
  } catch (error) {
    if (isHttpError(error)) {
      return res.status(error.status).json({ error: error.message });
    }

    console.error('[CUSTOMERS] Detail error:', error.message);
    return res.status(500).json({ error: 'Erro ao buscar cliente.' });
  }
});

router.put('/:id', async (req, res) => {
  try {
    const customerId = parseIdParam(req.params.id, 'customer_id');
    const payload = parseCustomerUpdatePayload(req.body);

    const updatedCustomer = await runInTransaction(async (client) => {
      const existing = await client.query(
        `SELECT id
           FROM customers
          WHERE id = $1
            AND archived_at IS NULL
          FOR UPDATE`,
        [customerId]
      );

      if (existing.rows.length === 0) {
        throw createHttpError(404, 'Cliente nao encontrado.');
      }

      const updates = [];
      const params = [];

      if (Object.prototype.hasOwnProperty.call(payload, 'name')) {
        params.push(payload.name);
        updates.push(`name = $${params.length}`);
      }
      if (Object.prototype.hasOwnProperty.call(payload, 'email')) {
        params.push(payload.email);
        updates.push(`email = $${params.length}`);
      }
      if (Object.prototype.hasOwnProperty.call(payload, 'phone')) {
        params.push(payload.phone);
        updates.push(`phone = $${params.length}`);
      }
      if (Object.prototype.hasOwnProperty.call(payload, 'notes')) {
        params.push(payload.notes);
        updates.push(`notes = $${params.length}`);
      }

      updates.push('updated_at = NOW()');
      params.push(customerId);

      const result = await client.query(
        `UPDATE customers
            SET ${updates.join(', ')}
          WHERE id = $${params.length}
          RETURNING *`,
        params
      );

      await auditAdminEvent({
        component: 'customers',
        eventType: 'customer_updated',
        adminId: req.admin.id,
        actorIdentifier: req.admin.email,
        req,
        result: 'success',
        reason: 'customer_updated',
        metadata: { customer_id: result.rows[0].id },
        client,
        strict: true,
      });

      return result.rows[0];
    });

    return res.json(updatedCustomer);
  } catch (error) {
    if (isHttpError(error)) {
      await auditAdminEvent({
        component: 'customers',
        eventType: 'customer_update_denied',
        adminId: req.admin?.id || null,
        actorIdentifier: req.admin?.email || null,
        req,
        result: 'denied',
        reason: error.status === 404 ? 'customer_not_found' : 'customer_update_rejected',
        metadata: { customer_id: Number.parseInt(req.params.id, 10) || null, status: error.status, error: error.message },
      });
      return res.status(error.status).json({ error: error.message });
    }

    console.error('[CUSTOMERS] Update error:', error.message);
    await auditAdminEvent({
      component: 'customers',
      eventType: 'customer_update_error',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'error',
      reason: 'customer_update_exception',
      metadata: { customer_id: Number.parseInt(req.params.id, 10) || null },
    });
    return res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

router.delete('/:id', async (req, res) => {
  try {
    assertEmptyBody(req.body);
    const customerId = parseIdParam(req.params.id, 'customer_id');

    const archivedCustomer = await runInTransaction(async (client) => {
      const customerResult = await client.query(
        `SELECT id
           FROM customers
          WHERE id = $1
            AND archived_at IS NULL
          FOR UPDATE`,
        [customerId]
      );

      if (customerResult.rows.length === 0) {
        throw createHttpError(404, 'Cliente nao encontrado.');
      }

      const licenseStats = await client.query(
        `SELECT
            COUNT(*) FILTER (WHERE archived_at IS NULL) AS total,
            COUNT(*) FILTER (
              WHERE archived_at IS NULL
                AND ${LICENSE_SQL_ACTIVE_CONDITION}
            ) AS active
           FROM licenses
          WHERE customer_id = $1`,
        [customerId]
      );

      const totalLicenses = Number.parseInt(licenseStats.rows[0].total, 10);
      const activeLicenses = Number.parseInt(licenseStats.rows[0].active, 10);

      if (activeLicenses > 0) {
        throw createHttpError(409, `Cliente possui ${activeLicenses} licenca(s) activa(s). Revogue-as primeiro.`);
      }

      await client.query(
        `UPDATE licenses
            SET archived_at = NOW(),
                archived_by_admin_id = $1,
                updated_at = NOW()
          WHERE customer_id = $2
            AND archived_at IS NULL`,
        [req.admin.id, customerId]
      );

      const result = await client.query(
        `UPDATE customers
            SET archived_at = NOW(),
                archived_by_admin_id = $1,
                updated_at = NOW()
          WHERE id = $2
          RETURNING id`,
        [req.admin.id, customerId]
      );

      await auditAdminEvent({
        component: 'customers',
        eventType: 'customer_archived',
        adminId: req.admin.id,
        actorIdentifier: req.admin.email,
        req,
        result: 'success',
        reason: totalLicenses > 0 ? 'customer_archived_with_related_licenses' : 'customer_archived',
        metadata: {
          customer_id: customerId,
          archived_licenses: totalLicenses,
        },
        client,
        strict: true,
      });

      return result.rows[0];
    });

    return res.json({ message: 'Cliente arquivado.', id: archivedCustomer.id });
  } catch (error) {
    if (isHttpError(error)) {
      await auditAdminEvent({
        component: 'customers',
        eventType: 'customer_delete_denied',
        adminId: req.admin?.id || null,
        actorIdentifier: req.admin?.email || null,
        req,
        result: 'denied',
        reason: error.status === 404 ? 'customer_not_found' : 'customer_archive_rejected',
        metadata: { customer_id: Number.parseInt(req.params.id, 10) || null, status: error.status, error: error.message },
      });
      return res.status(error.status).json({ error: error.message });
    }

    console.error('[CUSTOMERS] Delete error:', error.message);
    await auditAdminEvent({
      component: 'customers',
      eventType: 'customer_delete_error',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'error',
      reason: 'customer_archive_exception',
      metadata: { customer_id: Number.parseInt(req.params.id, 10) || null },
    });
    return res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

module.exports = router;
