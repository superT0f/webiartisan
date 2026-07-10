<?php
$lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$lng = filter_input(INPUT_GET, 'lng', FILTER_VALIDATE_FLOAT);

if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid lat/lng']);
    exit;
}

$url = sprintf(
    'https://api.open-meteo.com/v1/forecast?latitude=%F&longitude=%F&current_weather=true',
    $lat,
    $lng
);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($code !== 200 || $response === false) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => $error ?: 'Open-Meteo unavailable']);
    exit;
}

$data = json_decode($response, true);
if (!$data || !isset($data['current_weather'])) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'Invalid weather response']);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => $data['current_weather']
]);
