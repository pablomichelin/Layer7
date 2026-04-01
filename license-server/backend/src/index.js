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
const licensesRoutes = require('./routes/licenses');
const customersRoutes = require('./routes/customers');
const dashboardRoutes = require('./routes/dashboard');
const { ensureSessionSchema } = require('./session');

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
app.use('/api/licenses', licensesRoutes);
app.use('/api/customers', customersRoutes);
app.use('/api/dashboard', dashboardRoutes);

app.use(async (err, req, res, _next) => {
  console.error('[API] Error:', err.message);

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
    await ensureSessionSchema();
    await ensureAdminSurfaceSchema();
    app.listen(PORT, '0.0.0.0', () => {
      console.log(`[API] Layer7 License Server running on port ${PORT}`);
    });
  } catch (err) {
    console.error('[API] Startup error:', err.message);
    process.exit(1);
  }
}

startServer();
