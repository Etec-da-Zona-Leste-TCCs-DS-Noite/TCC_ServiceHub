<?php
// clientes/mapa.php — Mapa interativo de empresas
session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

verificarLogin();
if (!isCliente()) { header('Location: ../index.php'); exit; }

// Busca empresas com coordenadas
$stmt = $pdo->query("
    SELECT e.*,
           (SELECT ROUND(AVG(a.nota),1) FROM avaliacoes a WHERE a.empresa_id = e.id) AS media_nota,
           (SELECT COUNT(*)             FROM avaliacoes a WHERE a.empresa_id = e.id) AS total_aval,
           (SELECT COUNT(*)             FROM servicos   s WHERE s.empresa_id = e.id AND s.status = 1) AS total_servicos
    FROM empresas e
    WHERE e.status = 1
    ORDER BY e.nome_empresa
");
$empresas = $stmt->fetchAll();

// Separa empresas com e sem coordenadas
$comGeo   = array_filter($empresas, fn($e) => !empty($e['latitude']) && !empty($e['longitude']));
$semGeo   = array_filter($empresas, fn($e) =>  empty($e['latitude']) ||  empty($e['longitude']));

$empresasJson = json_encode(array_values(array_map(fn($e) => [
    'id'             => (int)$e['id'],
    'nome'           => $e['nome_empresa'],
    'endereco'       => $e['endereco'] ?? '',
    'telefone'       => $e['telefone'] ?? '',
    'descricao'      => mb_substr($e['descricao'] ?? '', 0, 120),
    'lat'            => (float)$e['latitude'],
    'lng'            => (float)$e['longitude'],
    'media_nota'     => $e['media_nota'] ? (float)$e['media_nota'] : null,
    'total_aval'     => (int)$e['total_aval'],
    'total_servicos' => (int)$e['total_servicos'],
    'url'            => 'empresa.php?id=' . $e['id'],
], $comGeo)));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ServiceHub — Mapa de Empresas</title>
<link rel="stylesheet" href="../css/estilo.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<!-- Leaflet.js — mapa gratuito sem API key -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
:root {
    --navy: #0d1b2a;
    --navy-soft: #1a3a5c;
    --gold: #c9a84c;
    --gold-lt: #e8c96e;
    --border: #e2e8f0;
    --radius: 12px;
    --shadow: 0 4px 20px rgba(13,27,42,.12);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', sans-serif; background: #f4f7fa; color: #1a2332; }

/* NAV */
.navbar {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-soft) 100%);
    padding: 0 24px; display: flex; align-items: center;
    justify-content: space-between; min-height: 60px;
    position: sticky; top: 0; z-index: 1000;
    box-shadow: 0 2px 12px rgba(0,0,0,.3);
}
.navbar a { color: #b0bec5; font-size: 13px; text-decoration: none;
            padding: 6px 12px; border-radius: 6px; transition: .2s; }
.navbar a:hover { color: #fff; background: rgba(201,168,76,.2); }
.brand { color: #fff; font-size: 20px; font-weight: 700; }
.brand span { color: var(--gold); }

/* LAYOUT */
.page { display: flex; height: calc(100vh - 60px); }

/* SIDEBAR */
.sidebar {
    width: 360px; min-width: 320px; background: #fff;
    display: flex; flex-direction: column;
    border-right: 1px solid var(--border);
    overflow: hidden;
}
.sidebar-header {
    padding: 18px 20px; background: var(--navy);
    color: #fff; flex-shrink: 0;
}
.sidebar-header h2 { font-size: 16px; margin-bottom: 4px; }
.sidebar-header p  { font-size: 12px; color: #90a4ae; }

/* Controles */
.controls { padding: 14px 16px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.search-box {
    display: flex; gap: 8px; margin-bottom: 10px;
}
.search-box input {
    flex: 1; padding: 9px 12px; border: 1px solid var(--border);
    border-radius: 8px; font-size: 13px; outline: none;
    transition: border .2s;
}
.search-box input:focus { border-color: var(--gold); }
.search-box button {
    background: var(--navy); color: #fff; border: none;
    padding: 9px 14px; border-radius: 8px; cursor: pointer;
    font-size: 13px; white-space: nowrap;
}
.filter-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.filter-row label { font-size: 12px; color: #607d8b; white-space: nowrap; }
.filter-row select, .filter-row input[type=range] {
    font-size: 12px; border: 1px solid var(--border);
    border-radius: 6px; padding: 5px 8px; cursor: pointer;
}
#distLabel { font-size: 12px; font-weight: 600; color: var(--navy); min-width: 48px; }

/* Botão localização */
.btn-loc {
    width: 100%; padding: 9px; margin-top: 8px;
    background: linear-gradient(135deg, #1a4a6f, var(--navy));
    color: #fff; border: none; border-radius: 8px;
    cursor: pointer; font-size: 13px; font-weight: 600;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    transition: .2s;
}
.btn-loc:hover { opacity: .9; }
.btn-loc.active { background: linear-gradient(135deg, var(--gold), #b8952a); color: var(--navy); }

/* Lista de empresas */
.empresa-list { flex: 1; overflow-y: auto; }
.emp-item {
    padding: 14px 16px; border-bottom: 1px solid var(--border);
    cursor: pointer; transition: background .15s;
    display: flex; gap: 12px; align-items: flex-start;
}
.emp-item:hover { background: #f8fafc; }
.emp-item.highlighted { background: #fff8e6; border-left: 3px solid var(--gold); }
.emp-item.hidden { display: none; }
.emp-icon {
    width: 42px; height: 42px; border-radius: 10px;
    background: linear-gradient(135deg, var(--navy), var(--navy-soft));
    display: flex; align-items: center; justify-content: center;
    color: var(--gold); font-size: 18px; flex-shrink: 0;
}
.emp-info { flex: 1; min-width: 0; }
.emp-info h4 { font-size: 14px; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.emp-info .addr { font-size: 11px; color: #78909c; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.emp-meta { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.emp-meta .dist-badge {
    font-size: 11px; font-weight: 700; color: var(--navy);
    background: #fff3cc; border-radius: 10px;
    padding: 2px 8px; border: 1px solid #f0d060;
}
.emp-meta .stars { font-size: 11px; color: var(--gold); }
.emp-meta .svc-count { font-size: 11px; color: #78909c; }
.no-results { text-align: center; padding: 40px 20px; color: #90a4ae; }
.no-results i { font-size: 36px; display: block; margin-bottom: 12px; }

/* SEM GEO */
.sem-geo-banner {
    padding: 12px 16px; background: #fff8e6;
    border-top: 1px solid #f0d060; font-size: 12px; color: #7a5c00;
    display: flex; align-items: center; gap: 8px; flex-shrink: 0;
}

/* MAPA */
#map { flex: 1; z-index: 1; }

/* POPUP */
.map-popup h4 { font-size: 14px; color: var(--navy); margin-bottom: 4px; }
.map-popup .addr { font-size: 11px; color: #607d8b; margin-bottom: 6px; }
.map-popup .stars { color: var(--gold); font-size: 13px; }
.map-popup .btn-popup {
    display: block; margin-top: 8px; padding: 7px 12px;
    background: var(--gold); color: var(--navy); border-radius: 6px;
    text-align: center; font-weight: 700; font-size: 12px;
    text-decoration: none;
}
.map-popup .dist-popup { font-size: 11px; color: #607d8b; margin-top: 4px; }

/* Marcador do usuário */
.user-dot { width: 18px; height: 18px; border-radius: 50%;
            background: #2196f3; border: 3px solid #fff;
            box-shadow: 0 0 0 3px rgba(33,150,243,.4); }

/* Toast */
#toast {
    position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
    background: #1a2332; color: #fff; padding: 12px 20px;
    border-radius: 10px; font-size: 13px; z-index: 9999;
    display: none; box-shadow: 0 4px 20px rgba(0,0,0,.3);
    pointer-events: none;
}

@media (max-width: 768px) {
    .page { flex-direction: column; height: auto; }
    .sidebar { width: 100%; height: auto; min-height: 300px; border-right: none; border-bottom: 1px solid var(--border); }
    .empresa-list { max-height: 240px; }
    #map { height: 55vh; }
}
</style>
</head>
<body>

<nav class="navbar">
    <div class="brand">Service<span>Hub</span></div>
    <div>
        <a href="../dashboard_cliente.php"><i class="fas fa-th-large"></i> Painel</a>
        <a href="empresas.php"><i class="fas fa-list"></i> Lista</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Sair</a>
    </div>
</nav>

<div class="page">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-map-marked-alt" style="color:var(--gold)"></i> Mapa de Empresas</h2>
            <p><?= count($comGeo) ?> empresa(s) no mapa · <?= count($semGeo) ?> sem localização</p>
        </div>

        <div class="controls">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Buscar empresa...">
                <button onclick="clearSearch()"><i class="fas fa-times"></i></button>
            </div>
            <div class="filter-row">
                <label><i class="fas fa-ruler"></i> Raio:</label>
                <input type="range" id="distRange" min="1" max="100" value="50" step="1"
                       oninput="updateDist(this.value)" disabled>
                <span id="distLabel" style="color:#90a4ae">—</span>
            </div>
            <button class="btn-loc" id="btnLoc" onclick="toggleLocation()">
                <i class="fas fa-location-arrow"></i> Usar minha localização
            </button>
        </div>

        <div class="empresa-list" id="empresaList">
            <?php foreach ($comGeo as $emp): ?>
            <div class="emp-item"
                 id="item-<?= $emp['id'] ?>"
                 data-id="<?= $emp['id'] ?>"
                 data-nome="<?= strtolower($emp['nome_empresa']) ?>"
                 data-lat="<?= $emp['latitude'] ?>"
                 data-lng="<?= $emp['longitude'] ?>"
                 onclick="focusEmpresa(<?= $emp['id'] ?>)">
                <div class="emp-icon"><i class="fas fa-building"></i></div>
                <div class="emp-info">
                    <h4><?= htmlspecialchars($emp['nome_empresa']) ?></h4>
                    <div class="addr"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($emp['endereco'] ?? 'Localização no mapa') ?></div>
                    <div class="emp-meta">
                        <?php if ($emp['media_nota']): ?>
                        <span class="stars">
                            <?= str_repeat('★', round($emp['media_nota'])) ?><?= str_repeat('☆', 5 - round($emp['media_nota'])) ?>
                            <?= number_format($emp['media_nota'], 1, ',', '') ?>
                        </span>
                        <?php endif; ?>
                        <span class="svc-count"><i class="fas fa-briefcase"></i> <?= $emp['total_servicos'] ?> serv.</span>
                        <span class="dist-badge" id="dist-<?= $emp['id'] ?>" style="display:none">—</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($comGeo)): ?>
            <div class="no-results">
                <i class="fas fa-map-marked-alt"></i>
                <p>Nenhuma empresa com localização cadastrada ainda.</p>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($semGeo)): ?>
        <div class="sem-geo-banner">
            <i class="fas fa-exclamation-triangle"></i>
            <span><?= count($semGeo) ?> empresa(s) sem endereço geocodificado — não aparecem no mapa.</span>
        </div>
        <?php endif; ?>
    </aside>

    <!-- MAPA -->
    <div id="map"></div>
</div>

<div id="toast"></div>

<script>
const EMPRESAS = <?= $empresasJson ?>;

// ── Mapa ──────────────────────────────────────────────────────────────
const map = L.map('map', { zoomControl: true }).setView([-15.8, -47.9], 5);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19
}).addTo(map);

// Ícone personalizado para empresas
function makeIcon(color = '#1a4a6f') {
    return L.divIcon({
        className: '',
        html: `<div style="
            width:36px;height:36px;border-radius:50% 50% 50% 0;
            background:${color};border:3px solid #fff;
            box-shadow:0 3px 10px rgba(0,0,0,.3);
            transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;">
            <i class='fas fa-building' style='transform:rotate(45deg);color:#fff;font-size:13px;'></i>
        </div>`,
        iconSize: [36, 36],
        iconAnchor: [18, 36],
        popupAnchor: [0, -38],
    });
}

const markers = {};

EMPRESAS.forEach(e => {
    const stars = e.media_nota
        ? '★'.repeat(Math.round(e.media_nota)) + '☆'.repeat(5 - Math.round(e.media_nota))
          + ` ${e.media_nota.toFixed(1).replace('.', ',')} (${e.total_aval})`
        : 'Sem avaliações';

    const popup = `
      <div class="map-popup">
        <h4>${e.nome}</h4>
        <div class="addr"><i class="fas fa-map-marker-alt"></i> ${e.endereco || '—'}</div>
        <div class="stars">${stars}</div>
        <div style="font-size:11px;color:#607d8b;margin-top:4px;">
          <i class="fas fa-briefcase"></i> ${e.total_servicos} serviço(s) disponível(is)
        </div>
        <div class="dist-popup" id="popupDist${e.id}"></div>
        <a class="btn-popup" href="${e.url}">
          <i class="fas fa-eye"></i> Ver empresa
        </a>
      </div>`;

    const marker = L.marker([e.lat, e.lng], { icon: makeIcon() })
        .addTo(map)
        .bindPopup(popup, { maxWidth: 240 });

    marker.on('click', () => highlightItem(e.id));
    markers[e.id] = marker;
});

// ── Localização do usuário ────────────────────────────────────────────
let userLat = null, userLng = null, userMarker = null, locActive = false;

function toggleLocation() {
    if (locActive) {
        clearLocation();
    } else {
        requestLocation();
    }
}

function requestLocation() {
    if (!navigator.geolocation) {
        showToast('Seu navegador não suporta geolocalização');
        return;
    }
    showToast('Obtendo sua localização...');
    navigator.geolocation.getCurrentPosition(
        pos => {
            userLat = pos.coords.latitude;
            userLng = pos.coords.longitude;
            locActive = true;

            // Marcador do usuário
            if (userMarker) map.removeLayer(userMarker);
            userMarker = L.marker([userLat, userLng], {
                icon: L.divIcon({
                    className: '',
                    html: `<div class="user-dot"></div>`,
                    iconSize: [18, 18], iconAnchor: [9, 9]
                }),
                zIndexOffset: 1000
            }).addTo(map).bindPopup('<b>Você está aqui</b>').openPopup();

            map.setView([userLat, userLng], 12);

            document.getElementById('btnLoc').classList.add('active');
            document.getElementById('btnLoc').innerHTML =
                '<i class="fas fa-times-circle"></i> Remover localização';
            document.getElementById('distRange').disabled = false;

            updateDistances();
            applyFilters();
            showToast('Localização encontrada!');
        },
        err => {
            showToast('Não foi possível obter a localização: ' + err.message);
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

function clearLocation() {
    locActive = false; userLat = null; userLng = null;
    if (userMarker) { map.removeLayer(userMarker); userMarker = null; }
    document.getElementById('btnLoc').classList.remove('active');
    document.getElementById('btnLoc').innerHTML = '<i class="fas fa-location-arrow"></i> Usar minha localização';
    document.getElementById('distRange').disabled = true;
    document.getElementById('distLabel').textContent = '—';
    document.getElementById('distLabel').style.color = '#90a4ae';

    // Remove badges de distância
    EMPRESAS.forEach(e => {
        const badge = document.getElementById('dist-' + e.id);
        if (badge) badge.style.display = 'none';
        const pd = document.getElementById('popupDist' + e.id);
        if (pd) pd.textContent = '';
    });

    // Reexibe todos os itens
    document.querySelectorAll('.emp-item').forEach(el => el.classList.remove('hidden'));
    applySearch();
}

function haversine(lat1, lng1, lat2, lng2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat/2)**2 +
              Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) * Math.sin(dLng/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
}

function updateDistances() {
    if (!locActive) return;
    const maxDist = parseInt(document.getElementById('distRange').value);
    EMPRESAS.forEach(e => {
        const dist = haversine(userLat, userLng, e.lat, e.lng);
        e._dist = dist;

        const badge = document.getElementById('dist-' + e.id);
        if (badge) {
            badge.textContent = dist < 1 ? `${(dist*1000).toFixed(0)} m` : `${dist.toFixed(1).replace('.',',')} km`;
            badge.style.display = 'inline-block';
        }
        const pd = document.getElementById('popupDist' + e.id);
        if (pd) {
            pd.innerHTML = `<i class="fas fa-route"></i> ${dist < 1 ? (dist*1000).toFixed(0)+' m' : dist.toFixed(1).replace('.',',')+' km'} de você`;
        }
    });

    // Reordena lista por distância
    const list = document.getElementById('empresaList');
    [...list.querySelectorAll('.emp-item')].sort((a, b) => {
        const ea = EMPRESAS.find(e => e.id === parseInt(a.dataset.id));
        const eb = EMPRESAS.find(e => e.id === parseInt(b.dataset.id));
        return (ea?._dist ?? 9999) - (eb?._dist ?? 9999);
    }).forEach(el => list.appendChild(el));
}

function updateDist(val) {
    document.getElementById('distLabel').textContent = val + ' km';
    document.getElementById('distLabel').style.color = 'var(--navy)';
    updateDistances();
    applyFilters();
}

function applyFilters() {
    if (!locActive) return;
    const maxDist = parseInt(document.getElementById('distRange').value);
    EMPRESAS.forEach(e => {
        const item = document.getElementById('item-' + e.id);
        const marker = markers[e.id];
        if (!item) return;
        const hidden = e._dist > maxDist;
        item.classList.toggle('hidden', hidden);
        if (marker) {
            if (hidden) map.removeLayer(marker);
            else marker.addTo(map);
        }
    });
}

// ── Busca por nome ────────────────────────────────────────────────────
document.getElementById('searchInput').addEventListener('input', applySearch);

function applySearch() {
    const q = document.getElementById('searchInput').value.toLowerCase().trim();
    document.querySelectorAll('.emp-item').forEach(el => {
        const nome = el.dataset.nome || '';
        const matches = nome.includes(q);
        if (!el.classList.contains('hidden') || !locActive) {
            el.style.display = matches ? '' : 'none';
        }
    });
}

function clearSearch() {
    document.getElementById('searchInput').value = '';
    applySearch();
}

// ── Foco no mapa ──────────────────────────────────────────────────────
function focusEmpresa(id) {
    const marker = markers[id];
    if (!marker) return;
    map.setView(marker.getLatLng(), 14, { animate: true });
    marker.openPopup();
    highlightItem(id);
}

function highlightItem(id) {
    document.querySelectorAll('.emp-item').forEach(el => el.classList.remove('highlighted'));
    const item = document.getElementById('item-' + id);
    if (item) {
        item.classList.add('highlighted');
        item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

// ── Toast ─────────────────────────────────────────────────────────────
function showToast(msg, ms = 3000) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.style.display = 'block';
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.style.display = 'none', ms);
}

// Se há empresas, centraliza o mapa nelas
if (EMPRESAS.length > 0) {
    const bounds = L.latLngBounds(EMPRESAS.map(e => [e.lat, e.lng]));
    map.fitBounds(bounds, { padding: [40, 40] });
}
</script>
</body>
</html>
