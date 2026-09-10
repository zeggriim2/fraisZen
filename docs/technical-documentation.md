# Documentation technique FraisZen

Cette documentation décrit l’organisation technique réelle du dépôt FraisZen pour les développeurs et agents IA.

## Stack

### Backend

- PHP 8.5
- Symfony 8
- FrankenPHP, basé sur Caddy, comme serveur applicatif
- Doctrine ORM 3 avec MySQL 8
- Symfony Messenger pour les bus CQRS
- LexikJWTAuthenticationBundle pour l’authentification JWT
- Symfony Reset Password Bundle pour la réinitialisation de mot de passe
- dompdf pour les exports PDF
- Stripe PHP SDK pour les abonnements et webhooks

### Frontend

- Vue 3 avec Composition API et composants `<script setup>`
- Vite
- Pinia
- Vue Router
- Tailwind CSS
- TypeScript
- Axios

### Infrastructure locale

Le développement local passe par Docker Compose. Les commandes projet sont exposées par le `makefile` et doivent être lancées via `make`.

## Architecture générale

Le backend suit une organisation DDD + CQRS par contextes bornés.

```text
src/
  Auth/
  Person/
  Expense/
  Billing/
  Admin/
  SharedKernel/
```

Chaque contexte métier est organisé autour de trois couches :

```text
Domain/          Entités, value objects, enums, services métier, interfaces de repository
Application/     Commands, queries, handlers, DTO applicatifs
Infrastructure/  Contrôleurs HTTP, repositories Doctrine, services externes
```

Règle de conception : les contrôleurs restent minces et délèguent aux bus de commande ou de requête. La logique métier doit rester dans le domaine ou les handlers applicatifs.

## Contextes bornés

### Auth

Responsabilités :

- inscription ;
- connexion JWT ;
- profil utilisateur courant ;
- modification du profil et du mot de passe ;
- suppression de compte ;
- demande et exécution de reset password ;
- statut d’abonnement et identifiant client Stripe sur l’utilisateur.

Éléments principaux :

- `User` ;
- `ResetPasswordRequest` ;
- `UserRepositoryInterface` ;
- `DoctrineUserRepository` ;
- `AuthController`.

### Person

Responsabilités :

- gestion des personnes rattachées au compte ;
- gestion des trajets favoris par personne.

Éléments principaux :

- `Person` ;
- `FavoriteRoute` ;
- `PersonRepositoryInterface` ;
- `FavoriteRouteRepositoryInterface` ;
- contrôleurs `Person/*` et `FavoriteRoute/*`.

### Expense

Responsabilités :

- création, modification, suppression et liste des dépenses ;
- calculs de résumé annuel ;
- calcul des distances ;
- calcul des indemnités kilométriques ;
- export PDF/CSV ;
- justificatifs de parking.

Hiérarchie Doctrine des dépenses :

```text
Expense
  TravelExpense
  RemoteWorkExpense
  TollExpense
  MealExpense
  ParkingExpense
```

Le type de dépense est modélisé par `ExpenseType` : `travel`, `remote_work`, `toll`, `meal`, `parking`.

### Billing

Responsabilités :

- création de checkout Stripe ;
- accès au portail client Stripe ;
- traitement des webhooks ;
- mise à jour du statut d’abonnement ;
- protection des routes API par abonnement.

`SubscriptionMiddleware` bloque les routes `/api/*` si l’utilisateur connecté n’a pas un abonnement `active`, sauf pour :

- `/api/auth/` ;
- `/api/billing/` ;
- `/api/admin/`.

### Admin

Responsabilités :

- statistiques d’administration ;
- liste, détail et export CSV des utilisateurs ;
- mise à jour du statut d’abonnement utilisateur ;
- configuration fiscale annuelle ;
- gestion des barèmes kilométriques.

### SharedKernel

Responsabilités :

- interfaces communes de bus ;
- implémentations Messenger des bus ;
- exceptions partagées ;
- value object UUID ;
- services transverses comme les jours fériés.

## CQRS et bus Messenger

La configuration Messenger définit deux bus :

```yaml
framework:
  messenger:
    default_bus: command.bus
    buses:
      command.bus:
        middleware:
          - doctrine_transaction
      query.bus: ~
```

Les interfaces applicatives sont liées à Messenger dans `config/services.yaml` :

- `CommandBusInterface` → `MessengerCommandBus` ;
- `QueryBusInterface` → `MessengerQueryBus`.

Les handlers sont tagués automatiquement :

- `CommandHandlerInterface` sur `command.bus` ;
- `QueryHandlerInterface` sur `query.bus`.

Les commandes modifient l’état et passent par le bus de commande. Les requêtes retournent des données et passent par le bus de requête.

## Entités et calculs principaux

### Dépenses

Les dépenses utilisent une hiérarchie d’entités Doctrine. Les sous-types ajoutent leurs champs spécifiques :

- `TravelExpense` : distance, puissance fiscale, type de véhicule, électrique, départ, arrivée, aller-retour ;
- `RemoteWorkExpense` : jour de télétravail ;
- `TollExpense` : montant de péage, départ, arrivée ;
- `MealExpense` : montant repas, valeur repas à domicile, contribution employeur, sans justificatif ;
- `ParkingExpense` : montant, localisation, nom de justificatif.

### Résumé annuel

`GetExpensesSummaryQueryHandler` :

- récupère les dépenses d’une personne sur une année ;
- agrège les trajets par données utiles au barème ;
- compte les jours de télétravail ;
- additionne péages, repas et parkings ;
- applique la configuration fiscale annuelle si elle existe ;
- calcule le total annuel.

### Barème kilométrique

`KilometricAllowanceCalculator` regroupe les trajets par type de véhicule, puissance fiscale et caractère électrique, puis applique les tranches du barème.

`BaremeKilometriqueProvider` contient un barème de référence 2023, réutilisé pour 2024 et 2025 lorsque l’année ne définit pas ses propres taux. Le modèle `BaremeKilometrique` et les écrans admin permettent de gérer des barèmes persistés.

### Exports

Les exports passent par :

- `SummaryExporterInterface` ;
- `SummaryExporterRegistry` ;
- `DompdfSummaryPdfExporter` ;
- `CsvSummaryExporter`.

Les exporteurs sont tagués avec `app.summary_exporter`.

## Repositories Doctrine

Les interfaces de repositories sont définies dans le domaine, puis liées à des implémentations Doctrine dans `config/services.yaml`.

Exemples :

- `PersonRepositoryInterface` → `DoctrinePersonRepository` ;
- `ExpenseRepositoryInterface` → `DoctrineExpenseRepository` ;
- `UserRepositoryInterface` → `DoctrineUserRepository` ;
- `FiscalConfigRepositoryInterface` → `DoctrineFiscalConfigRepository`.

Cette séparation garde le domaine indépendant de Doctrine.

## Routes API principales

### Auth

| Méthode | Route | Description |
|---|---|---|
| POST | `/api/auth/login` | Connexion JWT |
| POST | `/api/auth/register` | Inscription |
| GET | `/api/auth/me` | Profil courant |
| PATCH | `/api/auth/me` | Mise à jour profil |
| PATCH | `/api/auth/me/password` | Mise à jour mot de passe |
| DELETE | `/api/auth/me` | Suppression du compte |
| POST | `/api/auth/forgot-password` | Demande de reset password |
| POST | `/api/auth/reset-password` | Exécution du reset password |

### Personnes et trajets favoris

| Méthode | Route | Description |
|---|---|---|
| GET | `/api/persons` | Liste des personnes |
| POST | `/api/persons` | Création d’une personne |
| GET | `/api/persons/{id}` | Détail d’une personne |
| PUT | `/api/persons/{id}` | Mise à jour d’une personne |
| DELETE | `/api/persons/{id}` | Suppression d’une personne |
| GET | `/api/persons/{personId}/favorite-routes` | Liste des trajets favoris |
| POST | `/api/persons/{personId}/favorite-routes` | Création d’un trajet favori |
| PUT | `/api/persons/{personId}/favorite-routes/{id}` | Mise à jour d’un trajet favori |
| DELETE | `/api/persons/{personId}/favorite-routes/{id}` | Suppression d’un trajet favori |

### Dépenses

| Méthode | Route | Description |
|---|---|---|
| GET | `/api/expenses` | Liste des dépenses filtrée par période et personne |
| POST | `/api/expenses` | Création d’une dépense |
| PATCH | `/api/expenses/{id}` | Mise à jour d’une dépense |
| DELETE | `/api/expenses/{id}` | Suppression d’une dépense |
| POST | `/api/expenses/bulk-travel` | Création en masse de trajets |
| GET | `/api/expenses/summary` | Résumé annuel JSON |
| GET | `/api/expenses/summary/pdf` | Export PDF |
| GET | `/api/expenses/summary/csv` | Export CSV |
| GET | `/api/expenses/distance` | Calcul de distance |
| GET | `/api/expenses/fiscal-config/{year}` | Valeurs fiscales annuelles utilisées par les dépenses |
| GET | `/api/baremes/{year}` | Barème kilométrique public pour une année |
| POST | `/api/expenses/{id}/receipt` | Upload justificatif parking |
| GET | `/api/expenses/{id}/receipt` | Téléchargement justificatif parking |
| DELETE | `/api/expenses/{id}/receipt` | Suppression justificatif parking |

### Billing

| Méthode | Route | Description |
|---|---|---|
| POST | `/api/billing/checkout` | Création session checkout Stripe |
| POST | `/api/billing/portal` | Création session portail client Stripe |
| POST | `/api/billing/webhook` | Réception webhooks Stripe |

### Admin

Les routes admin sont préfixées par `/api/admin/` et protégées par `ROLE_ADMIN`. Elles couvrent les utilisateurs, les statistiques, la configuration fiscale et les barèmes kilométriques.

### Jours fériés

`/api/public-holidays/{year}` expose les jours fériés d’une année via le `SharedKernel`.

## Frontend

Organisation principale :

```text
frontend/src/
  api/          Clients Axios typés
  stores/       Stores Pinia
  views/        Pages Vue Router
  components/   Composants réutilisables
  composables/  Logique Vue réutilisable
  types/        Types TypeScript partagés
  utils/        Helpers de formatage et erreurs API
```

### Clients API

- `authApi.ts` ;
- `personApi.ts` ;
- `favoriteRouteApi.ts` ;
- `expenseApi.ts` ;
- `billingApi.ts` ;
- `adminApi.ts` ;
- `http.ts` pour l’instance Axios commune.

`http.ts` ajoute le token JWT et redirige vers `/pricing` en cas de réponse HTTP 402.

### Stores Pinia

- `authStore` : utilisateur courant, session, login/register/logout ;
- `personStore` : personnes ;
- `expenseStore` : dépenses et résumé ;
- `favoriteRouteStore` : trajets favoris.

### Vues principales

- `/login`, `/register`, `/forgot-password`, `/reset-password` ;
- `/calendar` ;
- `/summary` ;
- `/trips` ;
- `/persons` ;
- `/settings` ;
- `/pricing` ;
- `/admin/*`.

Le routeur protège les routes privées par présence du token JWT et protège les routes admin par `ROLE_ADMIN`.

## Docker Compose

### Services

- `php` : application Symfony servie par FrankenPHP ;
- `database` : MySQL 8 ;
- `node` : environnement Node 22 pour Vite et le typecheck frontend ;
- `mailer` : Mailpit en environnement de développement via `compose.override.yaml`.

### Volumes

- `caddy_data` et `caddy_config` pour Caddy/FrankenPHP ;
- `database_data` pour MySQL ;
- `node_modules` pour isoler les dépendances frontend du bind mount ;
- `receipts_data` pour les justificatifs.

### Environnement

Les variables importantes incluent :

- `APP_ENV` ;
- `APP_SECRET` ;
- `DATABASE_URL` ;
- `DEFAULT_URI` ;
- `APP_SHARE_DIR` ;
- `JWT_SECRET_KEY`, `JWT_PUBLIC_KEY`, `JWT_PASSPHRASE` ;
- `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_PRICE_ID_MONTHLY`, `STRIPE_PRICE_ID_YEARLY` ;
- `ORS_API_KEY` pour les services de distance/adresse quand configurés.

Aucun secret ne doit être commité. Les fichiers `.env` locaux et secrets réels restent hors versionnement.

## Commandes Makefile utiles

Toutes les commandes projet doivent passer par `make`.

```bash
make start              # build des images puis démarrage
make up                 # démarrage sans rebuild
make down               # arrêt des conteneurs
make logs               # logs Docker Compose
make bash               # shell bash dans le conteneur php
make sh                 # shell sh dans le conteneur php
make vendor             # composer install depuis le conteneur php
make composer c='...'   # commande Composer dans le conteneur php
make sf c='...'         # commande Symfony bin/console dans le conteneur php
make cc                 # clear cache
make fixtures           # reset DB + migrations + fixtures
make test               # tests unitaires Pest
make test-browser       # tests browser Pest/Playwright
make typecheck          # TypeScript via le service node
make analyse            # lint + PHPStan + Psalm + TypeScript + Hadolint + Actionlint
```

## Tests et qualité

### Tests

- `make test` lance les tests unitaires Pest dans `tests/Unit/` avec `APP_ENV=test`.
- `make test-browser` lance les tests navigateur dans `tests/Browser/`.

### Analyse qualité

`make analyse` agrège :

- PHP CS Fixer en dry-run ;
- PHPStan ;
- Psalm ;
- TypeScript `vue-tsc --noEmit` ;
- Hadolint sur le `Dockerfile` ;
- Actionlint sur les workflows GitHub Actions.

## CI/CD GitHub Actions

Le workflow CI est `.github/workflows/ci.yaml`.

Il se déclenche sur :

- `push` vers `main` ;
- `pull_request` ;
- `workflow_dispatch`.

Jobs principaux :

- `Tests Backend` : prépare un environnement CI, installe Composer et lance Pest sur `tests/Unit/` ;
- `Code Quality` : installe les dépendances PHP et frontend, chauffe le cache Symfony, lance PHP CS Fixer, PHPStan, Psalm et TypeScript ;
- `Lint` : lance Super-Linter avec plusieurs validateurs désactivés pour éviter les doublons ou contrôles non configurés.

Le déploiement production est documenté comme déclenché par tag Git, avec build d’image Docker et publication vers GHCR avant déploiement VPS.

## Sécurité

### Authentification

L’API utilise JWT sur le firewall `/api`. Les routes publiques explicites sont :

- login ;
- register ;
- forgot password ;
- reset password ;
- webhook billing.

Toutes les autres routes `/api` exigent un utilisateur authentifié, sauf règles plus spécifiques comme l’administration.

### Administration

`/api/admin` exige `ROLE_ADMIN` via `security.yaml`.

### Ownership multi-utilisateur

Les données métier sont rattachées à un utilisateur ou à une personne. Les handlers et repositories doivent préserver l’isolation entre comptes. Toute évolution touchant les dépenses, personnes, exports ou justificatifs doit vérifier que l’utilisateur courant ne peut pas lire ou modifier les données d’un autre compte.

### Abonnement

Le middleware d’abonnement renvoie HTTP 402 quand l’accès API est bloqué pour un utilisateur non actif. Le frontend intercepte ce statut et redirige vers `/pricing`.

### Justificatifs et données sensibles

Les justificatifs peuvent contenir des données personnelles ou fiscales. Points de vigilance :

- ne jamais exposer les fichiers entre utilisateurs ;
- contrôler taille et type des uploads ;
- ne pas commiter de fichiers réels ;
- stocker les fichiers via le répertoire configuré, pas dans le code source.

### Secrets

Ne jamais commiter :

- clés JWT privées ;
- clés Stripe ;
- secrets webhook ;
- mots de passe de base de données réels ;
- tokens GitHub ou clés d’API externes.

Les valeurs dans les exemples CI sont des valeurs de test destinées aux workflows.

## Conventions pour agents IA

Pour toute contribution automatisée :

1. partir de `origin/develop` ;
2. ne jamais modifier directement `develop` ou `main` ;
3. créer une branche par issue contenant le numéro d’issue ;
4. ouvrir une PR vers `develop` ;
5. lier explicitement la PR avec `Issue: #XX` et `Closes #XX` ;
6. exécuter les vérifications via Docker/Makefile ;
7. ne pas installer PHP, Composer ou Node directement sur l’hôte ;
8. ne jamais merger automatiquement ;
9. documenter dans la PR les commandes réellement exécutées et leurs résultats ;
10. signaler clairement tout blocage Docker, CI ou manque de spécification.

## Ajout d’une fonctionnalité

Flux recommandé :

1. lire l’issue et vérifier les labels ;
2. créer une branche depuis `origin/develop` ;
3. écrire ou adapter un test avant le code applicatif ;
4. implémenter dans le contexte borné concerné ;
5. garder les contrôleurs minces ;
6. mettre à jour les types et clients frontend si l’API change ;
7. lancer `make test` puis `make analyse` ;
8. ouvrir une PR draft vers `develop`.

Pour une tâche purement documentation, aucun test applicatif nouveau n’est requis, mais les commandes projet demandées par l’issue doivent être exécutées et reportées.
