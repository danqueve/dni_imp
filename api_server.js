require('dotenv').config();

const express    = require('express');
const morgan     = require('morgan');
const rateLimit  = require('express-rate-limit');
const { getByDni, searchByName } = require('cuitonline');

const app  = express();
const PORT = process.env.PORT || 3000;

app.use(express.json());
app.use(morgan('combined'));

// ── CORS restringido (configurable vía .env) ──────────────
const CORS_ORIGINS = (process.env.CORS_ORIGIN || 'http://localhost')
  .split(',').map(o => o.trim());

app.use((req, res, next) => {
  const origin = req.headers.origin;
  if (!origin || CORS_ORIGINS.includes(origin) || CORS_ORIGINS.includes('*')) {
    res.set('Access-Control-Allow-Origin', origin || CORS_ORIGINS[0]);
  }
  res.set('Access-Control-Allow-Headers', 'Content-Type');
  if (req.method === 'OPTIONS') return res.sendStatus(204);
  next();
});

// ── Rate limiting (60 req/min por IP) ─────────────────────
const limiter = rateLimit({
  windowMs:       60 * 1000,
  max:            parseInt(process.env.RATE_LIMIT_MAX) || 60,
  message:        { error: 'Demasiadas solicitudes. Esperá un momento e intentá de nuevo.' },
  standardHeaders: true,
  legacyHeaders:   false,
});
app.use('/api/dni',    limiter);
app.use('/api/nombre', limiter);

// ── Buscar por DNI ────────────────────────────────────────
app.get('/api/dni/:dni', async (req, res) => {
  const { dni } = req.params;

  if (!dni || !/^\d{7,8}$/.test(dni)) {
    return res.status(400).json({ error: 'DNI inválido. Debe tener 7 u 8 dígitos.' });
  }

  try {
    const resultado = await getByDni(dni);
    if (!resultado) {
      return res.status(404).json({ error: 'No se encontró ninguna persona con ese DNI.' });
    }
    res.json({ success: true, data: resultado });
  } catch (err) {
    console.error(`[ERROR] DNI ${dni}:`, err.message);
    res.status(500).json({ error: 'Error al consultar cuitonline. Intentá más tarde.' });
  }
});

// ── Buscar por nombre ─────────────────────────────────────
app.get('/api/nombre/:nombre', async (req, res) => {
  const nombre = decodeURIComponent(req.params.nombre).trim();

  if (!nombre || nombre.length < 3) {
    return res.status(400).json({ error: 'El nombre debe tener al menos 3 caracteres.' });
  }
  if (!/^[a-zA-ZáéíóúüñÁÉÍÓÚÜÑ\s]+$/.test(nombre)) {
    return res.status(400).json({ error: 'El nombre solo puede contener letras y espacios.' });
  }

  try {
    const resultados = await searchByName(nombre);
    res.json({ success: true, data: resultados });
  } catch (err) {
    console.error(`[ERROR] Nombre "${nombre}":`, err.message);
    res.status(500).json({ error: 'Error al consultar cuitonline. Intentá más tarde.' });
  }
});

// ── Health check ──────────────────────────────────────────
app.get('/api/status', (req, res) => {
  res.json({
    status:    'ok',
    message:   'API cuitonline corriendo correctamente',
    timestamp: new Date().toISOString(),
  });
});

app.listen(PORT, () => {
  console.log(`✅ API cuitonline corriendo en http://localhost:${PORT}`);
  console.log(`   GET /api/dni/:dni`);
  console.log(`   GET /api/nombre/:nombre`);
  console.log(`   GET /api/status`);
});
