<?php
// geo/geocode.php
// Converte um endereço em lat/lng usando a API gratuita do Nominatim (OpenStreetMap)
// Chamado via AJAX pelos formulários de empresa

header('Content-Type: application/json; charset=utf-8');

session_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';

verificarLogin(); // Apenas usuários logados podem usar

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
    exit;
}

$endereco = trim($_POST['endereco'] ?? '');
if (empty($endereco)) {
    echo json_encode(['erro' => 'Endereço vazio']);
    exit;
}

// Nominatim requer um User-Agent válido
$url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
    'q'              => $endereco . ', Brasil',
    'format'         => 'json',
    'limit'          => 1,
    'addressdetails' => 1,
]);

$opts = [
    'http' => [
        'method'  => 'GET',
        'header'  => "User-Agent: ServiceHub-TCC/1.0\r\n",
        'timeout' => 8,
    ]
];
$ctx      = stream_context_create($opts);
$response = @file_get_contents($url, false, $ctx);

if ($response === false) {
    echo json_encode(['erro' => 'Falha ao consultar serviço de geocodificação']);
    exit;
}

$data = json_decode($response, true);

if (empty($data)) {
    echo json_encode(['erro' => 'Endereço não encontrado']);
    exit;
}

$resultado = $data[0];
echo json_encode([
    'ok'          => true,
    'lat'         => (float)$resultado['lat'],
    'lng'         => (float)$resultado['lon'],
    'display'     => $resultado['display_name'],
]);
