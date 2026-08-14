const { Router } = require('express');
const bcrypt = require('bcryptjs');
const pool = require('../db');
const auth = require('../auth');
const {
  ADMIN_INTERNAL_ERROR_MESSAGE,
  auditAdminEvent,
  normalizeAdminEmail,
} = require('../admin-surface');
const { createHttpError, isHttpError } = require('../crud-integrity');
const {
  listPermissionCatalog,
  normalizePermissions,
  toPublicAdmin,
} = require('../admin-permissions');
const { requirePermission } = require('../require-permission');
const { revokeActiveSessionsForAdmin } = require('../session');

const router = Router();
const BCRYPT_ROUNDS = 12;

router.use(auth);
router.use(requirePermission('users.manage'));

function normalizeRequiredName(value) {
  if (typeof value !== 'string' || !value.trim()) {
    throw createHttpError(400, 'Nome obrigatorio.');
  }
  const name = value.trim();
  if (name.length > 255) {
    throw createHttpError(400, 'Nome demasiado longo.');
  }
  return name;
}

function normalizePassword(value) {
  if (typeof value !== 'string' || value.length < 12) {
    throw createHttpError(400, 'Password deve ter pelo menos 12 caracteres.');
  }
  if (value.length > 200) {
    throw createHttpError(400, 'Password demasiado longa.');
  }
  return value;
}

router.get('/permissions', (_req, res) => {
  res.json({ permissions: listPermissionCatalog() });
});

router.get('/', async (req, res) => {
  try {
    const result = await pool.query(
      `SELECT id, email, name, is_owner, is_active, permissions, totp_enabled, created_at
         FROM admins
        ORDER BY is_owner DESC, name ASC`
    );

    return res.json({
      users: result.rows.map((row) => ({
        ...toPublicAdmin(row),
        created_at: row.created_at,
      })),
    });
  } catch (error) {
    console.error('[USERS] List error:', error.message);
    return res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

router.post('/', async (req, res) => {
  try {
    const email = normalizeAdminEmail(req.body?.email);
    const name = normalizeRequiredName(req.body?.name);
    const password = normalizePassword(req.body?.password);
    const permissions = normalizePermissions(req.body?.permissions, { allowUsersManage: false });

    if (!email) {
      throw createHttpError(400, 'Email invalido.');
    }

    const passwordHash = await bcrypt.hash(password, BCRYPT_ROUNDS);
    const result = await pool.query(
      `INSERT INTO admins (email, name, password_hash, is_owner, is_active, permissions)
       VALUES ($1, $2, $3, FALSE, TRUE, $4::jsonb)
       RETURNING id, email, name, is_owner, is_active, permissions, totp_enabled, created_at`,
      [email, name, passwordHash, JSON.stringify(permissions)]
    );

    const user = result.rows[0];
    await auditAdminEvent({
      component: 'users',
      eventType: 'user_created',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'success',
      reason: 'user_created',
      metadata: { user_id: user.id, email: user.email, permissions },
    });

    return res.status(201).json({
      user: {
        ...toPublicAdmin(user),
        created_at: user.created_at,
      },
    });
  } catch (error) {
    if (error?.code === '23505') {
      return res.status(409).json({ error: 'Email ja existe.' });
    }
    if (isHttpError(error)) {
      return res.status(error.status).json({ error: error.message });
    }
    console.error('[USERS] Create error:', error.message);
    return res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

router.put('/:id', async (req, res) => {
  try {
    const userId = Number.parseInt(req.params.id, 10);
    if (!Number.isInteger(userId) || userId <= 0) {
      throw createHttpError(400, 'ID invalido.');
    }

    const existing = await pool.query(
      `SELECT * FROM admins WHERE id = $1`,
      [userId]
    );
    if (existing.rows.length === 0) {
      throw createHttpError(404, 'Utilizador nao encontrado.');
    }

    const target = existing.rows[0];
    if (target.is_owner) {
      throw createHttpError(409, 'Conta owner nao pode ser editada por esta API.');
    }

    const updates = [];
    const params = [];

    if (Object.prototype.hasOwnProperty.call(req.body || {}, 'name')) {
      params.push(normalizeRequiredName(req.body.name));
      updates.push(`name = $${params.length}`);
    }

    if (Object.prototype.hasOwnProperty.call(req.body || {}, 'permissions')) {
      const permissions = normalizePermissions(req.body.permissions, { allowUsersManage: false });
      params.push(JSON.stringify(permissions));
      updates.push(`permissions = $${params.length}::jsonb`);
    }

    if (Object.prototype.hasOwnProperty.call(req.body || {}, 'is_active')) {
      if (typeof req.body.is_active !== 'boolean') {
        throw createHttpError(400, 'is_active invalido.');
      }
      params.push(req.body.is_active);
      updates.push(`is_active = $${params.length}`);
    }

    if (Object.prototype.hasOwnProperty.call(req.body || {}, 'password')) {
      const password = normalizePassword(req.body.password);
      const passwordHash = await bcrypt.hash(password, BCRYPT_ROUNDS);
      params.push(passwordHash);
      updates.push(`password_hash = $${params.length}`);
    }

    if (updates.length === 0) {
      throw createHttpError(400, 'Nenhum campo para actualizar.');
    }

    params.push(userId);
    const result = await pool.query(
      `UPDATE admins
          SET ${updates.join(', ')}
        WHERE id = $${params.length}
        RETURNING id, email, name, is_owner, is_active, permissions, totp_enabled, created_at`,
      params
    );

    const user = result.rows[0];
    if (user.is_active === false || Object.prototype.hasOwnProperty.call(req.body || {}, 'password')) {
      await revokeActiveSessionsForAdmin(user.id);
    }

    await auditAdminEvent({
      component: 'users',
      eventType: user.is_active ? 'user_updated' : 'user_disabled',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'success',
      reason: user.is_active ? 'user_updated' : 'user_disabled',
      metadata: {
        user_id: user.id,
        email: user.email,
        permissions: user.permissions,
        is_active: user.is_active,
      },
    });

    return res.json({
      user: {
        ...toPublicAdmin(user),
        created_at: user.created_at,
      },
    });
  } catch (error) {
    if (isHttpError(error)) {
      return res.status(error.status).json({ error: error.message });
    }
    console.error('[USERS] Update error:', error.message);
    return res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

module.exports = router;
