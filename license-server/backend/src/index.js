require('dotenv').config();
const express = require('express');

const {
  ADMIN_INTERNAL_ERROR_MESSAGE,
  adminNoStoreMiddleware,
  auditAdminEvent,
  enforceAdminOrigin,
  ensureAdminSurfaceSchema,
  isAdminApiPath,
} = require('./admin-surface');
const authRoutes = require('./routes/auth');
const activateRoutes = require('./routes/activate');
const checkInRoutes = require('./routes/check-in');
const { ensureCheckInSchema } = require('./check-in-schema');
const licensesRoutes = require('./routes/licenses');
const customersRoutes = require('./routes/customers');
const dashboardRoutes = require('./routes/dashboard');
const auditRoutes = require('./routes/audit');
const searchRoutes = require('./routes/search');
const { ensureCrudIntegritySchema } = require('./crud-integrity');
const { ensureSessionSchema } = require('./session');
const { ensureTotpSchema } = require('./totp-schema');
const { ensureUsersRbacSchema } = require('./users-rbac-schema');
const usersRoutes = require('./routes/users');
const contentRoutes = require('./routes/content');
const { assertRequiredAuthSecrets } = require('./admin-bearer-secret');

const app = express();
const PORT = process.env.PORT || 3001;

app.set('trust proxy', 1);
app.use(adminNoStoreMiddleware);
app.use(enforceAdminOrigin);
app.use(express.json());

app.get('/api/health', (_req, res) => {
  res.json({ status: 'ok', service: 'layer7-license-api', timestamp: new Date().toISOString() });
});

app.use('/api/auth', authRoutes);
app.use('/api', activateRoutes);
app.use('/api', checkInRoutes);
app.use('/api/licenses', licensesRoutes);
app.use('/api/customers', customersRoutes);
app.use('/api/dashboard', dashboardRoutes);
app.use('/api/audit', auditRoutes);
app.use('/api/search', searchRoutes);
app.use('/api/users', usersRoutes);
/* Prefixo /layer7/... (primary downloads) — auth Bearer; sem SPA fallback. */
app.use(contentRoutes);

app.use(async (err, req, res, _next) => {
  console.error('[API] Error:', err.message);

  if (err instanceof SyntaxError && err.type === 'entity.parse.failed') {
    return res.status(400).json({ error: 'JSON invalido.' });
  }

  if (isAdminApiPath(req.path)) {
    await auditAdminEvent({
      component: 'admin-surface',
      eventType: 'admin_route_error',
      adminId: req.admin?.id || null,
      actorIdentifier: req.admin?.email || null,
      req,
      result: 'error',
      reason: 'unhandled_exception',
    });
  }

  res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
});

async function startServer() {
  try {
    assertRequiredAuthSecrets();
    await ensureSessionSchema();
    await ensureAdminSurfaceSchema();
    await ensureCrudIntegritySchema();
    await ensureCheckInSchema();
    await ensureTotpSchema();
    await ensureUsersRbacSchema();
    app.listen(PORT, '0.0.0.0', () => {
      console.log(`[API] Layer7 License Server running on port ${PORT}`);
    });
  } catch (err) {
    console.error('[API] Startup error:', err.message);
    process.exit(1);
  }
}

startServer();
