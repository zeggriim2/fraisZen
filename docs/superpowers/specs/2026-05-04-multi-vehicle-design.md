# Multi-véhicule par compte utilisateur

**Date** : 2026-05-04  
**Statut** : Approuvé

## Contexte

Actuellement, `vehicleType` et `vehiclePower` sont saisis manuellement à chaque dépense trajet. Une personne peut utiliser plusieurs véhicules (voiture + moto) et doit ressaisir les infos à chaque fois. Le `KilometricAllowanceCalculator` supporte déjà plusieurs véhicules en parallèle — c'est uniquement un problème UX.

## Décisions

- Les véhicules sont partagés au niveau du **compte utilisateur** (pas par personne)
- Gérés dans la **page Paramètres** (section dédiée)
- Dans le modal de dépense, le sélecteur **pré-remplit** type/puissance/électrique sans bloquer la saisie manuelle
- `VehicleType` enum déplacé de `Expense/Domain/Enum/` vers `SharedKernel/Domain/Enum/`

## Architecture backend

### Nouveau bounded context `src/Vehicle/`

```
src/Vehicle/
  Domain/
    Entity/Vehicle.php
    ValueObject/VehicleId.php
    Repository/VehicleRepositoryInterface.php
  Application/
    Command/CreateVehicle/CreateVehicleCommand.php
    Command/CreateVehicle/CreateVehicleCommandHandler.php
    Command/UpdateVehicle/UpdateVehicleCommand.php
    Command/UpdateVehicle/UpdateVehicleCommandHandler.php
    Command/DeleteVehicle/DeleteVehicleCommand.php
    Command/DeleteVehicle/DeleteVehicleCommandHandler.php
    Query/GetVehiclesByUser/GetVehiclesByUserQuery.php
    Query/GetVehiclesByUser/GetVehiclesByUserQueryHandler.php
  Infrastructure/
    Http/Vehicle/ListController.php
    Http/Vehicle/CreateController.php
    Http/Vehicle/UpdateController.php
    Http/Vehicle/DeleteController.php
    Persistence/DoctrineVehicleRepository.php
```

### Table `vehicle`

| Colonne        | Type           | Notes                    |
|----------------|----------------|--------------------------|
| id             | varchar(36)    | UUID v4, PK              |
| user_id        | varchar(36)    | référence User.id        |
| name           | varchar(100)   | ex: "Voiture principale" |
| vehicle_type   | varchar(20)    | car / motorcycle / moped |
| vehicle_power  | smallint NULL  | null pour cyclomoteur    |
| is_electric    | boolean        |                          |
| created_at     | datetime       |                          |

### Routes API

| Méthode | Route                  | Description                      |
|---------|------------------------|----------------------------------|
| GET     | `/api/vehicles`        | Liste des véhicules de l'user    |
| POST    | `/api/vehicles`        | Créer un véhicule                |
| PUT     | `/api/vehicles/{id}`   | Modifier (ownership check)       |
| DELETE  | `/api/vehicles/{id}`   | Supprimer (ownership check)      |

### Routing config

Nouveau fichier `config/routes/api/vehicle.yaml` avec prefix `/api/vehicles` et `name_prefix: vehicle_`.

## Refactoring `VehicleType`

- Déplacer `src/Expense/Domain/Enum/VehicleType.php` → `src/SharedKernel/Domain/Enum/VehicleType.php`
- Mettre à jour les imports dans `TravelExpense`, `KilometricAllowanceCalculator`, `FavoriteRoute`, et tous les handlers concernés

## Architecture frontend

### Nouveaux fichiers

- `frontend/src/api/vehicleApi.ts` — CRUD
- `frontend/src/stores/vehicleStore.ts` — Pinia store, chargé au démarrage dans `App.vue`

### Fichiers modifiés

**`frontend/src/types/index.ts`**
```ts
interface Vehicle {
  id: string
  name: string
  vehicleType: VehicleType
  vehiclePower: number | null
  isElectric: boolean
}
```

**`frontend/src/views/SettingsView.vue`**  
Nouvelle section "Mes véhicules" avec liste et formulaire inline (add/edit/delete). Même pattern que les trajets favoris dans `PersonModal`.

**`frontend/src/components/expense/ExpenseModal.vue`**  
Dans le bloc trajet, sélecteur de véhicule en haut des champs type/puissance :
- Visible uniquement si `vehicleStore.vehicles.length > 0`
- Option "Aucun" (saisie manuelle)
- Sélectionner un véhicule pré-remplit les champs, qui restent éditables
- Si l'utilisateur modifie manuellement après sélection, le sélecteur repasse sur "Aucun"

**`frontend/src/App.vue`**  
Ajout de `vehicleStore.fetchAll()` dans `onMounted` (aux côtés de `personStore.fetchAll()`).

## Cas limites

- **Suppression d'un véhicule utilisé** : les dépenses existantes conservent leurs données (pas de FK, pas de cascade)
- **Cyclomoteur** : `vehiclePower` null, `isElectric` false — cohérent avec l'existant
- **Aucun véhicule enregistré** : sélecteur absent dans `ExpenseModal`, comportement actuel inchangé
- **Ownership** : chaque PUT/DELETE vérifie que `vehicle.userId === user.id`

## Tests

- `CreateVehicleCommandHandlerTest` — création avec données valides
- `GetVehiclesByUserQueryHandlerTest` — retourne uniquement les véhicules de l'user connecté