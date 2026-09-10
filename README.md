# FraisZen — Frais réels d'impôt, sans la prise de tête

[![CI](https://github.com/zeggriim2/fraisZen/actions/workflows/ci.yaml/badge.svg)](https://github.com/zeggriim2/fraisZen/actions/workflows/ci.yaml)
[![CD — Production](https://github.com/zeggriim2/fraisZen/actions/workflows/cd-prod.yml/badge.svg)](https://github.com/zeggriim2/fraisZen/actions/workflows/cd-prod.yml)
[![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php)](https://www.php.net)
[![Symfony 8](https://img.shields.io/badge/Symfony-8.0-black?logo=symfony)](https://symfony.com)
[![Vue 3](https://img.shields.io/badge/Vue-3-4FC08D?logo=vue.js)](https://vuejs.org)

**FraisZen** est une application web qui aide les salariés français à déclarer leurs frais professionnels réels aux impôts. Elle calcule automatiquement les indemnités kilométriques, suit les dépenses du quotidien et génère les documents nécessaires à la déclaration de revenus.

> Chaque année, des milliers de salariés laissent de l'argent sur la table en optant par défaut pour l'abattement forfaitaire de 10 %. FraisZen vous dit si les frais réels sont plus avantageux — et fait les calculs à votre place.

---

## Documentation

- [Documentation fonctionnelle](docs/functional-documentation.md) — proposition de valeur, parcours utilisateur, fonctionnalités existantes et roadmap.
- [Documentation technique](docs/technical-documentation.md) — architecture, stack, API, Docker, qualité, sécurité et conventions de contribution.

---

## Fonctionnalités

### Saisie des dépenses
- **Trajets domicile–travail** — barème kilométrique officiel (3 à 7 CV fiscaux), voiture ou moto, avec majoration +20 % pour les véhicules électriques
- **Télétravail** — déduction forfaitaire par jour (2,70 €/jour en 2025)
- **Péages, repas professionnels, parking** — saisie manuelle ou import automatique depuis un relevé bancaire CSV
- **Trajets récurrents (favoris)** — définissez vos trajets habituels en une fois, réutilisez-les en un clic
- **Calcul de distance automatique** — saisissez les adresses, la distance est calculée via OpenRouteService
- **Génération en masse** — créez des dizaines de trajets récurrents en quelques secondes via le générateur calendaire

### Analyse & intelligence
- **Seuil de rentabilité** — comparaison automatique frais réels vs forfait 10 % avec indication du gain fiscal
- **Comparaison inter-annuelle** — visualisez l'évolution de vos déductions sur 5 ans
- **Foyer fiscal multi-personnes** — gérez les dépenses de plusieurs membres du foyer depuis un seul compte

### Export & déclaration
- **Export PDF formaté** — document prêt à joindre à votre dossier, avec barème appliqué et récapitulatif par catégorie
- **Export CSV** — fichier compatible Excel avec le détail de chaque trajet
- **Aide Cerfa 2042** — affichage direct de la case à renseigner (1AK ou 1BK selon le déclarant)

### Administration
- Interface d'administration dédiée (gestion des utilisateurs, export global)
- Abonnement via Stripe avec gestion automatique des accès

---

## Stack technique

| Couche | Technologies |
|--------|-------------|
| **Backend** | PHP 8.5 · Symfony 8 · FrankenPHP (Caddy) · Doctrine ORM 3 · MySQL 8 |
| **Frontend** | Vue 3 (Composition API) · Vite · Pinia · Tailwind CSS · TypeScript |
| **Architecture** | DDD + CQRS (command.bus / query.bus via Symfony Messenger) |
| **Tests** | Pest PHP (unit) · Playwright (browser) |
| **CI/CD** | GitHub Actions → Docker image (GHCR) → déploiement VPS sur tag |
| **PDF** | dompdf · templates Twig |
| **Auth** | JWT (LexikJWTAuthenticationBundle) · reset password |
| **Paiement** | Stripe (webhooks · SubscriptionMiddleware) |

---

## Démarrage rapide (développement)

### Prérequis

- [Docker](https://www.docker.com/) + Docker Compose v2.10+
- [Node.js](https://nodejs.org/) 20+ (pour le frontend en dehors de Docker)

### Installation

```bash
# 1. Cloner le dépôt
git clone git@github.com:zeggriim2/fraisZen.git
cd fraisZen

# 2. Construire les images et démarrer les conteneurs
make start

# 3. Charger les données de test
make fixtures

# 4. Démarrer le serveur de développement frontend (dans un autre terminal)
cd frontend && npm install && npm run dev
```

L'application est accessible sur **https://localhost** (certificat TLS auto-signé).

### Comptes de test

| Email | Mot de passe | Rôle | Abonnement |
|-------|-------------|------|-----------|
| `admin@fraisreel.fr` | `Admin1234!` | Admin | actif |
| `alice@example.com` | `Test1234!` | Utilisateur | actif |
| `bob@example.com` | `Test1234!` | Utilisateur | actif |
| `carol@example.com` | `Test1234!` | Utilisateur | aucun (→ 402) |

---

## Commandes disponibles

```bash
make start          # build + démarrage des conteneurs
make up             # démarrage sans rebuild
make down           # arrêt des conteneurs
make logs           # logs en temps réel
make bash           # shell dans le conteneur FrankenPHP

make fixtures       # reset BDD + jeu de données complet
make cc             # vider le cache Symfony
make sf c='...'     # exécuter une commande bin/console
make composer c='...'  # exécuter une commande composer

make test           # tests unitaires Pest
make test-browser   # tests Playwright (navigateur)
make analyse        # lint + PHPStan + Psalm + TypeScript + Dockerfile
```

---

## Architecture

Le projet suit une architecture **DDD + CQRS** avec des contextes bornés indépendants.

```
src/
├── Auth/           # JWT, inscription, reset password
├── Person/         # Agrégat Person (lié à un User)
├── Expense/        # Toutes les dépenses + export PDF/CSV
│   ├── Domain/     # Entités, Value Objects, barème kilométrique
│   ├── Application/
│   │   ├── Command/   # Handlers write (créer, modifier, supprimer)
│   │   └── Query/     # Handlers read (résumé, liste)
│   └── Infrastructure/
│       ├── Http/      # Contrôleurs minces (→ bus)
│       └── Persistence/  # Repositories Doctrine
├── Billing/        # Stripe, SubscriptionMiddleware
├── Admin/          # Dashboard admin
└── SharedKernel/   # CommandBusInterface, QueryBusInterface
```

**Hiérarchie des dépenses (Single Table Inheritance) :**
`Expense` (base) → `TravelExpense` · `RemoteWorkExpense` · `TollExpense` · `MealExpense` · `ParkingExpense`

---

## Tests

```bash
make test              # tests unitaires (Pest)
make test-browser      # tests navigateur (Playwright)
make analyse           # analyse statique complète
```

La CI vérifie à chaque push : PHP CS Fixer · PHPStan (niveau 5) · Psalm · TypeScript · Hadolint · tests Pest · tests Playwright.

---

## Déploiement

Le déploiement en production est déclenché automatiquement à la création d'un tag Git :

```bash
git tag V1.x.x && git push origin V1.x.x
```

Le workflow GitHub Actions construit l'image Docker, la pousse sur GitHub Container Registry (`ghcr.io`) et la déploie sur le VPS via SSH.

---

## Licence

MIT — voir [LICENSE](LICENSE).
