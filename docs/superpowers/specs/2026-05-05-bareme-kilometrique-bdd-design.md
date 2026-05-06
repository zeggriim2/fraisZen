# Design — Barème kilométrique en BDD avec CRUD admin

**Date :** 2026-05-05
**Contexte :** Ajouter le barème kilométrique 2026 et rendre tous les barèmes éditables par l'admin via l'interface, en les stockant en base de données.

---

## Contexte & problème

Actuellement, les taux kilométriques sont hardcodés dans `BaremeKilometriqueProvider` (classe PHP statique). Pour 2024 et 2025, une valeur `null` indique un héritage silencieux depuis 2023. Pour 2026, le gouvernement n'a pas revalorisé les taux (identiques à 2023–2025), mais un arrêté officiel existe. L'admin ne peut pas modifier ces taux sans redéploiement.

**Objectif :** Stocker les barèmes kilométriques en BDD, les seeder pour 2023–2026, et exposer un CRUD admin complet (API + frontend).

---

## Décisions d'architecture

- L'entité `BaremeKilometrique` et son repository vivent dans le **`Expense` BC** (consommateur naturel).
- Les contrôleurs CRUD admin vivent dans **`Admin` BC** et utilisent le repository `Expense`.
- `KilometricAllowanceCalculator` devient un service injectable (plus de méthodes statiques) ; il lit la BDD et tombe en fallback sur `BaremeKilometriqueProvider` si aucune entrée n'existe pour l'année demandée.

---

## Modèle de données

**Table :** `bareme_kilometrique`

| Colonne | Type SQL | Description |
|---------|----------|-------------|
| `year`  | `INT` PK | Année fiscale (ex. 2026 = revenus 2025) |
| `rates` | `JSON`   | Structure complète des taux |

**Structure JSON du champ `rates` :**

```json
{
  "car": {
    "3": {"rate1": 0.529, "rate2": 0.316, "fixed2": 1065, "rate3": 0.370},
    "4": {"rate1": 0.606, "rate2": 0.340, "fixed2": 1330, "rate3": 0.407},
    "5": {"rate1": 0.636, "rate2": 0.357, "fixed2": 1395, "rate3": 0.427},
    "6": {"rate1": 0.665, "rate2": 0.374, "fixed2": 1457, "rate3": 0.447},
    "7": {"rate1": 0.697, "rate2": 0.394, "fixed2": 1515, "rate3": 0.470}
  },
  "motorcycle": {
    "1": {"rate1": 0.395, "rate2": 0.099, "fixed2": 891,  "rate3": 0.248},
    "3": {"rate1": 0.468, "rate2": 0.082, "fixed2": 1158, "rate3": 0.275},
    "6": {"rate1": 0.606, "rate2": 0.079, "fixed2": 1583, "rate3": 0.343}
  },
  "moped": {"rate1": 0.315, "rate2": 0.079, "fixed2": 711, "rate3": 0.198},
  "electricMultiplier": 1.20
}
```

Tranches voiture : ≤ 5 000 km / 5 001–20 000 km / > 20 000 km.  
Tranches moto/cyclo : ≤ 3 000 km / 3 001–6 000 km / > 6 000 km.

La migration seedera les années 2023, 2024, 2025 et 2026 avec ces taux (identiques selon l'arrêté du 27 mars 2023 et sa reconduction).

---

## Backend — fichiers à créer/modifier

### Expense BC (Domain + Infrastructure)

**Nouveaux fichiers :**

- `src/Expense/Domain/Entity/BaremeKilometrique.php`
  - `$year` (int, PK)
  - `$rates` (array, JSON)
  - `getRates(): array`, `setRates(array): void`, `toArray(): array`

- `src/Expense/Domain/Repository/BaremeKilometriqueRepositoryInterface.php`
  - `findByYear(int $year): ?BaremeKilometrique`
  - `findAll(): array`
  - `save(BaremeKilometrique $b): void`

- `src/Expense/Infrastructure/Persistence/DoctrineBaremeKilometriqueRepository.php`
  - Implémentation Doctrine de l'interface ci-dessus

**Fichier modifié :**

- `src/Expense/Domain/Service/KilometricAllowanceCalculator.php`
  - Constructeur injectable avec `BaremeKilometriqueRepositoryInterface`
  - Méthode privée `resolveBareme(int $year): array` : cherche en BDD, fallback sur `BaremeKilometriqueProvider::forYear()`
  - `calculateAnnualDeduction()` et `calculateForPowerAndDistance()` utilisent `resolveBareme()`

### Admin BC (Application + Infrastructure)

**Nouveaux fichiers (pattern identique à FiscalConfig) :**

```
src/Admin/Application/Query/GetBaremeKilometrique/
  GetBaremeKilometriqueQuery.php
  GetBaremeKilometriqueQueryHandler.php

src/Admin/Application/Query/ListBaremeKilometrique/
  ListBaremeKilometriqueQuery.php
  ListBaremeKilometriqueQueryHandler.php

src/Admin/Application/Command/UpsertBaremeKilometrique/
  UpsertBaremeKilometriqueCommand.php
  UpsertBaremeKilometriqueCommandHandler.php

src/Admin/Infrastructure/Http/AdminBareme/
  GetController.php   → GET  /api/admin/bareme-kilometrique/{year}
  ListController.php  → GET  /api/admin/bareme-kilometrique
  UpsertController.php→ PUT  /api/admin/bareme-kilometrique/{year}
```

**Validation dans `UpsertController` :** vérifier que le JSON reçu contient bien les clés `car` (3–7), `motorcycle` (1, 3, 6), `moped`, `electricMultiplier` avant de dispatcher la commande.

---

## Migration

Fichier Doctrine Migrations :
1. Crée la table `bareme_kilometrique` (`year INT PK`, `rates JSON NOT NULL`)
2. Insère les 4 années (2023–2026) avec les taux ci-dessus

---

## API Routes

| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/admin/bareme-kilometrique` | Liste toutes les années |
| GET | `/api/admin/bareme-kilometrique/{year}` | Barème d'une année |
| PUT | `/api/admin/bareme-kilometrique/{year}` | Créer/mettre à jour |

Toutes protégées par le rôle `ROLE_ADMIN` (via `AbstractAdminController`).

---

## Frontend

**Nouveau fichier :** `frontend/src/api/adminBaremeApi.ts`
- `listBaremes()` → GET liste
- `getBareme(year)` → GET une année
- `upsertBareme(year, rates)` → PUT

**Nouvelle vue :** `frontend/src/views/admin/BaremeKilometriqueView.vue`
- Liste des années configurées (tableau)
- Bouton "Modifier" ouvre un formulaire inline ou modal
- Formulaire structuré :
  - Section **Voitures** (5 lignes : 3–7 CV) × 4 champs (`rate1`, `rate2`, `fixed2`, `rate3`)
  - Section **Motos** (3 lignes : 1-2 CV, 3-5 CV, >5 CV) × 4 champs
  - Section **Cyclomoteur** — 4 champs
  - Champ **Multiplicateur électrique** (ex. 1.20 = +20 %)
- Validation : tous les taux > 0, multiplicateur ≥ 1

**Route Vue Router :** `/admin/bareme-kilometrique`
**Lien dans la sidebar admin existante.**

---

## Gestion des erreurs

- Si BDD vide pour une année donnée → fallback silencieux sur `BaremeKilometriqueProvider` (pas d'erreur utilisateur)
- PUT avec JSON invalide → HTTP 400 avec message explicite
- GET sur année inexistante → HTTP 404

---

## Tests

- `KilometricAllowanceCalculatorTest` — mock du repository, vérifier priorité BDD > fallback
- `UpsertBaremeKilometriqueCommandHandlerTest` — vérifier sauvegarde correcte
- Pas de test E2E frontend dans ce scope

---

## Hors scope

- Historisation des modifications de barème
- Diff visuel entre années
- Import CSV des barèmes