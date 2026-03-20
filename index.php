<?php
// ============================================================
// Configuración de API
// ============================================================
function loadEnv($file = null) {
    $file = $file ?? __DIR__ . '/.env';
    if (!file_exists($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (!$line || $line[0] === '#' || strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
}
loadEnv();

define('API_URL', $_ENV['API_URL'] ?? 'http://localhost:3000');

// ============================================================
// Llamada a la API de Node.js
// ============================================================
function consultarAPI($endpoint) {
    $url = API_URL . $endpoint;
    $ctx = stream_context_create([
        'http' => ['timeout' => 15, 'ignore_errors' => true]
    ]);
    $response = file_get_contents($url, false, $ctx);
    if ($response === false) {
        return ['error' => 'No se pudo conectar con la API. ¿Está corriendo el servidor Node.js?'];
    }
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Respuesta inválida de la API.'];
    }
    return $data;
}

// ============================================================
// Procesar petición AJAX
// ============================================================
if (isset($_GET['action'])) {
    ini_set('display_errors', 0);
    error_reporting(0);
    header('Content-Type: application/json');

    if ($_GET['action'] === 'buscar_dni' && isset($_GET['dni'])) {
        $dni = preg_replace('/\D/', '', trim($_GET['dni']));
        if (strlen($dni) < 7 || strlen($dni) > 8) {
            echo json_encode(['error' => 'El DNI debe tener 7 u 8 dígitos.']);
            exit;
        }
        echo json_encode(consultarAPI('/api/dni/' . $dni));
        exit;
    }

    if ($_GET['action'] === 'api_status') {
        $t0     = microtime(true);
        $result = consultarAPI('/api/status');
        $ms     = round((microtime(true) - $t0) * 1000);
        if (isset($result['status']) && $result['status'] === 'ok') {
            echo json_encode(['ok' => true, 'ms' => $ms]);
        } else {
            echo json_encode(['ok' => false]);
        }
        exit;
    }

    echo json_encode(['error' => 'Acción no válida']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>DNI Imperio</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg:       #f0f4ff;
      --surface:  #ffffff;
      --border:   #dde4f5;
      --accent:   #4361ee;
      --accent2:  #06d6a0;
      --danger:   #ef476f;
      --text:     #1e2040;
      --muted:    #7b84b4;
      --shadow:   0 2px 12px rgba(67,97,238,.10);
      --shadow-lg: 0 8px 32px rgba(67,97,238,.14);
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      background: var(--bg);
      color: var(--text);
      font-family: 'Inter', sans-serif;
      min-height: 100dvh;
      -webkit-font-smoothing: antialiased;
    }

    /* ── Fondo decorativo ── */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background:
        radial-gradient(ellipse 70% 50% at 10% -10%, rgba(67,97,238,.09) 0%, transparent 60%),
        radial-gradient(ellipse 50% 40% at 90% 100%, rgba(6,214,160,.08) 0%, transparent 55%);
      pointer-events: none;
      z-index: 0;
    }

    .wrapper {
      position: relative;
      z-index: 1;
      max-width: 520px;
      margin: 0 auto;
      padding: 1.5rem 1rem 5rem;
      min-height: 100dvh;
    }

    /* ── Header ── */
    header {
      display: flex;
      align-items: center;
      gap: .9rem;
      margin-bottom: 2rem;
      padding: 1.1rem 1.2rem;
      background: var(--surface);
      border-radius: 18px;
      box-shadow: var(--shadow);
      border: 1px solid var(--border);
    }
    .logo {
      width: 46px; height: 46px;
      background: linear-gradient(135deg, var(--accent), #7b5ea7);
      border-radius: 13px;
      display: grid; place-items: center;
      font-family: 'Inter', sans-serif;
      font-weight: 800;
      font-size: .9rem;
      color: #fff;
      letter-spacing: -.02em;
      flex-shrink: 0;
      box-shadow: 0 4px 12px rgba(67,97,238,.35);
    }
    .brand h1 {
      font-size: 1.15rem;
      font-weight: 800;
      letter-spacing: -.03em;
      background: linear-gradient(90deg, var(--accent), #7b5ea7);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      line-height: 1.2;
    }
    .brand p {
      font-size: .7rem;
      color: var(--muted);
      margin-top: 1px;
      font-weight: 500;
      letter-spacing: .02em;
    }
    .badge-api {
      margin-left: auto;
      display: flex;
      align-items: center;
      gap: .35rem;
      padding: .35rem .75rem;
      border-radius: 999px;
      font-size: .65rem;
      font-weight: 600;
      letter-spacing: .05em;
      text-transform: uppercase;
      border: 1.5px solid var(--border);
      color: var(--muted);
      background: var(--bg);
      cursor: pointer;
      transition: all .2s;
      white-space: nowrap;
    }
    .badge-api .dot {
      width: 6px; height: 6px;
      border-radius: 50%;
      background: var(--muted);
      flex-shrink: 0;
      transition: background .2s;
    }
    .badge-api.online  { border-color: #b8f0de; color: #0e9a6e; background: #f0fdf9; }
    .badge-api.online .dot  { background: var(--accent2); box-shadow: 0 0 6px var(--accent2); }
    .badge-api.offline { border-color: #fdd0d8; color: #c4334f; background: #fff5f7; }
    .badge-api.offline .dot { background: var(--danger); }

    /* ── Card de búsqueda ── */
    .search-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 1.5rem;
      box-shadow: var(--shadow);
      margin-bottom: 1.25rem;
    }
    .search-card h2 {
      font-size: .72rem;
      font-weight: 700;
      letter-spacing: .1em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 1rem;
    }

    .input-group {
      position: relative;
      margin-bottom: .85rem;
    }
    .input-group .icon {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      font-size: 1.1rem;
      pointer-events: none;
    }
    input[type="text"] {
      width: 100%;
      background: var(--bg);
      border: 1.5px solid var(--border);
      border-radius: 13px;
      color: var(--text);
      font-family: 'JetBrains Mono', monospace;
      font-size: 1.25rem;
      font-weight: 500;
      padding: .9rem 1rem .9rem 2.9rem;
      outline: none;
      transition: border-color .2s, box-shadow .2s;
      letter-spacing: .08em;
      -webkit-appearance: none;
    }
    input[type="text"]:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 4px rgba(67,97,238,.12);
      background: #fff;
    }
    input[type="text"]::placeholder {
      color: var(--muted);
      font-size: .95rem;
      letter-spacing: .03em;
      font-weight: 400;
    }

    .btn-row {
      display: flex;
      gap: .6rem;
    }
    .btn {
      flex: 1;
      padding: .88rem 1rem;
      border: none;
      border-radius: 13px;
      font-family: 'Inter', sans-serif;
      font-size: .95rem;
      font-weight: 700;
      cursor: pointer;
      transition: all .18s;
      min-height: 48px;
      -webkit-tap-highlight-color: transparent;
    }
    .btn-primary {
      background: linear-gradient(135deg, var(--accent), #7b5ea7);
      color: #fff;
      box-shadow: 0 4px 14px rgba(67,97,238,.35);
      flex: 2;
    }
    .btn-primary:hover  { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(67,97,238,.4); }
    .btn-primary:active { transform: translateY(0); box-shadow: none; }
    .btn-ghost {
      background: var(--bg);
      color: var(--muted);
      border: 1.5px solid var(--border);
      flex: 1;
    }
    .btn-ghost:hover { color: var(--text); border-color: #b0b8d8; }
    .btn-ghost:active { background: var(--border); }

    /* ── Resultado ── */
    #resultado { margin-bottom: 1rem; }

    .result-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 20px;
      overflow: hidden;
      box-shadow: var(--shadow-lg);
      animation: slideUp .3s cubic-bezier(.16,1,.3,1);
    }
    @keyframes slideUp {
      from { opacity: 0; transform: translateY(16px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .result-hero {
      padding: 1.4rem 1.4rem 1rem;
      background: linear-gradient(135deg, rgba(67,97,238,.07), rgba(123,94,167,.05));
      border-bottom: 1px solid var(--border);
    }
    .result-hero .name-tag {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      font-size: .65rem;
      font-weight: 700;
      letter-spacing: .1em;
      text-transform: uppercase;
      color: var(--accent2);
      background: rgba(6,214,160,.1);
      border: 1px solid rgba(6,214,160,.25);
      padding: .25rem .7rem;
      border-radius: 999px;
      margin-bottom: .6rem;
    }
    .result-hero h3 {
      font-size: 1.35rem;
      font-weight: 800;
      letter-spacing: -.02em;
      color: var(--text);
      line-height: 1.2;
    }
    .result-hero .cuit-display {
      font-family: 'JetBrains Mono', monospace;
      font-size: 1rem;
      font-weight: 500;
      color: var(--accent);
      margin-top: .3rem;
      letter-spacing: .06em;
    }

    .result-fields {
      padding: .5rem 0;
    }
    .field-row {
      display: flex;
      align-items: center;
      padding: .75rem 1.4rem;
      border-bottom: 1px solid var(--border);
      gap: .75rem;
    }
    .field-row:last-child { border-bottom: none; }
    .field-icon {
      font-size: 1.1rem;
      width: 28px;
      text-align: center;
      flex-shrink: 0;
    }
    .field-info { flex: 1; min-width: 0; }
    .field-label {
      font-size: .62rem;
      font-weight: 700;
      letter-spacing: .09em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: .15rem;
    }
    .field-value {
      font-size: .9rem;
      font-weight: 500;
      color: var(--text);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .field-value.mono {
      font-family: 'JetBrains Mono', monospace;
      font-size: .85rem;
    }

    /* ── Mensajes ── */
    .msg-box {
      padding: 1rem 1.2rem;
      border-radius: 14px;
      font-size: .88rem;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: .75rem;
      animation: slideUp .25s ease;
    }
    .msg-box.error   { background: #fff5f7; border: 1.5px solid #fdd0d8; color: #c4334f; }
    .msg-box.loading { background: #f0f4ff; border: 1.5px solid #c7d2fc; color: var(--accent); }
    .spinner {
      width: 18px; height: 18px;
      border: 2.5px solid rgba(67,97,238,.2);
      border-top-color: var(--accent);
      border-radius: 50%;
      animation: spin .7s linear infinite;
      flex-shrink: 0;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Responsive ── */
    @media (max-width: 400px) {
      .wrapper { padding: 1rem .75rem 4rem; }
      .result-hero h3 { font-size: 1.15rem; }
      header { padding: .9rem 1rem; }
    }
  </style>
</head>
<body>
<div class="wrapper">

  <header>
    <div class="logo">DI</div>
    <div class="brand">
      <h1>DNI Imperio</h1>
      <p>AFIP · cuitonline.com · Argentina</p>
    </div>
    <span class="badge-api" id="apiBadge" onclick="checkAPI()">
      <span class="dot"></span>
      <span id="badgeText">verificando</span>
    </span>
  </header>

  <!-- BÚSQUEDA -->
  <div class="search-card">
    <h2>Buscar persona</h2>
    <div class="input-group">
      <span class="icon">🪪</span>
      <input type="text" id="dniInput" maxlength="8"
             inputmode="numeric" pattern="\d*"
             placeholder="Número de DNI"
             oninput="this.value=this.value.replace(/\D/g,'')"
             onkeydown="if(event.key==='Enter') buscar()">
    </div>
    <div class="btn-row">
      <button class="btn btn-primary" onclick="buscar()">Consultar</button>
      <button class="btn btn-ghost" onclick="limpiar()">Limpiar</button>
    </div>
  </div>

  <!-- RESULTADO -->
  <div id="resultado"></div>

</div>

<script>
async function checkAPI() {
  const badge = document.getElementById('apiBadge');
  const text  = document.getElementById('badgeText');
  badge.className = 'badge-api';
  text.textContent = 'verificando';
  try {
    const r    = await fetch('index.php?action=api_status');
    const data = await r.json();
    if (data.ok) {
      badge.className = 'badge-api online';
      text.textContent = `online · ${data.ms}ms`;
    } else throw new Error();
  } catch {
    badge.className = 'badge-api offline';
    text.textContent = 'offline';
  }
}
checkAPI();
setInterval(checkAPI, 30000);

async function buscar() {
  const dni = document.getElementById('dniInput').value.trim();
  const box  = document.getElementById('resultado');

  if (!dni || dni.length < 7) {
    box.innerHTML = `<div class="msg-box error">⚠️ Ingresá un DNI válido (7 u 8 dígitos).</div>`;
    return;
  }

  box.innerHTML = `
    <div class="msg-box loading">
      <div class="spinner"></div>
      Consultando DNI ${dni}…
    </div>`;

  try {
    const res  = await fetch(`index.php?action=buscar_dni&dni=${encodeURIComponent(dni)}`);
    const json = await res.json();

    if (json.error) {
      box.innerHTML = `<div class="msg-box error">❌ ${json.error}</div>`;
      return;
    }

    const d = json.data;
    box.innerHTML = `
      <div class="result-card">
        <div class="result-hero">
          <div class="name-tag">✓ Encontrado</div>
          <h3>${d.name || 'Sin nombre'}</h3>
          <div class="cuit-display">${d.cuit || '—'}</div>
        </div>
        <div class="result-fields">
          <div class="field-row">
            <div class="field-icon">🪪</div>
            <div class="field-info">
              <div class="field-label">DNI</div>
              <div class="field-value mono">${dni}</div>
            </div>
          </div>
          <div class="field-row">
            <div class="field-icon">🏷️</div>
            <div class="field-info">
              <div class="field-label">CUIT / CUIL</div>
              <div class="field-value mono">${d.cuit || '—'}</div>
            </div>
          </div>
          <div class="field-row">
            <div class="field-icon">👤</div>
            <div class="field-info">
              <div class="field-label">Tipo de persona</div>
              <div class="field-value">${d.personType || '—'}</div>
            </div>
          </div>
          <div class="field-row">
            <div class="field-icon">⚥</div>
            <div class="field-info">
              <div class="field-label">Género</div>
              <div class="field-value">${d.gender || '—'}</div>
            </div>
          </div>
          <div class="field-row">
            <div class="field-icon">🌍</div>
            <div class="field-info">
              <div class="field-label">Nacionalidad</div>
              <div class="field-value">${d.nationality || '—'}</div>
            </div>
          </div>
          <div class="field-row">
            <div class="field-icon">📍</div>
            <div class="field-info">
              <div class="field-label">Localidad</div>
              <div class="field-value">${d.locality || '—'}</div>
            </div>
          </div>
          <div class="field-row">
            <div class="field-icon">🏢</div>
            <div class="field-info">
              <div class="field-label">Empleador</div>
              <div class="field-value">${d.employer || '—'}</div>
            </div>
          </div>
        </div>
      </div>`;
  } catch(e) {
    box.innerHTML = `<div class="msg-box error">❌ Error de conexión. Verificá que la API esté corriendo.</div>`;
  }
}

function limpiar() {
  document.getElementById('dniInput').value = '';
  document.getElementById('resultado').innerHTML = '';
  document.getElementById('dniInput').focus();
}
</script>
</body>
</html>
