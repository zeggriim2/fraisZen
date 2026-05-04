# Calcul automatique de distance pour les trajets

**Date** : 2026-05-04  
**Statut** : Approuvé

## Contexte

La saisie manuelle des kilomètres pour les `TravelExpense` est le principal point de friction de l'application. Les utilisateurs doivent estimer ou calculer eux-mêmes la distance, source d'erreurs et de découragement à la saisie régulière. Cette feature ajoute l'autocomplete d'adresse et le calcul automatique de distance via API gratuite.

## Périmètre

- Autocomplete sur les champs départ/arrivée dans `ExpenseModal` (trajets ponctuels)
- Autocomplete + calcul automatique sur le formulaire de trajet favori dans `PersonModal`
- Le champ km reste toujours éditable manuellement — le calcul est un helper, jamais un bloquant

## APIs externes

| API | Usage | Clé | Limite |
|-----|-------|-----|--------|
| api-adresse.data.gouv.fr | Geocodage + autocomplete (adresses françaises) | Aucune | Raisonnable |
| OpenRouteService (ORS) | Calcul d'itinéraire routier → distance en km | Gratuite (inscription) | 2 000 req/jour |

## Flux utilisateur

```
User tape "23 rue..."
  → frontend appelle api-adresse.data.gouv.fr (debounce 300ms, min 3 chars)
  → dropdown de suggestions (label + lat/lng)
  → user sélectionne départ ET arrivée
  → frontend appelle GET /api/distance?fromLat=…&fromLng=…&toLat=…&toLng=…
  → backend appelle ORS /v2/directions/driving-car
  → retourne distance en km (arrondi à 1 décimale)
  → champ km pré-rempli, reste éditable
```

Les coordonnées lat/lng ne sont **pas persistées** — elles vivent uniquement en session pour déclencher le calcul.

## Architecture backend

### Client HTTP scoped ORS

`config/packages/http_client.yaml` — nouveau scope `openrouteservice` (même pattern que datagouv) :
```yaml
openrouteservice:
  base_uri: 'https://api.openrouteservice.org'
  headers:
    Authorization: '%env(ORS_API_KEY)%'
```

### CQRS

```
src/Expense/Application/Query/GetDistance/
  GetDistanceQuery.php          # float fromLat, fromLng, toLat, toLng
  GetDistanceQueryHandler.php   # appelle ORS, retourne float (km)
src/Expense/Infrastructure/Http/Distance/
  GetDistanceController.php     # GET /api/distance
config/routes/api/distance.yaml # prefix /api/distance, name_prefix distance_
```

Le handler appelle :
```
GET /v2/directions/driving-car?start={fromLng},{fromLat}&end={toLng},{toLat}
```
Extrait `features[0].properties.summary.distance` (mètres) → divise par 1000 → arrondit à 1 décimale.

### Gestion d'erreur backend

Si ORS est indisponible (timeout, 5xx, rate-limit) → le handler lève une `\RuntimeException` → le controller renvoie HTTP 503. Le frontend affiche un warning non-bloquant et laisse le champ km actif pour saisie manuelle.

### Variable d'environnement

- `.env` : `ORS_API_KEY=` (vide, valeur de démonstration)
- `.env.dev` : clé ORS réelle (non commitée)

## Architecture frontend

### Nouveau composable

`frontend/src/composables/useAddressAutocomplete.ts`
```ts
// Retourne : { suggestions: Ref<Suggestion[]>, search(query: string), reset() }
// Suggestion : { label: string; lat: number; lng: number }
// Debounce 300ms, déclenché à partir de 3 caractères
// Appel : GET https://api-adresse.data.gouv.fr/search/?q=…&limit=5&type=housenumber
```

### Modification expenseApi.ts

Nouvelle fonction :
```ts
getDistance(fromLat: number, fromLng: number, toLat: number, toLng: number): Promise<number>
// Appelle GET /api/distance, retourne km (float)
```

### Modification ExpenseModal.vue

Dans le bloc trajet (`type === 'travel'`) :
- Champs départ et arrivée : input texte avec dropdown de suggestions via `useAddressAutocomplete`
- Quand départ ET arrivée sont sélectionnés depuis le dropdown (lat/lng disponibles) → appel automatique `getDistance` → pré-remplit le champ km
- Modification manuelle du champ km après auto-calcul : aucun recalcul silencieux
- Si `getDistance` échoue (503) → warning inline sous le champ km : "Calcul indisponible, vérifiez votre connexion" — champ km reste actif

### Modification PersonModal.vue (trajets favoris)

Même traitement sur les champs départ/arrivée du formulaire de trajet favori :
- Autocomplete identique
- Auto-calcul de `distanceKm` à la sélection des deux adresses
- Distance stockée dans le favori → pré-remplit le modal de dépense (comportement existant inchangé)

## Cas limites

| Cas | Comportement |
|-----|-------------|
| Adresse sans résultat | Dropdown vide, saisie libre conservée, pas de calcul auto |
| Un seul champ sélectionné | Pas d'appel ORS |
| ORS indisponible / 503 | Warning inline, km éditable manuellement |
| Utilisateur modifie km après auto-calcul | Valeur manuelle conservée, pas de recalcul |
| Adresse hors France | api-adresse.data.gouv.fr ne couvre que la France — autocomplete vide, saisie libre |
| Trajet favori sans départ/arrivée | Comportement actuel inchangé |

## Tests

**Backend :**
- `GetDistanceQueryHandlerTest` — mock client HTTP ORS, vérifie conversion mètres → km et arrondi
- `GetDistanceQueryHandlerTest` — ORS timeout → RuntimeException levée

**Frontend :**
- `useAddressAutocomplete` — debounce respecté, reset entre deux saisies, min 3 chars
- `ExpenseModal` — sélection départ + arrivée déclenche `getDistance`, champ km pré-rempli ; modification manuelle post-calcul ne redéclenche pas d'appel

Les appels ORS et api-adresse.data.gouv.fr sont mockés dans tous les tests (pas de dépendance réseau).

## Fichiers non modifiés

- `TravelExpense.php` — déjà `departure`, `arrival`, `distanceKm`, pas de changement de schéma
- `FavoriteRoute.php` — déjà `departure`, `arrival`, `distanceKm`, pas de changement de schéma
- Aucune migration Doctrine nécessaire