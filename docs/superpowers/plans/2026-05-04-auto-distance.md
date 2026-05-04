# Auto-Distance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter l'autocomplete d'adresse (api-adresse.data.gouv.fr) et le calcul automatique de distance (OpenRouteService via backend) sur les champs départ/arrivée des trajets.

**Architecture:** Le frontend appelle api-adresse.data.gouv.fr directement pour l'autocomplete (pas de clé requise). Le calcul de distance passe par un endpoint backend `GET /api/expenses/distance` qui appelle ORS (clé serveur). Le composant `AddressAutocompleteInput` est réutilisé dans `ExpenseModal` et `PersonModal`.

**Tech Stack:** PHP 8.5 / Symfony 8.0 / Pest v4, Vue 3 / TypeScript / Tailwind CSS, api-adresse.data.gouv.fr, OpenRouteService free tier

---

## Fichiers créés / modifiés

| Fichier | Action |
|---------|--------|
| `config/packages/framework.yaml` | Modifier — ajouter scoped client ORS |
| `.env` | Modifier — ajouter `ORS_API_KEY=` |
| `config/services.yaml` | Modifier — wirer le handler ORS |
| `src/Expense/Application/Query/GetDistance/GetDistanceQuery.php` | Créer |
| `src/Expense/Application/Query/GetDistance/GetDistanceQueryHandler.php` | Créer |
| `src/Expense/Infrastructure/Http/Distance/GetDistanceController.php` | Créer |
| `tests/Unit/Expense/Application/Query/GetDistanceQueryHandlerTest.php` | Créer |
| `frontend/src/composables/useAddressAutocomplete.ts` | Créer |
| `frontend/src/components/ui/AddressAutocompleteInput.vue` | Créer |
| `frontend/src/api/expenseApi.ts` | Modifier — ajouter `getDistance` |
| `frontend/src/composables/useRouteDistance.ts` | Modifier — utiliser backend ORS |
| `frontend/src/components/expense/ExpenseModal.vue` | Modifier — autocomplete + auto-calc |
| `frontend/src/components/person/PersonModal.vue` | Modifier — autocomplete routes favoris |

---

## Task 1: Backend – Configuration ORS

**Files:**
- Modify: `config/packages/framework.yaml`
- Modify: `.env`
- Modify: `config/services.yaml`

- [ ] **Step 1 : Ajouter le scoped client ORS dans `config/packages/framework.yaml`**

Ajouter après le bloc `datagouv.client` existant :

```yaml
    http_client:
        scoped_clients:
            datagouv.client:
                base_uri: 'https://calendrier.api.gouv.fr'
                headers:
                    Accept: 'application/json'
            openrouteservice.client:
                base_uri: 'https://api.openrouteservice.org'
                headers:
                    Accept: 'application/json, application/geo+json'
                    Authorization: '%env(ORS_API_KEY)%'
```

- [ ] **Step 2 : Ajouter `ORS_API_KEY` dans `.env`**

Ajouter à la fin du fichier `.env` :

```dotenv
###> openrouteservice ###
ORS_API_KEY=
###< openrouteservice ###
```

- [ ] **Step 3 : Vérifier que Symfony démarre sans erreur**

```bash
make sf c='debug:container --env-vars' 2>&1 | grep -i ors
```

Expected : ligne mentionnant `ORS_API_KEY` sans erreur.

- [ ] **Step 4 : Commit**

```bash
rtk git add config/packages/framework.yaml .env && rtk git commit -m "feat(distance): scoped HTTP client OpenRouteService + ORS_API_KEY env var"
```

---

## Task 2: Backend – GetDistanceQueryHandler (TDD)

**Files:**
- Create: `src/Expense/Application/Query/GetDistance/GetDistanceQuery.php`
- Create: `src/Expense/Application/Query/GetDistance/GetDistanceQueryHandler.php`
- Create: `tests/Unit/Expense/Application/Query/GetDistanceQueryHandlerTest.php`
- Modify: `config/services.yaml`

- [ ] **Step 1 : Créer le Query**

```php
<?php
// src/Expense/Application/Query/GetDistance/GetDistanceQuery.php
declare(strict_types=1);

namespace App\Expense\Application\Query\GetDistance;

final readonly class GetDistanceQuery
{
    public function __construct(
        public float $fromLat,
        public float $fromLng,
        public float $toLat,
        public float $toLng,
    ) {
    }
}
```

- [ ] **Step 2 : Écrire les tests en premier**

```php
<?php
// tests/Unit/Expense/Application/Query/GetDistanceQueryHandlerTest.php
declare(strict_types=1);

use App\Expense\Application\Query\GetDistance\GetDistanceQuery;
use App\Expense\Application\Query\GetDistance\GetDistanceQueryHandler;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

it('retourne la distance en km arrondie à 0.1', function () {
    $geojson = json_encode([
        'features' => [[
            'properties' => ['summary' => ['distance' => 12345.6]],
        ]],
    ]);

    $httpClient = new MockHttpClient([
        new MockResponse($geojson, ['http_code' => 200]),
    ]);

    $handler = new GetDistanceQueryHandler($httpClient);
    $result = $handler(new GetDistanceQuery(48.8566, 2.3522, 48.9021, 2.3611));

    expect($result)->toBe(12.3);
});

it('lève une RuntimeException si ORS est indisponible', function () {
    $httpClient = new MockHttpClient([
        new MockResponse('', ['http_code' => 503]),
    ]);

    $handler = new GetDistanceQueryHandler($httpClient);

    expect(fn () => $handler(new GetDistanceQuery(48.8566, 2.3522, 48.9021, 2.3611)))
        ->toThrow(\RuntimeException::class);
});

it('lève une RuntimeException si la réponse ne contient aucun itinéraire', function () {
    $geojson = json_encode(['features' => []]);

    $httpClient = new MockHttpClient([
        new MockResponse($geojson, ['http_code' => 200]),
    ]);

    $handler = new GetDistanceQueryHandler($httpClient);

    expect(fn () => $handler(new GetDistanceQuery(48.8566, 2.3522, 48.9021, 2.3611)))
        ->toThrow(\RuntimeException::class);
});
```

- [ ] **Step 3 : Lancer les tests pour confirmer qu'ils échouent**

```bash
make test c='tests/Unit/Expense/Application/Query/GetDistanceQueryHandlerTest.php'
```

Expected : FAIL — classe `GetDistanceQueryHandler` introuvable.

- [ ] **Step 4 : Implémenter le handler**

```php
<?php
// src/Expense/Application/Query/GetDistance/GetDistanceQueryHandler.php
declare(strict_types=1);

namespace App\Expense\Application\Query\GetDistance;

use App\SharedKernel\Application\Bus\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetDistanceQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    public function __invoke(GetDistanceQuery $query): float
    {
        $response = $this->httpClient->request('GET', '/v2/directions/driving-car', [
            'query' => [
                'start' => "{$query->fromLng},{$query->fromLat}",
                'end' => "{$query->toLng},{$query->toLat}",
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('ORS unavailable: HTTP '.$response->getStatusCode());
        }

        $data = $response->toArray();

        if (empty($data['features'])) {
            throw new \RuntimeException('ORS returned no route.');
        }

        $meters = (float) $data['features'][0]['properties']['summary']['distance'];

        return round($meters / 1000, 1);
    }
}
```

- [ ] **Step 5 : Wirer le handler avec le client ORS dans `config/services.yaml`**

Ajouter après le bloc `PublicHolidayService` :

```yaml
    # Distance handler — uses scoped HTTP client for OpenRouteService
    App\Expense\Application\Query\GetDistance\GetDistanceQueryHandler:
        arguments:
            $httpClient: '@openrouteservice.client'
```

- [ ] **Step 6 : Lancer les tests pour confirmer qu'ils passent**

```bash
make test c='tests/Unit/Expense/Application/Query/GetDistanceQueryHandlerTest.php'
```

Expected : 3 tests PASS.

- [ ] **Step 7 : Commit**

```bash
rtk git add src/Expense/Application/Query/GetDistance/ tests/Unit/Expense/Application/Query/GetDistanceQueryHandlerTest.php config/services.yaml && rtk git commit -m "feat(distance): GetDistanceQueryHandler + tests (ORS, TDD)"
```

---

## Task 3: Backend – GetDistanceController

**Files:**
- Create: `src/Expense/Infrastructure/Http/Distance/GetDistanceController.php`

- [ ] **Step 1 : Créer le controller**

```php
<?php
// src/Expense/Infrastructure/Http/Distance/GetDistanceController.php
declare(strict_types=1);

namespace App\Expense\Infrastructure\Http\Distance;

use App\Expense\Application\Query\GetDistance\GetDistanceQuery;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/expenses/distance', name: 'expense_distance_get', methods: [Request::METHOD_GET])]
final class GetDistanceController extends AbstractController
{
    public function __construct(private readonly QueryBusInterface $queryBus)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $fromLat = (float) $request->query->get('fromLat', 0);
        $fromLng = (float) $request->query->get('fromLng', 0);
        $toLat   = (float) $request->query->get('toLat', 0);
        $toLng   = (float) $request->query->get('toLng', 0);

        try {
            $km = $this->queryBus->ask(new GetDistanceQuery($fromLat, $fromLng, $toLat, $toLng));

            return $this->json(['distanceKm' => $km]);
        } catch (\Throwable) {
            return $this->json(
                ['error' => 'Distance calculation unavailable'],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }
    }
}
```

- [ ] **Step 2 : Vérifier que la route est bien enregistrée**

```bash
make sf c='debug:router' 2>&1 | grep distance
```

Expected : ligne `expense_distance_get   GET  /api/expenses/distance`

- [ ] **Step 3 : Commit**

```bash
rtk git add src/Expense/Infrastructure/Http/Distance/ && rtk git commit -m "feat(distance): GET /api/expenses/distance controller"
```

---

## Task 4: Frontend – useAddressAutocomplete composable

**Files:**
- Create: `frontend/src/composables/useAddressAutocomplete.ts`

- [ ] **Step 1 : Créer le composable**

```ts
// frontend/src/composables/useAddressAutocomplete.ts
import { ref } from 'vue'

export interface AddressSuggestion {
  label: string
  lat: number
  lng: number
}

export async function geocodeFirstResult(query: string): Promise<{ lat: number; lng: number } | null> {
  if (query.trim().length < 3) return null
  try {
    const url = `https://api-adresse.data.gouv.fr/search/?q=${encodeURIComponent(query)}&limit=1`
    const res = await fetch(url)
    if (!res.ok) return null
    const data = await res.json() as { features: Array<{ geometry: { coordinates: [number, number] } }> }
    if (!data.features.length) return null
    const [lng, lat] = data.features[0].geometry.coordinates
    return { lat, lng }
  } catch {
    return null
  }
}

export function useAddressAutocomplete() {
  const suggestions = ref<AddressSuggestion[]>([])
  let debounceTimer: ReturnType<typeof setTimeout> | null = null

  function search(query: string): void {
    if (debounceTimer) clearTimeout(debounceTimer)
    if (query.trim().length < 3) {
      suggestions.value = []
      return
    }
    debounceTimer = setTimeout(async () => {
      try {
        const url = `https://api-adresse.data.gouv.fr/search/?q=${encodeURIComponent(query)}&limit=5`
        const res = await fetch(url)
        if (!res.ok) { suggestions.value = []; return }
        const data = await res.json() as {
          features: Array<{
            properties: { label: string }
            geometry: { coordinates: [number, number] }
          }>
        }
        suggestions.value = data.features.map(f => ({
          label: f.properties.label,
          lat: f.geometry.coordinates[1],
          lng: f.geometry.coordinates[0],
        }))
      } catch {
        suggestions.value = []
      }
    }, 300)
  }

  function reset(): void {
    if (debounceTimer) clearTimeout(debounceTimer)
    suggestions.value = []
  }

  return { suggestions, search, reset }
}
```

- [ ] **Step 2 : Vérifier le typecheck**

```bash
cd frontend && npm run typecheck
```

Expected : aucune erreur de type.

- [ ] **Step 3 : Commit**

```bash
rtk git add frontend/src/composables/useAddressAutocomplete.ts && rtk git commit -m "feat(distance): composable useAddressAutocomplete (api-adresse.data.gouv.fr)"
```

---

## Task 5: Frontend – AddressAutocompleteInput component

**Files:**
- Create: `frontend/src/components/ui/AddressAutocompleteInput.vue`

- [ ] **Step 1 : Créer le composant**

```vue
<!-- frontend/src/components/ui/AddressAutocompleteInput.vue -->
<template>
  <div class="relative">
    <input
      :value="modelValue"
      :placeholder="placeholder"
      type="text"
      class="w-full rounded-lg border-gray-300 shadow-sm text-sm"
      @input="onInput"
      @blur="onBlur"
      @keydown.escape="open = false"
    />
    <div
      v-if="open && suggestions.length"
      class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto"
    >
      <button
        v-for="s in suggestions"
        :key="s.label"
        type="button"
        class="w-full px-3 py-2 text-left text-sm text-gray-800 hover:bg-indigo-50 border-b border-gray-100 last:border-0 truncate"
        @mousedown.prevent="select(s)"
      >
        {{ s.label }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useAddressAutocomplete } from '@/composables/useAddressAutocomplete'
import type { AddressSuggestion } from '@/composables/useAddressAutocomplete'

defineProps<{ modelValue: string; placeholder?: string }>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
  'select': [suggestion: AddressSuggestion]
}>()

const { suggestions, search, reset } = useAddressAutocomplete()
const open = ref(false)

function onInput(e: Event) {
  const val = (e.target as HTMLInputElement).value
  emit('update:modelValue', val)
  search(val)
  open.value = true
}

function select(s: AddressSuggestion) {
  emit('update:modelValue', s.label)
  emit('select', s)
  reset()
  open.value = false
}

function onBlur() {
  // Délai pour laisser le mousedown du bouton se déclencher avant la fermeture
  setTimeout(() => { open.value = false }, 150)
}
</script>
```

- [ ] **Step 2 : Vérifier le typecheck**

```bash
cd frontend && npm run typecheck
```

Expected : aucune erreur de type.

- [ ] **Step 3 : Commit**

```bash
rtk git add frontend/src/components/ui/AddressAutocompleteInput.vue && rtk git commit -m "feat(distance): composant AddressAutocompleteInput réutilisable"
```

---

## Task 6: Frontend – expenseApi.getDistance + useRouteDistance

**Files:**
- Modify: `frontend/src/api/expenseApi.ts`
- Modify: `frontend/src/composables/useRouteDistance.ts`

- [ ] **Step 1 : Ajouter `getDistance` dans `expenseApi.ts`**

Dans `frontend/src/api/expenseApi.ts`, ajouter dans l'objet `expenseApi` après `getFiscalConfig` :

```ts
  getDistance: (fromLat: number, fromLng: number, toLat: number, toLng: number) =>
    http.get<{ distanceKm: number }>('/expenses/distance', {
      params: { fromLat, fromLng, toLat, toLng },
    }).then(r => r.data.distanceKm),
```

- [ ] **Step 2 : Réécrire `useRouteDistance.ts` pour utiliser le backend ORS**

Remplacer le contenu entier de `frontend/src/composables/useRouteDistance.ts` :

```ts
import { ref } from 'vue'
import { expenseApi } from '@/api/expenseApi'
import { geocodeFirstResult } from '@/composables/useAddressAutocomplete'

export function useRouteDistance() {
  const calculating = ref(false)
  const calcError = ref('')

  async function calculate(departure: string, arrival: string): Promise<number | null> {
    if (!departure.trim() || !arrival.trim()) {
      calcError.value = "Saisissez le départ et l'arrivée."
      return null
    }
    calculating.value = true
    calcError.value = ''
    try {
      const [from, to] = await Promise.all([
        geocodeFirstResult(departure),
        geocodeFirstResult(arrival),
      ])
      if (!from) { calcError.value = `Adresse introuvable : "${departure}"`; return null }
      if (!to)   { calcError.value = `Adresse introuvable : "${arrival}"`; return null }
      return await expenseApi.getDistance(from.lat, from.lng, to.lat, to.lng)
    } catch {
      calcError.value = 'Calcul impossible. Vérifiez les adresses ou votre connexion.'
      return null
    } finally {
      calculating.value = false
    }
  }

  return { calculating, calcError, calculate }
}
```

- [ ] **Step 3 : Vérifier le typecheck**

```bash
cd frontend && npm run typecheck
```

Expected : aucune erreur de type.

- [ ] **Step 4 : Commit**

```bash
rtk git add frontend/src/api/expenseApi.ts frontend/src/composables/useRouteDistance.ts && rtk git commit -m "feat(distance): expenseApi.getDistance + useRouteDistance utilise backend ORS"
```

---

## Task 7: Frontend – ExpenseModal autocomplete + auto-calc depuis les favoris

**Files:**
- Modify: `frontend/src/components/expense/ExpenseModal.vue`

Les champs départ/arrivée du bloc trajet utilisent déjà des `<input type="text">` simples. Ce task les remplace par `AddressAutocompleteInput` et ajoute l'auto-calcul lors de l'application d'un favori.

- [ ] **Step 1 : Ajouter l'import et les refs de coordonnées dans le `<script setup>`**

Dans la section `<script setup lang="ts">` de `ExpenseModal.vue`, après l'import de `useRouteDistance`, ajouter :

```ts
import AddressAutocompleteInput from '@/components/ui/AddressAutocompleteInput.vue'
import type { AddressSuggestion } from '@/composables/useAddressAutocomplete'
```

Et après `const { calculating, calcError, calculate } = useRouteDistance()`, ajouter :

```ts
const departureCoords = ref<{ lat: number; lng: number } | null>(null)
const arrivalCoords = ref<{ lat: number; lng: number } | null>(null)

function onDepartureSelect(s: AddressSuggestion) {
  departureCoords.value = { lat: s.lat, lng: s.lng }
  autoCalcIfReady()
}

function onArrivalSelect(s: AddressSuggestion) {
  arrivalCoords.value = { lat: s.lat, lng: s.lng }
  autoCalcIfReady()
}

async function autoCalcIfReady() {
  if (!departureCoords.value || !arrivalCoords.value) return
  calculating.value = true
  calcError.value = ''
  try {
    const km = await expenseApi.getDistance(
      departureCoords.value.lat, departureCoords.value.lng,
      arrivalCoords.value.lat, arrivalCoords.value.lng,
    )
    form.value.distanceKm = km
  } catch {
    calcError.value = 'Calcul indisponible, saisie manuelle.'
  } finally {
    calculating.value = false
  }
}
```

Ajouter aussi l'import `expenseApi` si pas déjà présent (il l'est déjà dans ce fichier).

- [ ] **Step 2 : Modifier `applyFavorite` pour auto-calculer la distance**

Remplacer la fonction `applyFavorite` existante :

```ts
async function applyFavorite(fav: typeof favorites.value[number]) {
  form.value.departure = fav.departure
  form.value.arrival = fav.arrival
  form.value.vehicleType = fav.vehicleType
  form.value.vehiclePower = fav.vehiclePower ?? 5
  form.value.isElectric = fav.isElectric
  form.value.roundTrip = fav.roundTrip
  departureCoords.value = null
  arrivalCoords.value = null
  const km = await calculate(fav.departure, fav.arrival)
  if (km !== null) form.value.distanceKm = km
}
```

- [ ] **Step 3 : Remplacer les inputs départ/arrivée dans le template**

Localiser dans le template le bloc :
```html
<div class="grid grid-cols-2 gap-4">
  <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Départ</label><input v-model="form.departure" type="text" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" placeholder="Adresse de départ" /></div>
  <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Arrivée</label><input v-model="form.arrival" type="text" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" placeholder="Adresse d'arrivée" /></div>
</div>
```

Le remplacer par :
```html
<div class="grid grid-cols-2 gap-4">
  <div>
    <label class="block text-sm font-medium text-gray-700 mb-1.5">Départ</label>
    <AddressAutocompleteInput
      v-model="form.departure"
      placeholder="Adresse de départ"
      @select="onDepartureSelect"
      @update:modelValue="departureCoords = null"
    />
  </div>
  <div>
    <label class="block text-sm font-medium text-gray-700 mb-1.5">Arrivée</label>
    <AddressAutocompleteInput
      v-model="form.arrival"
      placeholder="Adresse d'arrivée"
      @select="onArrivalSelect"
      @update:modelValue="arrivalCoords = null"
    />
  </div>
</div>
```

- [ ] **Step 4 : Vérifier le typecheck**

```bash
cd frontend && npm run typecheck
```

Expected : aucune erreur de type.

- [ ] **Step 5 : Commit**

```bash
rtk git add frontend/src/components/expense/ExpenseModal.vue && rtk git commit -m "feat(distance): autocomplete adresse + auto-calc dans ExpenseModal"
```

---

## Task 8: Frontend – PersonModal autocomplete sur les trajets favoris

**Files:**
- Modify: `frontend/src/components/person/PersonModal.vue`

Les deux formulaires de route (ajout + édition) ont chacun des inputs départ/arrivée à remplacer.

- [ ] **Step 1 : Ajouter l'import dans `<script setup>`**

Dans `PersonModal.vue`, ajouter en haut des imports :

```ts
import AddressAutocompleteInput from '@/components/ui/AddressAutocompleteInput.vue'
```

- [ ] **Step 2 : Remplacer les inputs départ/arrivée du formulaire d'ajout**

Localiser dans le template (formulaire d'ajout `v-if="showAddRoute"`) :
```html
<div class="grid grid-cols-2 gap-2">
  <input v-model="newRoute.departure" type="text" class="rounded-lg border-gray-300 shadow-sm text-sm" placeholder="Départ" />
  <input v-model="newRoute.arrival" type="text" class="rounded-lg border-gray-300 shadow-sm text-sm" placeholder="Arrivée" />
</div>
```

Remplacer par :
```html
<div class="grid grid-cols-2 gap-2">
  <AddressAutocompleteInput v-model="newRoute.departure" placeholder="Départ" />
  <AddressAutocompleteInput v-model="newRoute.arrival" placeholder="Arrivée" />
</div>
```

- [ ] **Step 3 : Remplacer les inputs départ/arrivée du formulaire d'édition**

Localiser dans le template (formulaire d'édition `v-if="editingRouteId === route.id"`) :
```html
<div class="grid grid-cols-2 gap-2">
  <input v-model="editForm.departure" type="text" class="rounded-lg border-gray-300 shadow-sm text-sm" placeholder="Départ" />
  <input v-model="editForm.arrival" type="text" class="rounded-lg border-gray-300 shadow-sm text-sm" placeholder="Arrivée" />
</div>
```

Remplacer par :
```html
<div class="grid grid-cols-2 gap-2">
  <AddressAutocompleteInput v-model="editForm.departure" placeholder="Départ" />
  <AddressAutocompleteInput v-model="editForm.arrival" placeholder="Arrivée" />
</div>
```

- [ ] **Step 4 : Vérifier le typecheck**

```bash
cd frontend && npm run typecheck
```

Expected : aucune erreur de type.

- [ ] **Step 5 : Lancer la suite de tests complète**

```bash
make test
```

Expected : tous les tests passent (y compris les 3 nouveaux GetDistanceQueryHandlerTest).

- [ ] **Step 6 : Commit final**

```bash
rtk git add frontend/src/components/person/PersonModal.vue && rtk git commit -m "feat(distance): autocomplete adresse dans PersonModal (trajets favoris)"
```