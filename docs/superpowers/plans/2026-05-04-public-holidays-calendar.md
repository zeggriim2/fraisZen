# Public Holidays Calendar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Afficher dans le calendrier un fond coloré différent sur les jours fériés français, récupérés automatiquement depuis l'API publique data.gouv.fr.

**Architecture:** Un `PublicHolidayService` Symfony appelle `https://calendrier.api.gouv.fr/jours-feries/metropole/{year}.json` via `HttpClientInterface` et met le résultat en cache filesystem 24h. Un contrôleur expose `GET /api/public-holidays/{year}` pour tous les utilisateurs authentifiés. Le `CalendarView.vue` consomme cet endpoint et marque les cellules concernées.

**Tech Stack:** `symfony/http-client`, `Symfony\Contracts\Cache\CacheInterface`, Pest (PHP tests), Vue 3 Composition API, Axios.

---

### Task 1 : Installer symfony/http-client

**Files:**
- Modify: `composer.json` / `composer.lock` (via make)

- [ ] **Step 1 : Installer le package**

```bash
make composer c='require symfony/http-client'
```

Expected output : `symfony/http-client` ajouté dans `composer.json`, autoloader régénéré.

- [ ] **Step 2 : Vérifier la disponibilité dans le container**

```bash
docker compose exec php php -r "echo class_exists('Symfony\Component\HttpClient\HttpClient') ? 'OK' : 'KO';"
```

Expected : `OK`

- [ ] **Step 3 : Commit**

```bash
rtk git add composer.json composer.lock
rtk git commit -m "chore(deps): install symfony/http-client"
```

---

### Task 2 : PublicHolidayService — récupération + cache

**Files:**
- Create: `src/SharedKernel/Infrastructure/PublicHoliday/PublicHolidayService.php`
- Create: `tests/Unit/SharedKernel/Infrastructure/PublicHoliday/PublicHolidayServiceTest.php`

- [ ] **Step 1 : Écrire le test qui échoue**

Créer `tests/Unit/SharedKernel/Infrastructure/PublicHoliday/PublicHolidayServiceTest.php` :

```php
<?php

declare(strict_types=1);

use App\SharedKernel\Infrastructure\PublicHoliday\PublicHolidayService;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

it('retourne les jours fériés depuis lAPI datagouv', function () {
    $payload = json_encode([
        '2025-01-01' => '1er janvier',
        '2025-05-01' => 'Fête du Travail',
    ]);

    $httpClient = new MockHttpClient([
        new MockResponse($payload, ['http_code' => 200]),
    ]);

    $cache = new class implements CacheInterface {
        public function get(string $key, callable $callback, float $beta = null, array &$metadata = null): mixed
        {
            $item = new class implements ItemInterface {
                public function getKey(): string { return ''; }
                public function get(): mixed { return null; }
                public function isHit(): bool { return false; }
                public function set(mixed $value): static { return $this; }
                public function expiresAt(?\DateTimeInterface $expiration): static { return $this; }
                public function expiresAfter(int|\DateInterval|null $time): static { return $this; }
                public function tag(string|iterable $tags): static { return $this; }
                public function getMetadata(): array { return []; }
            };
            return $callback($item);
        }
        public function delete(string $key): bool { return true; }
    };

    $service = new PublicHolidayService($httpClient, $cache);
    $result = $service->forYear(2025);

    expect($result)->toBe([
        '2025-01-01' => '1er janvier',
        '2025-05-01' => 'Fête du Travail',
    ]);
});

it('retourne un tableau vide si lAPI est indisponible', function () {
    $httpClient = new MockHttpClient([
        new MockResponse('', ['http_code' => 503]),
    ]);

    $cache = new class implements CacheInterface {
        public function get(string $key, callable $callback, float $beta = null, array &$metadata = null): mixed
        {
            $item = new class implements ItemInterface {
                public function getKey(): string { return ''; }
                public function get(): mixed { return null; }
                public function isHit(): bool { return false; }
                public function set(mixed $value): static { return $this; }
                public function expiresAt(?\DateTimeInterface $expiration): static { return $this; }
                public function expiresAfter(int|\DateInterval|null $time): static { return $this; }
                public function tag(string|iterable $tags): static { return $this; }
                public function getMetadata(): array { return []; }
            };
            return $callback($item);
        }
        public function delete(string $key): bool { return true; }
    };

    $service = new PublicHolidayService($httpClient, $cache);
    $result = $service->forYear(2025);

    expect($result)->toBe([]);
});
```

- [ ] **Step 2 : Lancer le test — vérifier qu'il échoue**

```bash
make test c="tests/Unit/SharedKernel/Infrastructure/PublicHoliday/PublicHolidayServiceTest.php"
```

Expected : FAIL — `PublicHolidayService not found`

- [ ] **Step 3 : Implémenter le service**

Créer `src/SharedKernel/Infrastructure/PublicHoliday/PublicHolidayService.php` :

```php
<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\PublicHoliday;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PublicHolidayService
{
    private const API_URL = 'https://calendrier.api.gouv.fr/jours-feries/metropole/%d.json';
    private const TTL = 86400; // 24h

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
    ) {}

    /** @return array<string, string> */
    public function forYear(int $year): array
    {
        return $this->cache->get("public_holidays_{$year}", function (ItemInterface $item) use ($year): array {
            $item->expiresAfter(self::TTL);
            try {
                $response = $this->httpClient->request('GET', sprintf(self::API_URL, $year));
                if (200 !== $response->getStatusCode()) {
                    return [];
                }
                return $response->toArray();
            } catch (\Throwable) {
                return [];
            }
        });
    }
}
```

- [ ] **Step 4 : Lancer le test — vérifier qu'il passe**

```bash
make test c="tests/Unit/SharedKernel/Infrastructure/PublicHoliday/PublicHolidayServiceTest.php"
```

Expected : PASS — 2 tests

- [ ] **Step 5 : Commit**

```bash
rtk git add src/SharedKernel/Infrastructure/PublicHoliday/PublicHolidayService.php \
           tests/Unit/SharedKernel/Infrastructure/PublicHoliday/PublicHolidayServiceTest.php
rtk git commit -m "feat(public-holidays): PublicHolidayService avec cache datagouv"
```

---

### Task 3 : Contrôleur GET /api/public-holidays/{year}

**Files:**
- Create: `src/SharedKernel/Infrastructure/Http/PublicHolidayController.php`

- [ ] **Step 1 : Créer le contrôleur**

Créer `src/SharedKernel/Infrastructure/Http/PublicHolidayController.php` :

```php
<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Http;

use App\SharedKernel\Infrastructure\PublicHoliday\PublicHolidayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/public-holidays/{year}', name: 'public_holidays', requirements: ['year' => '\d{4}'], methods: ['GET'])]
final class PublicHolidayController extends AbstractController
{
    public function __construct(private readonly PublicHolidayService $publicHolidayService)
    {
    }

    public function __invoke(int $year): JsonResponse
    {
        return $this->json($this->publicHolidayService->forYear($year));
    }
}
```

- [ ] **Step 2 : Vérifier que la route est bien enregistrée**

```bash
make sf c='debug:router public_holidays'
```

Expected : ligne avec `GET /api/public-holidays/{year}`

- [ ] **Step 3 : Tester manuellement l'endpoint**

S'assurer que les containers tournent (`make up`), puis :

```bash
TOKEN=$(curl -s -X POST https://localhost/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"votre@email.com","password":"votre_mdp"}' \
  --insecure | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")

curl -s "https://localhost/api/public-holidays/2025" \
  -H "Authorization: Bearer $TOKEN" --insecure | python3 -m json.tool | head -20
```

Expected : JSON `{"2025-01-01":"1er janvier", ...}`

- [ ] **Step 4 : Commit**

```bash
rtk git add src/SharedKernel/Infrastructure/Http/PublicHolidayController.php
rtk git commit -m "feat(public-holidays): endpoint GET /api/public-holidays/{year}"
```

---

### Task 4 : Frontend — appel API

**Files:**
- Modify: `frontend/src/api/expenseApi.ts`

- [ ] **Step 1 : Ajouter la fonction dans expenseApi.ts**

Dans `frontend/src/api/expenseApi.ts`, ajouter après les imports existants et avant la première export :

```typescript
export function getPublicHolidays(year: number): Promise<Record<string, string>> {
  return http.get<Record<string, string>>(`/public-holidays/${year}`).then(r => r.data)
}
```

- [ ] **Step 2 : Vérifier le typage**

```bash
docker compose exec node sh -c "npx vue-tsc --noEmit"
```

Expected : aucune erreur

- [ ] **Step 3 : Commit**

```bash
rtk git add frontend/src/api/expenseApi.ts
rtk git commit -m "feat(public-holidays): ajout getPublicHolidays dans expenseApi"
```

---

### Task 5 : CalendarView — affichage des jours fériés

**Files:**
- Modify: `frontend/src/views/CalendarView.vue`

- [ ] **Step 1 : Ajouter le state et le chargement des jours fériés**

Dans le `<script setup>`, ajouter l'import et le state après la ligne `import { usePersonStore }...` :

```typescript
import { getPublicHolidays } from '@/api/expenseApi'

const publicHolidays = ref<Record<string, string>>({})

async function loadHolidays() {
  try {
    publicHolidays.value = await getPublicHolidays(year.value)
  } catch {
    publicHolidays.value = {}
  }
}
```

- [ ] **Step 2 : Déclencher le chargement au montage et au changement d'année**

Remplacer :

```typescript
watch([month, year, () => personStore.activePerson], load)
onMounted(load)
```

Par :

```typescript
watch(year, loadHolidays, { immediate: true })
watch([month, year, () => personStore.activePerson], load)
onMounted(load)
```

- [ ] **Step 3 : Ajouter isHoliday dans les cellules**

Dans `computed cells`, remplacer la ligne qui construit chaque cellule du mois courant :

```typescript
result.push({ date: dateStr, day: d, inMonth: true, isToday: dateStr === todayStr, expenses: expenseStore.expenses.filter(e => e.date === dateStr) })
```

Par :

```typescript
result.push({ date: dateStr, day: d, inMonth: true, isToday: dateStr === todayStr, isHoliday: !!publicHolidays.value[dateStr], holidayName: publicHolidays.value[dateStr] ?? null, expenses: expenseStore.expenses.filter(e => e.date === dateStr) })
```

Et pour les cellules hors mois (avant et après), ajouter `isHoliday: false, holidayName: null` :

```typescript
// Cellules avant le 1er
result.push({ date: dateStr, day: d.getDate(), inMonth: false, isToday: false, isHoliday: false, holidayName: null, expenses: [] as Expense[] })

// Cellules après le dernier jour
result.push({ date: toDateStr(d), day: d.getDate(), inMonth: false, isToday: false, isHoliday: false, holidayName: null, expenses: [] as Expense[] })
```

- [ ] **Step 4 : Appliquer la couleur de fond et le label dans le template**

Dans le template, remplacer le bloc de la cellule :

```html
<div
  v-for="(cell, i) in cells" :key="i"
  @click="cell.date && openModal(cell.date)"
  :class="['min-h-28 p-2 border-b border-r border-gray-100 transition-colors cursor-pointer',
    !cell.inMonth && 'bg-gray-50/50 opacity-40',
    cell.isToday && 'bg-indigo-50/40',
    cell.inMonth && 'hover:bg-gray-50']"
>
  <span :class="['inline-flex items-center justify-center w-7 h-7 rounded-full text-sm font-medium mb-1',
    cell.isToday ? 'bg-indigo-600 text-white' : 'text-gray-700']">
    {{ cell.day }}
  </span>
```

Par :

```html
<div
  v-for="(cell, i) in cells" :key="i"
  @click="cell.date && openModal(cell.date)"
  :class="['min-h-28 p-2 border-b border-r border-gray-100 transition-colors cursor-pointer',
    !cell.inMonth && 'bg-gray-50/50 opacity-40',
    cell.isHoliday && cell.inMonth && 'bg-amber-50',
    cell.isToday && 'bg-indigo-50/40',
    cell.inMonth && !cell.isHoliday && 'hover:bg-gray-50',
    cell.inMonth && cell.isHoliday && 'hover:bg-amber-100']"
>
  <div class="flex items-center justify-between mb-1">
    <span :class="['inline-flex items-center justify-center w-7 h-7 rounded-full text-sm font-medium',
      cell.isToday ? 'bg-indigo-600 text-white' : 'text-gray-700']">
      {{ cell.day }}
    </span>
    <span v-if="cell.isHoliday && cell.inMonth" class="text-xs text-amber-600 font-medium truncate ml-1 max-w-[70%]" :title="cell.holidayName ?? ''">
      {{ cell.holidayName }}
    </span>
  </div>
```

- [ ] **Step 5 : Vérifier le typage**

```bash
docker compose exec node sh -c "npx vue-tsc --noEmit"
```

Expected : aucune erreur

- [ ] **Step 6 : Vérifier visuellement**

Démarrer le frontend (`cd frontend && npm run dev` ou via le container node), ouvrir le calendrier sur une année avec des jours fériés (ex: 2025). Les jours fériés doivent apparaître avec un fond amber et leur nom affiché.

- [ ] **Step 7 : Commit**

```bash
rtk git add frontend/src/views/CalendarView.vue
rtk git commit -m "feat(public-holidays): affichage jours fériés en fond amber dans le calendrier"
```

---

### Task 6 : Tests complets

**Files:**
- Verify: `tests/Unit/SharedKernel/Infrastructure/PublicHoliday/PublicHolidayServiceTest.php`

- [ ] **Step 1 : Lancer toute la suite de tests**

```bash
make test
```

Expected : tous les tests passent (les 2 nouveaux + les existants)

- [ ] **Step 2 : Commit final si nécessaire**

Si des ajustements ont été faits :

```bash
rtk git add -p
rtk git commit -m "fix(public-holidays): corrections suite aux tests"
```