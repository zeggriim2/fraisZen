---
name: frontend-developer
description: Concevoir, modifier ou corriger une interface Vue de fraisZen en respectant son design system, ses contrats TypeScript, les modes clair et sombre, le responsive et l’accessibilité. Utiliser ce skill pour les vues, composants, stores, routes et intégrations API du frontend.
---

# Frontend Developer

Utilise ce skill pour les changements dans `frontend/`. Les conventions et commandes de `AGENTS.md` restent obligatoires.

## Avant de modifier l’interface

1. Identifie le parcours utilisateur, l’action principale et les états nécessaires : chargement, vide, erreur, succès et désactivation.
2. Recherche les composants, styles, types et appels API existants avant d’en créer de nouveaux.
3. Préserve le langage visuel existant : variables CSS, espacements, rayons, typographie, icônes et comportements interactifs.
4. Vérifie le contrat backend réel. Ne contourne pas un type incorrect avec `any` ou une assertion non justifiée.

## Implémentation

- Utilise Vue 3 avec `<script setup>` et la Composition API.
- Place les appels HTTP dans `frontend/src/api/`, les types partagés dans `frontend/src/types/index.ts` et l’état transversal dans Pinia lorsque plusieurs vues le consomment.
- Extrais un composant lorsqu’un comportement est réutilisé, complexe, testable isolément ou alourdit fortement une vue. Ne crée pas de composant pour un simple fragment propre à une seule page.
- Garde une action principale identifiable par écran ou fenêtre modale.
- Donne aux contrôles interactifs un libellé explicite, un état de focus visible et une zone cliquable suffisante.
- Utilise des transitions ciblées. N’utilise ni `transition: all` ni des animations indispensables à la compréhension.
- Ne masque pas une fonctionnalité essentielle derrière le seul survol.

## Compatibilité visuelle

Vérifie chaque interface modifiée :

- en mode clair et sombre ;
- sur desktop et mobile ;
- avec des libellés longs et des listes vides ;
- pendant le chargement et après une erreur API ;
- au clavier pour les actions principales et les modales.

Utilise les variables du design system plutôt que des couleurs fixes, sauf pour les états sémantiques qui restent lisibles dans les deux thèmes.

## Validation

Exécute au minimum :

```bash
rtk make typecheck
```

Pour une modification visuelle significative, exécute aussi le build frontend et contrôle le rendu dans le navigateur sur les tailles pertinentes. Lance Prettier avec la version attendue par la CI lorsque des fichiers CSS, Vue ou HTML sont modifiés.

Dans le compte rendu, précise les parcours et états vérifiés ainsi que les éventuelles limites responsive ou d’accessibilité.
