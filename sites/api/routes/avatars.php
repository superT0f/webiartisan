<?php
/**
 * WebIArtisan API — Route : Avatars
 *
 * GET /avatars?gender=male|female
 */

$gender = $_GET['gender'] ?? 'neutral';
$allowedGenders = ['male', 'female', 'neutral'];
if (!in_array($gender, $allowedGenders, true)) {
    $gender = 'neutral';
}

$basePath = __DIR__ . '/../public/avatars';
$dirs = [];
if ($gender === 'neutral') {
    $dirs = ['neutral', 'male', 'female'];
} else {
    $dirs = [$gender, 'neutral'];
}

$avatars = [];
foreach ($dirs as $dir) {
    $path = $basePath . '/' . $dir;
    if (!is_dir($path)) continue;
    foreach (glob($path . '/*.{png,svg,jpg,jpeg}', GLOB_BRACE) as $file) {
        $metaFile = $file . '.json';
        $meta = [];
        if (file_exists($metaFile)) {
            $meta = json_decode(file_get_contents($metaFile), true) ?: [];
        }
        $avatars[] = [
            'id' => $meta['id'] ?? pathinfo($file, PATHINFO_FILENAME),
            'gender' => $dir,
            'url' => '/avatars/' . $dir . '/' . basename($file),
            'name' => $meta['name'] ?? basename($file),
            'unlock_level' => $meta['unlock_level'] ?? 1,
            'unlock_badge' => $meta['unlock_badge'] ?? null,
        ];
    }
}

usort($avatars, fn($a, $b) => $a['unlock_level'] <=> $b['unlock_level']);

echo json_encode(['success' => true, 'data' => $avatars]);
