const { Router } = require('express');
const crypto = require('crypto');
const pool = require('../db');
const auth = require('../auth');
const {
  ADMIN_INTERNAL_ERROR_MESSAGE,
  auditAdminEvent,
} = require('../admin-surface');
const { generateSignedLicense } = require('../crypto');

const router = Router();
router.use(auth);

router.get('/', async (req, res) => {
  try {
    const { status, customer_id, search, page = 1, limit = 20 } = req.query;
    const offset = (Math.max(1, parseInt(page)) - 1) * parseInt(limit);
    const conditions = [];
    const params = [];
    let idx = 1;

    if (status) {
      conditions.push(`l.status = $${idx++}`);
      params.push(status);
    }
    if (customer_id) {
      conditions.push(`l.customer_id = $${idx++}`);
      params.push(parseInt(customer_id));
    }
    if (search) {
      conditions.push(`(l.license_key ILIKE $${idx} OR c.name ILIKE $${idx})`);
      params.push(`%${search}%`);
      idx++;
    }

    const where = conditions.length > 0 ? 'WHERE ' + conditions.join(' AND ') : '';

    const countResult = await pool.query(
      `SELECT COUNT(*) FROM licenses l LEFT JOIN customers c ON c.id = l.customer_id ${where}`, params
    );
    const total = parseInt(countResult.rows[0].count);

    params.push(parseInt(limit));
    params.push(offset);

    const result = await pool.query(
      `SELECT l.*, c.name AS customer_name
       FROM licenses l
       LEFT JOIN customers c ON c.id = l.customer_id
       ${where}
       ORDER BY l.created_at DESC
       LIMIT $${idx++} OFFSET $${idx++}`,
      params
    );

    res.json({
      licenses: result.rows,
      total,
      page: parseInt(page),
      limit: parseInt(limit),
      pages: Math.ceil(total / parseInt(limit)),
    });
  } catch (err) {
    console.error('[LICENSES] List error:', err.message);
    res.status(500).json({ error: 'Erro ao listar licencas' });
  }
});

router.post('/', async (req, res) => {
  try {
    const { customer_id, expiry, features = 'full', notes = '' } = req.body;
    if (!customer_id || !expiry) {
      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_create_denied',
        adminId: req.admin.id,
        actorIdentifier: req.admin.email,
        req,
        result: 'denied',
        reason: 'missing_required_fields',
      });
      return res.status(400).json({ error: 'customer_id e expiry obrigatorios' });
    }

    const license_key = crypto.randomBytes(16).toString('hex');

    const result = await pool.query(
      `INSERT INTO licenses (customer_id, license_key, expiry, features, notes)
       VALUES ($1, $2, $3, $4, $5) RETURNING *`,
      [customer_id, license_key, expiry, features, notes]
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
    });

    res.status(201).json(result.rows[0]);
  } catch (err) {
    console.error('[LICENSES] Create error:', err.message);
    await auditAdminEvent({
      component: 'licenses',
      eventType: 'license_create_error',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'error',
      reason: 'license_create_exception',
    });
    res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

router.get('/:id', async (req, res) => {
  try {
    const { id } = req.params;

    const licResult = await pool.query(
      `SELECT l.*, c.name AS customer_name, c.email AS customer_email
       FROM licenses l
       LEFT JOIN customers c ON c.id = l.customer_id
       WHERE l.id = $1`,
      [id]
    );

    if (licResult.rows.length === 0) {
      return res.status(404).json({ error: 'Licenca nao encontrada' });
    }

    const logResult = await pool.query(
      `SELECT * FROM activations_log WHERE license_id = $1 ORDER BY created_at DESC LIMIT 50`,
      [id]
    );

    res.json({ license: licResult.rows[0], activations: logResult.rows });
  } catch (err) {
    console.error('[LICENSES] Detail error:', err.message);
    res.status(500).json({ error: 'Erro ao buscar licenca' });
  }
});

router.put('/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const { expiry, features, customer_id, notes } = req.body;

    const result = await pool.query(
      `UPDATE licenses
       SET expiry = COALESCE($1, expiry),
           features = COALESCE($2, features),
           customer_id = COALESCE($3, customer_id),
           notes = COALESCE($4, notes),
           updated_at = NOW()
       WHERE id = $5 RETURNING *`,
      [expiry, features, customer_id, notes, id]
    );

    if (result.rows.length === 0) {
      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_update_denied',
        adminId: req.admin.id,
        actorIdentifier: req.admin.email,
        req,
        result: 'denied',
        reason: 'license_not_found',
        metadata: { license_id: parseInt(id, 10) },
      });
      return res.status(404).json({ error: 'Licenca nao encontrada' });
    }

    await auditAdminEvent({
      component: 'licenses',
      eventType: 'license_updated',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'success',
      reason: 'license_updated',
      metadata: { license_id: result.rows[0].id },
    });

    res.json(result.rows[0]);
  } catch (err) {
    console.error('[LICENSES] Update error:', err.message);
    await auditAdminEvent({
      component: 'licenses',
      eventType: 'license_update_error',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'error',
      reason: 'license_update_exception',
      metadata: { license_id: parseInt(req.params.id, 10) },
    });
    res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

router.post('/:id/revoke', async (req, res) => {
  try {
    const { id } = req.params;

    const result = await pool.query(
      `UPDATE licenses
       SET status = 'revoked', revoked_at = NOW(), updated_at = NOW()
       WHERE id = $1 AND status != 'revoked'
       RETURNING *`,
      [id]
    );

    if (result.rows.length === 0) {
      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_revoke_denied',
        adminId: req.admin.id,
        actorIdentifier: req.admin.email,
        req,
        result: 'denied',
        reason: 'license_not_found_or_already_revoked',
        metadata: { license_id: parseInt(id, 10) },
      });
      return res.status(404).json({ error: 'Licenca nao encontrada ou ja revogada' });
    }

    await auditAdminEvent({
      component: 'licenses',
      eventType: 'license_revoked',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'success',
      reason: 'license_revoked',
      metadata: { license_id: result.rows[0].id },
    });

    res.json(result.rows[0]);
  } catch (err) {
    console.error('[LICENSES] Revoke error:', err.message);
    await auditAdminEvent({
      component: 'licenses',
      eventType: 'license_revoke_error',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'error',
      reason: 'license_revoke_exception',
      metadata: { license_id: parseInt(req.params.id, 10) },
    });
    res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

router.delete('/:id', async (req, res) => {
  try {
    const { id } = req.params;

    const lic = await pool.query('SELECT status FROM licenses WHERE id = $1', [id]);
    if (lic.rows.length === 0) {
      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_delete_denied',
        adminId: req.admin.id,
        actorIdentifier: req.admin.email,
        req,
        result: 'denied',
        reason: 'license_not_found',
        metadata: { license_id: parseInt(id, 10) },
      });
      return res.status(404).json({ error: 'Licenca nao encontrada' });
    }
    if (lic.rows[0].status === 'active') {
      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_delete_denied',
        adminId: req.admin.id,
        actorIdentifier: req.admin.email,
        req,
        result: 'denied',
        reason: 'active_license_delete_blocked',
        metadata: { license_id: parseInt(id, 10) },
      });
      return res.status(409).json({ error: 'Nao e possivel apagar licenca activa. Revogue primeiro.' });
    }

    await pool.query('DELETE FROM activations_log WHERE license_id = $1', [id]);
    await pool.query('DELETE FROM licenses WHERE id = $1', [id]);

    await auditAdminEvent({
      component: 'licenses',
      eventType: 'license_deleted',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'success',
      reason: 'license_deleted',
      metadata: { license_id: parseInt(id, 10) },
    });

    res.json({ message: 'Licenca removida', id: parseInt(id) });
  } catch (err) {
    console.error('[LICENSES] Delete error:', err.message);
    await auditAdminEvent({
      component: 'licenses',
      eventType: 'license_delete_error',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'error',
      reason: 'license_delete_exception',
      metadata: { license_id: parseInt(req.params.id, 10) },
    });
    res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

router.get('/:id/download', async (req, res) => {
  try {
    const { id } = req.params;

    const result = await pool.query(
      `SELECT l.*, c.name AS customer_name
       FROM licenses l
       LEFT JOIN customers c ON c.id = l.customer_id
       WHERE l.id = $1`,
      [id]
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
        metadata: { license_id: parseInt(id, 10) },
      });
      return res.status(404).json({ error: 'Licenca nao encontrada' });
    }

    const lic = result.rows[0];
    if (!lic.hardware_id) {
      await auditAdminEvent({
        component: 'licenses',
        eventType: 'license_download_denied',
        adminId: req.admin.id,
        actorIdentifier: req.admin.email,
        req,
        result: 'denied',
        reason: 'license_not_activated',
        metadata: { license_id: lic.id },
      });
      return res.status(400).json({ error: 'Licenca ainda nao foi activada (sem hardware_id)' });
    }

    const signed = generateSignedLicense({
      hardware_id: lic.hardware_id,
      expiry: new Date(lic.expiry).toISOString().slice(0, 10),
      customer: lic.customer_name || 'Unknown',
      features: lic.features || 'full',
    });

    res.setHeader('Content-Type', 'application/json');
    res.setHeader('Content-Disposition', `attachment; filename="layer7-${lic.license_key.slice(0, 8)}.lic"`);
    await auditAdminEvent({
      component: 'licenses',
      eventType: 'license_downloaded',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'success',
      reason: 'license_downloaded',
      metadata: { license_id: lic.id },
    });
    res.json(signed);
  } catch (err) {
    console.error('[LICENSES] Download error:', err.message);
    await auditAdminEvent({
      component: 'licenses',
      eventType: 'license_download_error',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'error',
      reason: 'license_download_exception',
      metadata: { license_id: parseInt(req.params.id, 10) },
    });
    res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

module.exports = router;
