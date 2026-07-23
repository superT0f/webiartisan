<?php
/**
 * WebiArtisan — Page d'inscription bêta (campagne QR / early access)
 * https://app.prigent.tech/beta[.php]?ville=<slug>
 */
date_default_timezone_set('Europe/Paris');

$cityNames = [
    'livry' => 'Livry',
    'combs-la-ville' => 'Combs-la-Ville',
    'vert-saint-denis' => 'Vert-Saint-Denis',
    'lieusaint' => 'Lieusaint',
];
$ville = $_GET['ville'] ?? '';
$villeName = $cityNames[$ville] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Bêta WebiArtisan — Accès anticipé</title>
  <meta name="description" content="Rejoignez la bêta fermée de WebiArtisan sur Android : artisans locaux, carte jouable, check-in, quêtes et combats de boss." />
  <link rel="icon" href="/favicon.svg" type="image/svg+xml" />
  <style>
    :root {
      --cream: #FEF8EC;
      --brown: #2B2118;
      --green: #2D6A4F;
      --gold: #C07A2E;
      --muted: #7a6f63;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
      background: var(--cream);
      color: var(--brown);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 24px 16px 48px;
    }
    .logo { width: 84px; height: 84px; margin-bottom: 8px; }
    .badge-android {
      display: inline-flex; align-items: center; gap: 6px;
      background: var(--green); color: #fff;
      padding: 6px 14px; border-radius: 999px;
      font-size: 0.85rem; font-weight: 600; margin: 8px 0 16px;
    }
    h1 { font-size: 1.6rem; text-align: center; max-width: 520px; }
    h1 .accent { color: var(--gold); }
    .lead { color: var(--muted); text-align: center; max-width: 480px; margin-top: 10px; line-height: 1.5; }
    .card {
      background: #fff; border-radius: 18px;
      box-shadow: 0 8px 30px rgba(43, 33, 24, 0.08);
      padding: 24px; width: 100%; max-width: 440px; margin-top: 24px;
    }
    .card h2 { font-size: 1.05rem; margin-bottom: 6px; }
    .card p.small { color: var(--muted); font-size: 0.85rem; margin-bottom: 14px; }
    form { display: flex; flex-direction: column; gap: 10px; }
    input[type="email"] {
      padding: 14px 16px; font-size: 1rem;
      border: 2px solid #e5dfd2; border-radius: 12px; width: 100%;
    }
    input[type="email"]:focus { outline: none; border-color: var(--gold); }
    button {
      background: var(--gold); color: #fff; border: none;
      padding: 14px; font-size: 1rem; font-weight: 700;
      border-radius: 12px; cursor: pointer;
    }
    button:disabled { opacity: 0.7; cursor: wait; }
    .error { color: #c0392b; font-size: 0.85rem; min-height: 1.2em; }
    .ios-note {
      background: #fff7e0; border: 1px solid #f0d98c;
      color: #8a6d1d; font-size: 0.85rem;
      padding: 10px 14px; border-radius: 10px; margin-top: 14px;
      display: none;
    }
    .features { list-style: none; margin-top: 18px; width: 100%; max-width: 440px; }
    .features li {
      background: #fff; border-radius: 12px; padding: 10px 16px;
      margin-bottom: 8px; font-size: 0.9rem;
      box-shadow: 0 2px 8px rgba(43, 33, 24, 0.05);
    }
    .success { display: none; text-align: center; }
    .success .big { font-size: 2.4rem; }
    .success a.play {
      display: inline-block; margin-top: 12px;
      background: var(--green); color: #fff; text-decoration: none;
      padding: 12px 20px; border-radius: 12px; font-weight: 700;
    }
    .links { margin-top: 28px; text-align: center; font-size: 0.85rem; color: var(--muted); }
    .links a { color: var(--green); }
  </style>
</head>
<body>
  <img class="logo" src="/logo.svg" alt="WebiArtisan" />
  <span class="badge-android">🤖 Android uniquement — iOS bientôt</span>
  <h1>Devenez <span class="accent">bêta-testeur</span><?= $villeName ? ' à ' . htmlspecialchars($villeName) : '' ?></h1>
  <p class="lead">
    WebiArtisan ouvre en <strong>accès anticipé</strong> : la carte de vos artisans devient un jeu —
    ramassez des déchets, faites des check-in, relevez des quêtes et affrontez le Big Brother. 🎩🏭
  </p>

  <div class="card" id="signup-card">
    <h2>Je rejoins la bêta fermée</h2>
    <p class="small">Laissez l'email de votre compte Google Play : on vous ajoute à la liste des testeurs sous 24-48 h et vous recevez le lien d'installation.</p>
    <form id="beta-form" novalidate>
      <input type="email" name="email" placeholder="votre@email.fr" autocomplete="email" required />
      <button type="submit" id="submit-btn">Je m'inscris à la bêta</button>
      <div class="error" id="form-error"></div>
    </form>
    <div class="ios-note" id="ios-note">
      🍏 Vous êtes sur iPhone ? L'app n'est pas encore sur iOS — laissez quand même votre email, on vous prévient dès sa sortie.
    </div>
  </div>

  <div class="card success" id="success-card">
    <div class="big">🎉</div>
    <h2>C'est noté !</h2>
    <p class="small">Votre email est dans la liste. Dès son ajout à la bêta (24-48 h), ce lien vous permet d'installer l'app depuis le Play Store :</p>
    <a class="play" id="play-link" href="#" target="_blank" rel="noopener">Rejoindre le test sur Google Play</a>
  </div>

  <ul class="features">
    <li>🗺️ Carte des artisans de votre ville, en 2D ou 3D</li>
    <li>🗑️ Ramassez des déchets, trouvez des trésors 💎</li>
    <li>📍 Check-in chez les artisans = énergie et XP</li>
    <li>📜 Quêtes quotidiennes, badges, podium des nettoyeurs</li>
    <li>⚔️ Duels contre le Big Brother : quiz, échecs, cartes</li>
  </ul>

  <p class="links">
    Vous êtes une équipe ? <a href="/plaquettes/beta-combs.pdf" target="_blank" rel="noopener">Téléchargez l'affiche à imprimer (PDF)</a><br />
    Déjà testeur ? <a href="https://play.google.com/apps/testing/tech.prigent.webiartisan" target="_blank" rel="noopener">Ouvrir le test Google Play</a>
  </p>

  <script>
    const API = 'https://api.prigent.tech/api/beta/signup';
    const VILLE = <?= json_encode($ville) ?>;

    if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
      document.getElementById('ios-note').style.display = 'block';
    }

    document.getElementById('beta-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.target;
      const email = form.email.value.trim().toLowerCase();
      const errorEl = document.getElementById('form-error');
      const btn = document.getElementById('submit-btn');
      errorEl.textContent = '';

      if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
        errorEl.textContent = 'Email invalide.';
        return;
      }
      btn.disabled = true;
      btn.textContent = 'Envoi…';
      try {
        const res = await fetch(API, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email, city: VILLE || undefined }),
        });
        const json = await res.json();
        if (json.success) {
          document.getElementById('play-link').href = json.data.testing_url;
          document.getElementById('signup-card').style.display = 'none';
          document.getElementById('success-card').style.display = 'block';
        } else {
          errorEl.textContent = json.error || 'Erreur, réessayez.';
          btn.disabled = false;
          btn.textContent = "Je m'inscris à la bêta";
        }
      } catch (err) {
        errorEl.textContent = 'Réseau indisponible, réessayez.';
        btn.disabled = false;
        btn.textContent = "Je m'inscris à la bêta";
      }
    });
  </script>
</body>
</html>
