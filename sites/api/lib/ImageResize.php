<?php
/**
 * Conversion basse résolution des photos communautaires (GD) :
 * ≤ 1600 px de large, ré-encodage JPEG q80 (~200-400 Ko).
 */

function poi_photo_resize(string $srcPath, string $mime, string $destPath): bool
{
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($srcPath),
        'image/png'  => @imagecreatefrompng($srcPath),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($srcPath) : false,
        default      => false,
    };
    if (!$src) return false;

    $w = imagesx($src);
    $h = imagesy($src);
    if ($w > 1600) {
        $dst = imagescale($src, 1600);
        imagedestroy($src);
        if (!$dst) return false;
        $src = $dst;
    }
    $ok = imagejpeg($src, $destPath, 80);
    imagedestroy($src);
    return $ok;
}
