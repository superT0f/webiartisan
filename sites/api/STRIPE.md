# Intégration Stripe - Paiement en Ligne

Documentation de l'intégration Stripe pour WebIArtisan.

## ✅ Configuration Terminée

| Étape | Statut |
|-------|--------|
| Compte Stripe test | ✅ Créé le 6 mars 2026 |
| Variables d'environnement | ✅ Configurées dans `.env` |
| SDK Stripe PHP | ✅ Installé v16.6.0 |
| Migration SQL | ✅ Exécutée en production |
| Service Stripe | ✅ `api/lib/StripeService.php` |
| Routes API | ✅ `api/routes/payments.php` |
| Model Payment | ✅ `api/models/PaymentModel.php` |
| Page paiement Vue | ✅ `app/src/components/PaymentPage.vue` |
| Route frontend | ✅ `/pay/:token` |
| Webhook CLI | ✅ `scripts/payments/stripe-webhook.sh` |

## 🔑 Variables d'environnement

```env
STRIPE_PUBLISHABLE_KEY=pk_test_51T849uHxefs...
STRIPE_SECRET_KEY=sk_test_51T849uHxefs...
STRIPE_WEBHOOK_SECRET=whsec_...  # Pour prod uniquement
STRIPE_CURRENCY=eur
```

## 📡 API Endpoints

### Authentifiés (JWT requis)
| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `/payments/config` | GET | Clé publique Stripe |
| `/payments/intent` | POST | Créer PaymentIntent + lien paiement |
| `/payments/status/{id}` | GET | Statut paiement facture |
| `/payments/link/{id}` | GET | Générer lien paiement |

### Publics
| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `/payments/public/{token}` | GET | Infos facture (page client) |
| `/payments/confirm/{token}` | POST | Confirmer paiement |
| `/webhooks/stripe` | POST | Webhook Stripe |
| `/webhooks/stripe` | GET | Test webhook endpoint |

**Webhook Production**: `https://api.prigent.tech/webhooks/stripe`
| Route | Description |
|-------|-------------|
| `/pay/{token}` | Page de paiement client |

## 🚀 Démarrage rapide

### 1. Webhook local (développement)

```bash
./scripts/payments/stripe-webhook.sh
```

Ou manuellement:
```bash
stripe login
stripe listen --forward-to http://localhost:8080/api/webhooks/stripe
# Copier le secret dans .env comme STRIPE_WEBHOOK_SECRET
```

### 2. Tester le flux

```bash
./scripts/payments/test-stripe-flow.sh [FACTURE_ID] [MONTANT]
```

### 3. Cartes de test

| Numéro | Scénario |
|--------|----------|
| `4242 4242 4242 4242` | ✅ Paiement réussi |
| `4000 0000 0000 0002` | ❌ Carte refusée |
| `4000 0000 0000 9995` | 💰 Solde insuffisant |

Date: future, CVC: 3 chiffres, ZIP: 5 chiffres

## 📊 Flow de Paiement

```
┌──────────┐    POST /payments/intent    ┌──────────┐
│  Artisan │ ───────────────────────────> │   API    │
└──────────┘                               └────┬─────┘
     │                                         │
     │    {facture_id, amount, email}         │
     │    + Crée PaymentIntent Stripe         │
     │    + Crée record en BDD                │
     │    + Génère token sécurisé             │
     │<─────────────────────────────────────────┘
     │
     │    Lien: /pay/{token}
     │    ──────────────────────>
     │                         ┌──────────┐
     │                         │  Client  │
     │                         └────┬─────┘
     │                              │
     │    Saisie CB (Stripe.js)     │
     │    ─────────────────────────>│
     │                              │
     │    Confirmation Stripe       │
     │<─────────────────────────────│
     │                              │
     │    Webhook: succeeded        │
     │    ─────────────────────────>│
     │                              │
     │    MàJ BDD: status='paid'    │
     │    Email reçu envoyé         │
     │<─────────────────────────────│
```

## 🗄️ Structure BDD

### Table `payments`
```sql
- id (PK)
- facture_id (FK)
- stripe_payment_intent_id
- amount, currency
- status (pending/succeeded/failed)
- paid_at, created_at
```

### Colonnes `factures`
```sql
- payment_status (unpaid/pending/paid/failed)
- payment_token
- payment_token_expires_at
- paid_at
- stripe_payment_intent_id
```

## 🔒 Sécurité

- **PCI-DSS**: Stripe Elements (iframe) - pas de données CB touchées
- **Tokens**: JWT-like signés avec expiration 30 jours
- **Webhooks**: Vérifiés avec signature Stripe
- **Idempotence**: Clés idempotence sur création PaymentIntent

## 📧 Prochaines étapes

- [ ] Envoi email confirmation paiement
- [ ] Génération reçu PDF
- [ ] Paiement partiel / acompte
- [ ] Paiement 3x/4x via Stripe
- [ ] Prélèvement SEPA clients récurrents
- [ ] Apple Pay / Google Pay

## 🧪 Feature de Test (Super Admin)

Une feature de test complète est disponible uniquement pour les superadmins:

### Backend (`api/routes/payments-test.php`)
| Endpoint | Description |
|----------|-------------|
| `POST /payments/test/create` | Crée un PaymentIntent de test |
| `POST /payments/test/webhook-simulate` | Simule un webhook Stripe |
| `GET /payments/test/config` | Voir config Stripe (masquée) |
| `GET /payments/test/recent` | Historique des tests |

### Frontend (`app/src/views/PaymentTestView.vue`)
- Route: `/payment-test` (superadmin only)
- Menu: "🧪 Test Stripe" dans la sidebar (section Super Admin)
- Accès protégé par `meta: { superadminOnly: true }`

### Utilisation
1. Se connecter avec un compte superadmin
2. Cliquer sur "🧪 Test Stripe" dans le menu
3. Créer un paiement de test
4. Utiliser la carte `4242 4242 4242 4242`
5. Simuler le webhook si besoin
