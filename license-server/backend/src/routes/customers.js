const { Router } = require('express');
const pool = require('../db');
const auth = require('../auth');

const router = Router();
router.use(auth);

router.get('/', async (req, res) => {
  try {
    const { search, page = 1, limit = 20 } = req.query;
    const offset = (Math.max(1, parseInt(page)) - 1) * parseInt(limit);
    const params = [];
    let idx = 1;
    let where = '';

    if (search) {
      where = `WHERE c.name ILIKE $${idx} OR c.email ILIKE $${idx}`;
      params.push(`%${search}%`);
      idx++;
    }

    const countResult = await pool.query(
      `SELECT COUNT(*) FROM customers c ${where}`, params
    );
    const total = parseInt(countResult.rows[0].count);

    params.push(parseInt(limit));
    params.push(offset);

    const result = await pool.query(
      `SELECT c.*,
              (SELECT COUNT(*) FROM licenses WHERE customer_id = c.id) AS license_count
       FROM customers c
       ${where}
       ORDER BY c.created_at DESC
       LIMIT $${idx++} OFFSET $${idx++}`,
      params
    );

    res.json({
      customers: result.rows,
      total,
      page: parseInt(page),
      limit: parseInt(limit),
      pages: Math.ceil(total / parseInt(limit)),
    });
  } catch (err) {
    console.error('[CUSTOMERS] List error:', err.message);
    res.status(500).json({ error: 'Erro ao listar clientes' });
  }
});

router.post('/', async (req, res) => {
  try {
    const { name, email, phone, notes } = req.body;
    if (!name) {
      return res.status(400).json({ error: 'Nome obrigatorio' });
    }

    const result = await pool.query(
      `INSERT INTO customers (name, email, phone, notes) VALUES ($1, $2, $3, $4) RETURNING *`,
      [name, email || null, phone || null, notes || null]
    );

    res.status(201).json(result.rows[0]);
  } catch (err) {
    console.error('[CUSTOMERS] Create error:', err.message);
    res.status(500).json({ error: 'Erro ao criar cliente' });
  }
});

router.get('/:id', async (req, res) => {
  try {
    const { id } = req.params;

    const custResult = await pool.query('SELECT * FROM customers WHERE id = $1', [id]);
    if (custResult.rows.length === 0) {
      return res.status(404).json({ error: 'Cliente nao encontrado' });
    }

    const licResult = await pool.query(
      'SELECT * FROM licenses WHERE customer_id = $1 ORDER BY created_at DESC',
      [id]
    );

    res.json({ customer: custResult.rows[0], licenses: licResult.rows });
  } catch (err) {
    console.error('[CUSTOMERS] Detail error:', err.message);
    res.status(500).json({ error: 'Erro ao buscar cliente' });
  }
});

router.put('/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const { name, email, phone, notes } = req.body;

    const result = await pool.query(
      `UPDATE customers
       SET name = COALESCE($1, name),
           email = COALESCE($2, email),
           phone = COALESCE($3, phone),
           notes = COALESCE($4, notes),
           updated_at = NOW()
       WHERE id = $5 RETURNING *`,
      [name, email, phone, notes, id]
    );

    if (result.rows.length === 0) {
      return res.status(404).json({ error: 'Cliente nao encontrado' });
    }

    res.json(result.rows[0]);
  } catch (err) {
    console.error('[CUSTOMERS] Update error:', err.message);
    res.status(500).json({ error: 'Erro ao actualizar cliente' });
  }
});

router.delete('/:id', async (req, res) => {
  try {
    const { id } = req.params;

    const licCheck = await pool.query(
      "SELECT COUNT(*) AS total, COUNT(*) FILTER (WHERE status = 'active') AS active FROM licenses WHERE customer_id = $1", [id]
    );
    const { total, active } = licCheck.rows[0];
    if (parseInt(active) > 0) {
      return res.status(409).json({ error: `Cliente possui ${active} licenca(s) activa(s). Revogue-as primeiro.` });
    }
    if (parseInt(total) > 0) {
      await pool.query('DELETE FROM activations_log WHERE license_id IN (SELECT id FROM licenses WHERE customer_id = $1)', [id]);
      await pool.query('DELETE FROM licenses WHERE customer_id = $1', [id]);
    }

    const result = await pool.query('DELETE FROM customers WHERE id = $1 RETURNING id', [id]);
    if (result.rows.length === 0) {
      return res.status(404).json({ error: 'Cliente nao encontrado' });
    }

    res.json({ message: 'Cliente removido', id: parseInt(id) });
  } catch (err) {
    console.error('[CUSTOMERS] Delete error:', err.message);
    res.status(500).json({ error: 'Erro ao remover cliente' });
  }
});

module.exports = router;
