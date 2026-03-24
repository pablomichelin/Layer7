const { Router } = require('express');
const crypto = require('crypto');
const pool = require('../db');
const auth = require('../auth');
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
      return res.status(400).json({ error: 'customer_id e expiry obrigatorios' });
    }

    const license_key = crypto.randomBytes(16).toString('hex');

    const result = await pool.query(
      `INSERT INTO licenses (customer_id, license_key, expiry, features, notes)
       VALUES ($1, $2, $3, $4, $5) RETURNING *`,
      [customer_id, license_key, expiry, features, notes]
    );

    res.status(201).json(result.rows[0]);
  } catch (err) {
    console.error('[LICENSES] Create error:', err.message);
    res.status(500).json({ error: 'Erro ao criar licenca' });
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
      return res.status(404).json({ error: 'Licenca nao encontrada' });
    }

    res.json(result.rows[0]);
  } catch (err) {
    console.error('[LICENSES] Update error:', err.message);
    res.status(500).json({ error: 'Erro ao actualizar licenca' });
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
      return res.status(404).json({ error: 'Licenca nao encontrada ou ja revogada' });
    }

    res.json(result.rows[0]);
  } catch (err) {
    console.error('[LICENSES] Revoke error:', err.message);
    res.status(500).json({ error: 'Erro ao revogar licenca' });
  }
});

router.delete('/:id', async (req, res) => {
  try {
    const { id } = req.params;

    const lic = await pool.query('SELECT status FROM licenses WHERE id = $1', [id]);
    if (lic.rows.length === 0) {
      return res.status(404).json({ error: 'Licenca nao encontrada' });
    }
    if (lic.rows[0].status === 'active') {
      return res.status(409).json({ error: 'Nao e possivel apagar licenca activa. Revogue primeiro.' });
    }

    await pool.query('DELETE FROM activations_log WHERE license_id = $1', [id]);
    await pool.query('DELETE FROM licenses WHERE id = $1', [id]);

    res.json({ message: 'Licenca removida', id: parseInt(id) });
  } catch (err) {
    console.error('[LICENSES] Delete error:', err.message);
    res.status(500).json({ error: 'Erro ao remover licenca' });
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
      return res.status(404).json({ error: 'Licenca nao encontrada' });
    }

    const lic = result.rows[0];
    if (!lic.hardware_id) {
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
    res.json(signed);
  } catch (err) {
    console.error('[LICENSES] Download error:', err.message);
    res.status(500).json({ error: 'Erro ao gerar ficheiro .lic' });
  }
});

module.exports = router;
