<?php
/**
 * Génère l'affiche bêta (campagne QR) en PDF A5 via Dompdf.
 *
 * Usage : php scripts/generate-plaquette-beta.php [ville] [sortie.pdf]
 *   défaut : ville=combs-la-ville, sortie=sites/app-landing/plaquettes/beta-combs.pdf
 *
 * Le QR est récupéré une fois via api.qrserver.com et embarqué dans le PDF
 * (aucune dépendance externe à l'affichage).
 */

require_once __DIR__ . '/../sites/api/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$ville = $argv[1] ?? 'combs-la-ville';
$target = $argv[2] ?? (__DIR__ . '/../sites/app-landing/plaquettes/beta-combs.pdf');
$cityNames = [
    'livry' => 'Livry',
    'combs-la-ville' => 'Combs-la-Ville',
    'vert-saint-denis' => 'Vert-Saint-Denis',
    'lieusaint' => 'Lieusaint',
];
$villeName = $cityNames[$ville] ?? $ville;
$betaUrl = "https://app.prigent.tech/beta?ville=" . rawurlencode($ville);

// 1. QR code (300×300, récupéré une fois)
$qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=10&data=' . rawurlencode($betaUrl);
$qrData = @file_get_contents($qrUrl);
if ($qrData === false) {
    fwrite(STDERR, "❌ Impossible de récupérer le QR ($qrUrl)\n");
    exit(1);
}
$qrB64 = 'data:image/png;base64,' . base64_encode($qrData);

// 2. HTML de l'affiche (A5 portrait)
$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page { margin: 0; }
  body {
    font-family: DejaVu Sans, sans-serif;
    background: #FEF8EC;
    color: #2B2118;
    text-align: center;
    margin: 0;
    padding: 36px 28px;
  }
  .kicker {
    display: inline-block;
    background: #2D6A4F; color: #fff;
    padding: 6px 16px; border-radius: 999px;
    font-size: 13px; font-weight: bold; margin-bottom: 18px;
  }
  h1 { font-size: 30px; margin: 0 0 6px; }
  h1 .accent { color: #C07A2E; }
  .lead { font-size: 13px; color: #6f665c; margin-bottom: 22px; }
  .qr {
    background: #fff; border: 3px solid #C07A2E; border-radius: 18px;
    padding: 14px; display: inline-block; margin-bottom: 14px;
  }
  .qr img { width: 300px; height: 300px; }
  .scan { font-size: 15px; font-weight: bold; margin-bottom: 18px; }
  .features { font-size: 12px; color: #4a4239; margin-bottom: 22px; line-height: 1.7; }
  .footer {
    border-top: 1px solid #e5dfd2; padding-top: 12px;
    font-size: 11px; color: #8a8175;
  }
  .android { color: #2D6A4F; font-weight: bold; }
</style>
</head>
<body>
  <div class="kicker">BÊTA ANDROID — ACCÈS ANTICIPÉ</div>
  <h1>WebiArtisan <span class="accent">{$villeName}</span></h1>
  <p class="lead">La carte de vos artisans devient un jeu : ramassez des déchets,<br/>faites des check-in, relevez des quêtes, affrontez le Big Brother.</p>
  <div class="qr"><img src="{$qrB64}" alt="QR code" /></div>
  <p class="scan">Scannez pour devenir bêta-testeur</p>
  <p class="features">
    Carte des artisans en 2D/3D ♦ Déchets &amp; trésors ♦ Check-in = énergie &amp; XP<br/>
    Quêtes &amp; badges ♦ Duels : quiz savoir-faire, échecs, cartes
  </p>
  <p class="footer">
    <span class="android">Application Android uniquement</span> — iPhone : bientôt, inscrivez-vous pour être prévenu.<br/>
    {$betaUrl}
  </p>
</body>
</html>
HTML;

// 3. Rendu PDF A5 portrait
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A5', 'portrait');
$dompdf->render();

$dir = dirname($target);
if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    fwrite(STDERR, "❌ Impossible de créer $dir\n");
    exit(1);
}
file_put_contents($target, $dompdf->output());
echo "✅ $target (" . number_format(filesize($target) / 1024, 0) . " Ko)\n";
