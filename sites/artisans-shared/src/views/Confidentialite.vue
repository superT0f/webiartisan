<script setup>
import { CITY_NAME } from '../api.js'
const updated = '29 juillet 2026'
</script>

<template>
  <div class="privacy-page page">
    <h1>Politique de confidentialité</h1>
    <p class="updated">Dernière mise à jour : {{ updated }}</p>

    <section class="card">
      <h2>🥖 Qui sommes-nous ?</h2>
      <p>
        WebiArtisan est un annuaire libre et jouable des artisans de proximité, édité par
        Christophe Prigent (France). Le service comprend des sites web par ville
        (dont {{ CITY_NAME }}) et une application Android en bêta fermée.
      </p>
      <p>Contact vie privée : <a href="mailto:webiartisan@gmail.com">webiartisan@gmail.com</a></p>
    </section>

    <section class="card">
      <h2>📦 Données que nous collectons</h2>
      <ul>
        <li><strong>Compte</strong> : adresse email, mot de passe (stocké chiffré/haché, jamais en clair), pseudo d'affichage, avatar.</li>
        <li><strong>Position GPS</strong> : utilisée <em>en temps réel</em> pour le jeu (check-in, ramassage, combats de boss). Nous ne conservons pas d'historique de vos trajets ; seuls les points de jeu validés (check-in, ramassages, ville) sont enregistrés.</li>
        <li><strong>Photos de lieux</strong> : les photos que vous publiez sont publiques. Les métadonnées (EXIF, dont la position précise) sont <strong>supprimées</strong> à l'import. Vous pouvez retirer vos photos à tout moment.</li>
        <li><strong>Statistiques de jeu</strong> : XP, niveau, énergie, inventaire, quêtes, check-ins, combats, badges.</li>
        <li><strong>Paiements</strong> : gérés par <strong>Stripe</strong>. Nous ne stockons ni numéro de carte ni données bancaires — seuls l'identifiant client Stripe et l'état de l'abonnement.</li>
        <li><strong>Technique</strong> : journaux serveur (adresse IP, horodatage) nécessaires à la sécurité et au débogage.</li>
      </ul>
    </section>

    <section class="card">
      <h2>🎯 Pourquoi nous les utilisons</h2>
      <ul>
        <li>Faire fonctionner le jeu et l'annuaire (compte, progression, carte interactive).</li>
        <li>Gérer les abonnements Premium et les droits associés.</li>
        <li>Modérer les contenus (photos signalées, avis).</li>
        <li>Vous contacter : inscription bêta, compte, incidents.</li>
        <li>Mesurer l'audience de façon respectueuse (outillage auto-hébergé, sans revente).</li>
      </ul>
      <p>Base légale : exécution du service demandé (jeu, compte, abonnement) et intérêt légitime (sécurité, amélioration du service).</p>
    </section>

    <section class="card">
      <h2>🍪 Cookies et stockage local</h2>
      <p>
        Nous utilisons uniquement le stockage nécessaire au fonctionnement : session de connexion,
        préférences (mode de carte, position récente à court terme) et mesure d'audience auto-hébergée.
        <strong>Aucun cookie publicitaire, aucune revente de données.</strong>
      </p>
    </section>

    <section class="card">
      <h2>🤝 Partage et sous-traitants</h2>
      <ul>
        <li><strong>Hébergement</strong> : Gandi SAS (France, UE) — serveurs et base de données.</li>
        <li><strong>Cartographie</strong> : MapTiler AG et OpenStreetMap (tuiles de carte ; votre navigateur télécharge les tuiles depuis leurs serveurs).</li>
        <li><strong>Paiement</strong> : Stripe, Inc. (abonnements).</li>
        <li>Les photos publiées sont visibles par tous les utilisateurs du service.</li>
      </ul>
      <p>Aucune donnée n'est vendue ni partagée à des tiers à des fins publicitaires.</p>
    </section>

    <section class="card">
      <h2>⏳ Durées de conservation</h2>
      <ul>
        <li>Compte et progression : jusqu'à la suppression du compte.</li>
        <li>Photos : jusqu'à leur retrait par vous ou par la modération.</li>
        <li>Journaux techniques : quelques semaines maximum.</li>
        <li>Inscriptions bêta : le temps de la campagne bêta.</li>
      </ul>
    </section>

    <section class="card">
      <h2>🛡️ Vos droits (RGPD)</h2>
      <p>
        Vous disposez des droits d'accès, de rectification, d'effacement, de portabilité,
        d'opposition et de limitation du traitement. Écrivez-nous à
        <a href="mailto:webiartisan@gmail.com">webiartisan@gmail.com</a> — la suppression de compte
        est aussi possible directement depuis <strong>Profil → Supprimer mon compte</strong>.
        Vous pouvez saisir la CNIL (<a href="https://www.cnil.fr" target="_blank" rel="noopener">cnil.fr</a>) en cas de réclamation.
      </p>
    </section>

    <section class="card">
      <h2>🔞 Public</h2>
      <p>
        Le service s'adresse à un public général mais n'est pas conçu pour les enfants de moins
        de 13 ans ; il ne collecte pas sciemment leurs données.
      </p>
    </section>
  </div>
</template>

<style scoped>
.privacy-page {
  max-width: 720px;
  margin: 0 auto;
  padding: 24px 16px 48px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.privacy-page h1 { margin-bottom: 0; }
.updated { color: var(--c-text-3); font-size: 0.85rem; margin-top: 4px; }
.privacy-page section { padding: 18px 20px; }
.privacy-page h2 { font-size: 1.05rem; margin: 0 0 10px; }
.privacy-page p, .privacy-page li { font-size: 0.92rem; line-height: 1.55; }
.privacy-page ul { margin: 0; padding-left: 20px; display: flex; flex-direction: column; gap: 6px; }
.privacy-page a { color: var(--c-green); font-weight: 600; }
</style>
