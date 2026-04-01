require('dotenv').config();
const express = require('express');
const cors = require('cors');

const authRoutes = require('./routes/auth');
const activateRoutes = require('./routes/activate');
const licensesRoutes = require('./routes/licenses');
const customersRoutes = require('./routes/customers');
const dashboardRoutes = require('./routes/dashboard');
const { ensureSessionSchema } = require('./session');

const app = express();
const PORT = process.env.PORT || 3001;

app.set('trust proxy', 1);
app.use(cors());
app.use(express.json());

app.get('/api/health', (_req, res) => {
  res.json({ status: 'ok', service: 'layer7-license-api', timestamp: new Date().toISOString() });
});

app.use('/api/auth', authRoutes);
app.use('/api', activateRoutes);
app.use('/api/licenses', licensesRoutes);
app.use('/api/customers', customersRoutes);
app.use('/api/dashboard', dashboardRoutes);

app.use((err, _req, res, _next) => {
  console.error('[API] Error:', err.message);
  res.status(500).json({ error: 'Internal server error' });
});

async function startServer() {
  try {
    await ensureSessionSchema();
    app.listen(PORT, '0.0.0.0', () => {
      console.log(`[API] Layer7 License Server running on port ${PORT}`);
    });
  } catch (err) {
    console.error('[API] Startup error:', err.message);
    process.exit(1);
  }
}

startServer();
