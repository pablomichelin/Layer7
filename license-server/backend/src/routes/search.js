const { Router } = require('express');
const pool = require('../db');
const auth = require('../auth');
const { createHttpError, isHttpError } = require('../crud-integrity');
const { requirePermission } = require('../require-permission');

const router = Router();
router.use(auth);
router.use(requirePermission('search.read'));

function parseSearchQuery(value) {
  if (typeof value !== 'string') {
    throw createHttpError(400, 'q invalido.');
  }
  const q = value.trim();
  if (q.length < 2 || q.length > 120) {
    throw createHttpError(400, 'q deve ter entre 2 e 120 caracteres.');
  }
  return q;
}

router.get('/', async (req, res) => {
  try {
    const q = parseSearchQuery(req.query.q);
    const pattern = `%${q}%`;

    const [customers, licenses] = await Promise.all([
      pool.query(
        `SELECT id, name, email, cnpj
           FROM customers
          WHERE archived_at IS NULL
            AND (
              name ILIKE $1
              OR COALESCE(email, '') ILIKE $1
              OR COALESCE(cnpj, '') ILIKE $1
              OR COALESCE(tags, '') ILIKE $1
            )
          ORDER BY name ASC
          LIMIT 8`,
        [pattern]
      ),
      pool.query(
        `SELECT l.id, l.license_key, l.status, l.hardware_id, c.name AS customer_name, c.id AS customer_id
           FROM licenses l
           LEFT JOIN customers c ON c.id = l.customer_id
          WHERE l.archived_at IS NULL
            AND (c.id IS NULL OR c.archived_at IS NULL)
            AND (
              l.license_key ILIKE $1
              OR COALESCE(l.hardware_id, '') ILIKE $1
              OR COALESCE(c.name, '') ILIKE $1
              OR COALESCE(c.cnpj, '') ILIKE $1
            )
          ORDER BY l.created_at DESC
          LIMIT 8`,
        [pattern]
      ),
    ]);

    return res.json({
      q,
      customers: customers.rows,
      licenses: licenses.rows,
    });
  } catch (error) {
    if (isHttpError(error)) {
      return res.status(error.status).json({ error: error.message });
    }
    console.error('[SEARCH] Error:', error.message);
    return res.status(500).json({ error: 'Erro na busca.' });
  }
});

module.exports = router;
