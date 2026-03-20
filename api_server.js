const express = require('express');
const { getByDni, searchByName } = require('cuitonline');

const app = express();
const PORT = 3000;

app.use(express.json());

// Permitir llamadas desde PHP/WAMP local
app.use((req, res, next) => {
  res.set('Access-Control-Allow-Origin', '*');
  res.set('Access-Control-Allow-Headers', 'Content-Type');
  if (req.method === 'OPTIONS') return res.sendStatus(204);
  next();
});

// Buscar por DNI
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
    res.status(500).json({ error: 'Error al consultar cuitonline: ' + err.message });
  }
});

// Buscar por nombre
app.get('/api/nombre/:nombre', async (req, res) => {
  const { nombre } = req.params;
  try {
    const resultados = await searchByName(decodeURIComponent(nombre));
    res.json({ success: true, data: resultados });
  } catch (err) {
    res.status(500).json({ error: 'Error al consultar: ' + err.message });
  }
});

// Health check
app.get('/api/status', (req, res) => {
  res.json({ status: 'ok', message: 'API cuitonline corriendo correctamente' });
});

app.listen(PORT, () => {
  console.log(`✅ API cuitonline corriendo en http://localhost:${PORT}`);
  console.log(`   Endpoints disponibles:`);
  console.log(`   GET /api/dni/:dni`);
  console.log(`   GET /api/nombre/:nombre`);
  console.log(`   GET /api/status`);
});
