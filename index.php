<?php
// ============================================================
// config.php inline — ajustá estos valores si cambian
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');               // WAMP por defecto no tiene contraseña
define('DB_NAME', 'consultas_dni');
define('API_URL', 'http://localhost:3000');

// ============================================================
// Conexión a MySQL
// ============================================================
function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        return null;
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

// ============================================================
// Llamada a la API de Node.js
// ============================================================
function consultarAPI($endpoint) {
    $url = API_URL . $endpoint;
    $ctx = stream_context_create([
        'http' => [
            'timeout'        => 15,
            'ignore_errors'  => true,
        ]
    ]);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) return ['error' => 'No se pudo conectar con la API. ¿Está corriendo el servidor Node.js?'];
    return json_decode($response, true);
}

// ============================================================
// Guardar en base de datos
// ============================================================
function guardarPersona($data) {
    $db = getDB();
    if (!$db) return false;

    $stmt = $db->prepare("
        INSERT INTO personas (dni, nombre, cuit_cuil, tipo_persona, genero, nacionalidad, localidad, empleador, ip_consulta)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'localhost';
    $stmt->bind_param('sssssssss',
        $data['dni'],
        $data['name']        ?? null,
        $data['cuit']        ?? null,
        $data['personType']  ?? null,
        $data['gender']      ?? null,
        $data['nationality'] ?? null,
        $data['locality']    ?? null,
        $data['employer']    ?? null,
        $ip
    );
    $ok = $stmt->execute();
    $stmt->close();
    $db->close();
    return $ok;
}

// ============================================================
// Obtener historial
// ============================================================
function getHistorial($limit = 10) {
    $db = getDB();
    if (!$db) return [];
    $result = $db->query("
        SELECT id, dni, nombre, cuit_cuil, tipo_persona, localidad,
               DATE_FORMAT(fecha_consulta, '%d/%m/%Y %H:%i') AS fecha
        FROM personas
        ORDER BY fecha_consulta DESC
        LIMIT $limit
    ");
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $db->close();
    return $rows;
}

// ============================================================
// Procesar petición AJAX
// ============================================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    if ($_GET['action'] === 'buscar_dni' && isset($_GET['dni'])) {
        $dni = preg_replace('/\D/', '', trim($_GET['dni']));
        if (strlen($dni) < 7 || strlen($dni) > 8) {
            echo json_encode(['error' => 'El DNI debe tener 7 u 8 dígitos.']);
            exit;
        }
        $resultado = consultarAPI('/api/dni/' . $dni);

        if (isset($resultado['success']) && $resultado['success']) {
            $persona = $resultado['data'];
            $persona['dni'] = $dni;
            guardarPersona($persona);
            echo json_encode(['success' => true, 'data' => $persona]);
        } else {
            echo json_encode($resultado);
        }
        exit;
    }

    if ($_GET['action'] === 'historial') {
        echo json_encode(getHistorial(20));
        exit;
    }

    if ($_GET['action'] === 'eliminar' && isset($_GET['id'])) {
        $db = getDB();
        $id = intval($_GET['id']);
        $db->query("DELETE FROM personas WHERE id = $id");
        $db->close();
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['error' => 'Acción no válida']);
    exit;
}

$historial = getHistorial(10);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Consulta DNI · Argentina</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg:        #0a0c10;
      --surface:   #111318;
      --border:    #1e2230;
      --accent:    #4f7cff;
      --accent2:   #00e5b0;
      --danger:    #ff4f6a;
      --text:      #e8eaf0;
      --muted:     #5a6080;
      --card:      #13161f;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      background: var(--bg);
      color: var(--text);
      font-family: 'DM Mono', monospace;
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* ── Fondo animado ── */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background:
        radial-gradient(ellipse 60% 40% at 20% 10%, rgba(79,124,255,.12) 0%, transparent 60%),
        radial-gradient(ellipse 40% 30% at 80% 80%, rgba(0,229,176,.08) 0%, transparent 50%);
      pointer-events: none;
      z-index: 0;
    }

    .wrapper {
      position: relative;
      z-index: 1;
      max-width: 860px;
      margin: 0 auto;
      padding: 2.5rem 1.5rem 4rem;
    }

    /* ── Header ── */
    header {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 2.8rem;
      padding-bottom: 1.5rem;
      border-bottom: 1px solid var(--border);
    }
    .logo {
      width: 42px; height: 42px;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      border-radius: 10px;
      display: grid; place-items: center;
      font-family: 'Syne', sans-serif;
      font-weight: 800;
      font-size: 1.1rem;
      flex-shrink: 0;
    }
    header h1 {
      font-family: 'Syne', sans-serif;
      font-size: 1.4rem;
      font-weight: 700;
      letter-spacing: -.01em;
    }
    header span {
      font-size: .75rem;
      color: var(--muted);
      display: block;
      margin-top: 2px;
    }
    .badge-api {
      margin-left: auto;
      padding: .3rem .75rem;
      border-radius: 999px;
      font-size: .68rem;
      letter-spacing: .06em;
      text-transform: uppercase;
      border: 1px solid var(--border);
      color: var(--muted);
      cursor: pointer;
      transition: all .2s;
    }
    .badge-api.online  { border-color: var(--accent2); color: var(--accent2); }
    .badge-api.offline { border-color: var(--danger);  color: var(--danger);  }

    /* ── Search box ── */
    .search-section {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 2rem;
      margin-bottom: 2rem;
    }
    .search-section h2 {
      font-family: 'Syne', sans-serif;
      font-size: 1rem;
      font-weight: 600;
      margin-bottom: 1.2rem;
      color: var(--text);
    }
    .input-row {
      display: flex;
      gap: .75rem;
      flex-wrap: wrap;
    }
    .input-wrap {
      position: relative;
      flex: 1;
      min-width: 200px;
    }
    .input-wrap label {
      position: absolute;
      top: -9px; left: 12px;
      font-size: .65rem;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: var(--accent);
      background: var(--card);
      padding: 0 4px;
    }
    input[type="text"] {
      width: 100%;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 10px;
      color: var(--text);
      font-family: 'DM Mono', monospace;
      font-size: 1.1rem;
      padding: .75rem 1rem;
      outline: none;
      transition: border-color .2s, box-shadow .2s;
      letter-spacing: .05em;
    }
    input[type="text"]:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(79,124,255,.15);
    }
    input[type="text"]::placeholder { color: var(--muted); font-size: .9rem; }

    .btn {
      padding: .75rem 1.6rem;
      border: none;
      border-radius: 10px;
      font-family: 'Syne', sans-serif;
      font-size: .9rem;
      font-weight: 700;
      cursor: pointer;
      transition: all .2s;
      letter-spacing: .02em;
      white-space: nowrap;
    }
    .btn-primary {
      background: var(--accent);
      color: #fff;
    }
    .btn-primary:hover { background: #3d6bf0; transform: translateY(-1px); }
    .btn-primary:active { transform: translateY(0); }
    .btn-ghost {
      background: transparent;
      color: var(--muted);
      border: 1px solid var(--border);
    }
    .btn-ghost:hover { color: var(--text); border-color: var(--muted); }

    /* ── Resultado ── */
    #resultado { margin-bottom: 2rem; }

    .result-card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 16px;
      overflow: hidden;
      animation: slideIn .35s ease;
    }
    @keyframes slideIn {
      from { opacity: 0; transform: translateY(12px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .result-header {
      padding: 1.2rem 1.5rem;
      background: linear-gradient(90deg, rgba(79,124,255,.12), transparent);
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      gap: .75rem;
    }
    .result-header .dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      background: var(--accent2);
      box-shadow: 0 0 8px var(--accent2);
      flex-shrink: 0;
    }
    .result-header h3 {
      font-family: 'Syne', sans-serif;
      font-size: 1.1rem;
      font-weight: 700;
    }
    .result-header .tag {
      margin-left: auto;
      font-size: .68rem;
      letter-spacing: .08em;
      text-transform: uppercase;
      padding: .25rem .65rem;
      border-radius: 999px;
      background: rgba(0,229,176,.1);
      color: var(--accent2);
      border: 1px solid rgba(0,229,176,.2);
    }
    .result-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 0;
    }
    .result-field {
      padding: 1rem 1.5rem;
      border-right: 1px solid var(--border);
      border-bottom: 1px solid var(--border);
    }
    .result-field:last-child { border-right: none; }
    .result-field .lbl {
      font-size: .62rem;
      letter-spacing: .1em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: .3rem;
    }
    .result-field .val {
      font-size: .95rem;
      color: var(--text);
      word-break: break-word;
    }
    .result-field .val.highlight {
      color: var(--accent2);
      font-weight: 500;
      font-size: 1rem;
    }
    .result-field .val.cuit {
      color: var(--accent);
      font-size: 1.05rem;
      letter-spacing: .05em;
    }

    /* ── Error / loading ── */
    .msg-box {
      padding: 1rem 1.5rem;
      border-radius: 12px;
      font-size: .9rem;
      display: flex;
      align-items: center;
      gap: .75rem;
      animation: slideIn .3s ease;
    }
    .msg-box.error   { background: rgba(255,79,106,.1);  border: 1px solid rgba(255,79,106,.3);  color: #ff8fa0; }
    .msg-box.loading { background: rgba(79,124,255,.1);  border: 1px solid rgba(79,124,255,.3);  color: var(--accent); }
    .spinner {
      width: 18px; height: 18px;
      border: 2px solid rgba(79,124,255,.3);
      border-top-color: var(--accent);
      border-radius: 50%;
      animation: spin .7s linear infinite;
      flex-shrink: 0;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Guardado badge ── */
    .saved-badge {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      padding: .4rem 1rem;
      border-radius: 999px;
      background: rgba(0,229,176,.1);
      border: 1px solid rgba(0,229,176,.25);
      color: var(--accent2);
      font-size: .72rem;
      letter-spacing: .06em;
      text-transform: uppercase;
      margin-top: .75rem;
    }

    /* ── Historial ── */
    .section-title {
      font-family: 'Syne', sans-serif;
      font-size: .85rem;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 1rem;
      display: flex;
      align-items: center;
      gap: .5rem;
    }
    .section-title::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border);
    }

    .hist-table-wrap {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 14px;
      overflow: hidden;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: .82rem;
    }
    thead th {
      padding: .75rem 1rem;
      text-align: left;
      font-size: .65rem;
      letter-spacing: .1em;
      text-transform: uppercase;
      color: var(--muted);
      border-bottom: 1px solid var(--border);
      white-space: nowrap;
    }
    tbody tr {
      transition: background .15s;
      border-bottom: 1px solid var(--border);
    }
    tbody tr:last-child { border-bottom: none; }
    tbody tr:hover { background: rgba(255,255,255,.03); }
    td {
      padding: .7rem 1rem;
      color: var(--text);
      vertical-align: middle;
    }
    td.mono { font-family: 'DM Mono', monospace; letter-spacing: .04em; }
    td.cuit-col { color: var(--accent); }
    td.date-col { color: var(--muted); font-size: .75rem; }
    .del-btn {
      background: none;
      border: 1px solid transparent;
      color: var(--muted);
      cursor: pointer;
      border-radius: 6px;
      padding: .2rem .5rem;
      font-size: .8rem;
      transition: all .15s;
    }
    .del-btn:hover { color: var(--danger); border-color: var(--danger); }

    .empty-hist {
      padding: 2.5rem;
      text-align: center;
      color: var(--muted);
      font-size: .85rem;
    }

    /* ── Responsive ── */
    @media (max-width: 600px) {
      .input-row { flex-direction: column; }
      .result-grid { grid-template-columns: 1fr 1fr; }
      thead th:nth-child(4), td:nth-child(4),
      thead th:nth-child(5), td:nth-child(5) { display: none; }
    }
  </style>
</head>
<body>
<div class="wrapper">

  <header>
    <div class="logo">AR</div>
    <div>
      <h1>Consulta por DNI</h1>
      <span>cuitonline.com · AFIP · Base local</span>
    </div>
    <span class="badge-api" id="apiBadge" onclick="checkAPI()">● verificando api</span>
  </header>

  <!-- BÚSQUEDA -->
  <div class="search-section">
    <h2>Buscar persona</h2>
    <div class="input-row">
      <div class="input-wrap">
        <label for="dniInput">DNI</label>
        <input type="text" id="dniInput" maxlength="8"
               placeholder="Ej: 28401234"
               oninput="this.value=this.value.replace(/\D/,'')"
               onkeydown="if(event.key==='Enter') buscar()">
      </div>
      <button class="btn btn-primary" onclick="buscar()">Consultar</button>
      <button class="btn btn-ghost" onclick="limpiar()">Limpiar</button>
    </div>
  </div>

  <!-- RESULTADO -->
  <div id="resultado"></div>

  <!-- HISTORIAL -->
  <p class="section-title">Historial de consultas</p>
  <div class="hist-table-wrap" id="histWrap">
    <?php if (empty($historial)): ?>
      <div class="empty-hist">No hay consultas registradas aún.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>DNI</th>
            <th>Nombre completo</th>
            <th>CUIT / CUIL</th>
            <th>Localidad</th>
            <th>Fecha</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="histBody">
          <?php foreach ($historial as $row): ?>
          <tr id="row-<?= $row['id'] ?>">
            <td class="mono" style="color:var(--muted)"><?= $row['id'] ?></td>
            <td class="mono"><?= htmlspecialchars($row['dni']) ?></td>
            <td><?= htmlspecialchars($row['nombre'] ?? '—') ?></td>
            <td class="mono cuit-col"><?= htmlspecialchars($row['cuit_cuil'] ?? '—') ?></td>
            <td><?= htmlspecialchars($row['localidad'] ?? '—') ?></td>
            <td class="date-col"><?= $row['fecha'] ?></td>
            <td><button class="del-btn" onclick="eliminar(<?= $row['id'] ?>)">✕</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

</div>

<script>
// ── Estado de la API ──────────────────────────────────────
async function checkAPI() {
  const badge = document.getElementById('apiBadge');
  badge.className = 'badge-api';
  badge.textContent = '● verificando...';
  try {
    const r = await fetch('http://localhost:3000/api/status');
    if (r.ok) {
      badge.className = 'badge-api online';
      badge.textContent = '● api online';
    } else throw new Error();
  } catch {
    badge.className = 'badge-api offline';
    badge.textContent = '● api offline';
  }
}
checkAPI();

// ── Buscar DNI ─────────────────────────────────────────────
async function buscar() {
  const dni = document.getElementById('dniInput').value.trim();
  const box  = document.getElementById('resultado');

  if (!dni || dni.length < 7) {
    box.innerHTML = `<div class="msg-box error">⚠ Ingresá un DNI válido (7 u 8 dígitos).</div>`;
    return;
  }

  box.innerHTML = `
    <div class="msg-box loading">
      <div class="spinner"></div>
      Consultando DNI ${dni} en cuitonline.com…
    </div>`;

  try {
    const res  = await fetch(`index.php?action=buscar_dni&dni=${dni}`);
    const json = await res.json();

    if (json.error) {
      box.innerHTML = `<div class="msg-box error">✕ ${json.error}</div>`;
      return;
    }

    const d = json.data;
    box.innerHTML = `
      <div class="result-card">
        <div class="result-header">
          <div class="dot"></div>
          <h3>${d.name || 'Sin nombre'}</h3>
          <span class="tag">${d.personType || 'Persona'}</span>
        </div>
        <div class="result-grid">
          <div class="result-field">
            <div class="lbl">Nombre completo</div>
            <div class="val highlight">${d.name || '—'}</div>
          </div>
          <div class="result-field">
            <div class="lbl">DNI</div>
            <div class="val mono">${dni}</div>
          </div>
          <div class="result-field">
            <div class="lbl">CUIT / CUIL</div>
            <div class="val cuit">${d.cuit || '—'}</div>
          </div>
          <div class="result-field">
            <div class="lbl">Tipo</div>
            <div class="val">${d.personType || '—'}</div>
          </div>
          <div class="result-field">
            <div class="lbl">Género</div>
            <div class="val">${d.gender || '—'}</div>
          </div>
          <div class="result-field">
            <div class="lbl">Nacionalidad</div>
            <div class="val">${d.nationality || '—'}</div>
          </div>
          <div class="result-field">
            <div class="lbl">Localidad</div>
            <div class="val">${d.locality || '—'}</div>
          </div>
          <div class="result-field">
            <div class="lbl">Empleador</div>
            <div class="val">${d.employer || '—'}</div>
          </div>
        </div>
      </div>
      <div class="saved-badge">✓ Guardado en base de datos</div>`;

    recargarHistorial();
  } catch(e) {
    box.innerHTML = `<div class="msg-box error">✕ Error de conexión. Verificá que la API Node.js esté corriendo.</div>`;
  }
}

// ── Limpiar ────────────────────────────────────────────────
function limpiar() {
  document.getElementById('dniInput').value = '';
  document.getElementById('resultado').innerHTML = '';
  document.getElementById('dniInput').focus();
}

// ── Eliminar del historial ─────────────────────────────────
async function eliminar(id) {
  if (!confirm('¿Eliminar este registro?')) return;
  await fetch(`index.php?action=eliminar&id=${id}`);
  const row = document.getElementById(`row-${id}`);
  if (row) row.style.cssText = 'opacity:0;transition:.3s';
  setTimeout(() => { if(row) row.remove(); }, 310);
}

// ── Recargar historial ─────────────────────────────────────
async function recargarHistorial() {
  const res  = await fetch('index.php?action=historial');
  const rows = await res.json();
  const wrap = document.getElementById('histWrap');

  if (!rows.length) {
    wrap.innerHTML = '<div class="empty-hist">No hay consultas registradas aún.</div>';
    return;
  }

  let html = `<table>
    <thead><tr>
      <th>#</th><th>DNI</th><th>Nombre completo</th>
      <th>CUIT / CUIL</th><th>Localidad</th><th>Fecha</th><th></th>
    </tr></thead><tbody>`;

  rows.forEach(r => {
    html += `<tr id="row-${r.id}">
      <td class="mono" style="color:var(--muted)">${r.id}</td>
      <td class="mono">${r.dni}</td>
      <td>${r.nombre || '—'}</td>
      <td class="mono cuit-col">${r.cuit_cuil || '—'}</td>
      <td>${r.localidad || '—'}</td>
      <td class="date-col">${r.fecha}</td>
      <td><button class="del-btn" onclick="eliminar(${r.id})">✕</button></td>
    </tr>`;
  });

  wrap.innerHTML = html + '</tbody></table>';
}
</script>
</body>
</html>