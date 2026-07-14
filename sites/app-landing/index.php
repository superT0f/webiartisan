<?php
/**
 * WebiArtisan — Landing page for app.prigent.tech
 *
 * Simple PHP entry point so we can add logging, geolocation-based
 * redirects, or dynamic routes later.
 */

// Ensure consistent timezone
date_default_timezone_set('Europe/Paris');

// Simple visit log (flat file)
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/visits-' . date('Y-m-d') . '.log';
$logLine = sprintf(
    "[%s] %s %s %s %s\n",
    date('Y-m-d H:i:s'),
    $_SERVER['REMOTE_ADDR'] ?? '-',
    $_SERVER['REQUEST_METHOD'] ?? '-',
    $_SERVER['REQUEST_URI'] ?? '-',
    $_SERVER['HTTP_USER_AGENT'] ?? '-'
);
@file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

// Cities data
$cities = [
    [
        'slug'   => 'livry',
        'name'   => 'Livry',
        'desc'   => 'Artisans, commerçants et bons plans du 14240',
        'icon'   => '🌳',
        'accent' => '#2D6A4F',
        'url'    => 'https://artisans-livry.prigent.tech/',
    ],
    [
        'slug'   => 'combs-la-ville',
        'name'   => 'Combs-la-Ville',
        'desc'   => 'Services locaux et artisans du 77380',
        'icon'   => '🏠',
        'accent' => '#2F4E8C',
        'url'    => 'https://artisans-combs.prigent.tech/',
    ],
    [
        'slug'   => 'vert-saint-denis',
        'name'   => 'Vert-Saint-Denis',
        'desc'   => 'Artisans et commerces du 77240',
        'icon'   => '🌻',
        'accent' => '#E9B949',
        'url'    => 'https://artisans-vert-saint-denis.prigent.tech/',
    ],
];

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="description" content="WebiArtisan — Choisissez votre ville pour découvrir les artisans locaux, faire des check-ins et gagner des récompenses." />
  <title>WebiArtisan — Choix de la ville</title>
  <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
  <link rel="apple-touch-icon" href="/apple-touch-icon.png" />
  <meta name="theme-color" content="#C07A2E" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <style>
    :root {
      --bg: #FEF8EC;
      --card: #ffffff;
      --text: #2B2118;
      --muted: #6B5D4F;
      --primary: #C07A2E;
      --primary-light: #F5E5D0;
      --radius: 16px;
      --shadow: 0 4px 24px rgba(0,0,0,0.08);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .container {
      width: 100%;
      max-width: 420px;
      text-align: center;
    }
    .logo {
      width: 260px;
      max-width: 80%;
      margin: 0 auto 16px;
    }
    h1 {
      font-family: 'Outfit', sans-serif;
      font-size: 1.5rem;
      margin: 0 0 8px;
    }
    p {
      color: var(--muted);
      margin: 0 0 28px;
      line-height: 1.5;
    }
    .cities {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }
    .city {
      display: flex;
      align-items: center;
      gap: 16px;
      background: var(--card);
      border: 1px solid #E7DAC4;
      border-left: 4px solid var(--accent, var(--primary));
      border-radius: var(--radius);
      padding: 18px 20px;
      text-decoration: none;
      color: var(--text);
      box-shadow: var(--shadow);
      transition: transform .15s ease, border-color .15s ease;
    }
    .city:hover, .city:focus {
      transform: translateY(-2px);
      border-color: var(--accent, var(--primary));
      outline: none;
    }
    .city-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      background: var(--primary-light);
      display: grid;
      place-items: center;
      font-size: 1.5rem;
      flex-shrink: 0;
    }
    .city-info { text-align: left; }
    .city-name {
      font-family: 'Outfit', sans-serif;
      font-weight: 700;
      font-size: 1.05rem;
    }
    .city-desc {
      font-size: 0.85rem;
      color: var(--muted);
    }
    .trust-badge {
      margin-top: 24px;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--muted);
    }
    .footer {
      margin-top: 32px;
      font-size: 0.8rem;
      color: var(--muted);
    }
  </style>
</head>
<body>
  <div class="container">
    <img class="logo" src="/logo.svg" alt="WebiArtisan — L'annuaire libre des artisans de proximité" />
    <h1>Bienvenue sur WebiArtisan</h1>
    <p>Choisissez votre commune pour faire un check-in chez vos artisans et gagner de l'XP, profiter de coupons offerts par vos commerçants et faire tourner l'avatar des boutiques partenaires pour gagner des offres.</p>

    <nav class="cities" aria-label="Choix de la ville">
      <?php foreach ($cities as $city): ?>
      <a class="city" href="<?php echo htmlspecialchars($city['url'], ENT_QUOTES, 'UTF-8'); ?>" style="--accent: <?php echo htmlspecialchars($city['accent'], ENT_QUOTES, 'UTF-8'); ?>">
        <div class="city-icon"><?php echo $city['icon']; ?></div>
        <div class="city-info">
          <div class="city-name"><?php echo htmlspecialchars($city['name'], ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="city-desc"><?php echo htmlspecialchars($city['desc'], ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </nav>

    <div class="trust-badge">🇫🇷 Conçu en France</div>

    <div class="footer">
      © WebiArtisan — Faites vivre le commerce local
    </div>
  </div>
</body>
</html>
