---
name: api-developer
description: Concevoir, modifier ou corriger une API Symfony de fraisZen en respectant son architecture DDD/CQRS, ses conventions HTTP, sa sécurité multi-utilisateur et ses tests. Utiliser ce skill pour tout travail sur un endpoint, un contrôleur HTTP, un contrat JSON ou un cas d’usage exposé par l’API.
---

# API Developer

Utilise ce skill pour les changements backend exposés par HTTP. Les règles de `AGENTS.md` restent obligatoires et priment sur ce workflow.

## Avant de modifier le code

1. Identifie le bounded context concerné et recherche un endpoint ou un cas d’usage similaire.
2. Définis le contrat avant l’implémentation : méthode, route, paramètres, corps, réponse et statuts d’erreur.
3. Détermine si l’opération est une écriture ou une lecture :
   - écriture → commande via `command.bus` ;
   - lecture → requête via `query.bus`.
4. Vérifie l’identité et la propriété des ressources. Toute ressource liée à une personne ou un utilisateur doit être protégée contre les accès croisés.

## Répartition des responsabilités

Le contrôleur peut uniquement :

- lire et normaliser les données HTTP avec les API Symfony adaptées ;
- récupérer l’utilisateur authentifié et contrôler l’autorisation d’entrée ;
- construire puis dispatcher une commande ou une requête ;
- convertir le résultat en réponse HTTP.

Place les règles métier et l’orchestration dans le handler Application. Place les invariants dans le Domain. Expose les accès aux données et services externes par des interfaces Domain ou Application, puis implémente-les dans Infrastructure.

Avant de terminer, relis chaque contrôleur modifié et retire tout appel direct à Doctrine, mutation d’entité, calcul métier, pagination, construction complexe de vue, accès fichier ou traitement externe.

## Contrat HTTP

- Conserve les conventions des routes voisines avant d’introduire un nouveau format.
- Valide explicitement les champs obligatoires, formats, valeurs d’enum et identifiants.
- Retourne des statuts cohérents : `200` pour une lecture ou mise à jour avec contenu, `201` pour une création, `204` sans contenu, `400` pour une entrée invalide, `403` pour une ressource interdite, `404` pour une ressource absente et `409` pour un conflit métier.
- Ne révèle pas l’existence d’une ressource appartenant à un autre utilisateur si les conventions de sécurité du contexte imposent un `404`.
- Garde les modèles de réponse stables et typés côté frontend dans `frontend/src/types/index.ts` lorsque le contrat est consommé par Vue.

## Persistance et transactions

- Le repository Domain exprime le besoin métier, pas une API Doctrine générique.
- Regroupe les écritures d’un cas d’usage dans une seule unité transactionnelle.
- Persiste les entités nécessaires puis effectue un seul `flush()` à la fin du handler, sauf contrainte technique démontrée.
- Modifie une migration existante uniquement lorsqu’elle n’a jamais été déployée ni fusionnée. Sinon, crée une nouvelle migration.

## Tests et validation

Ajoute en priorité des tests unitaires au niveau du handler ou du service métier. Teste séparément un adaptateur Infrastructure lorsque son mapping, sa requête ou son I/O contient un comportement significatif.

Couvre au minimum :

- le cas nominal ;
- les données obligatoires ou invalides ;
- la ressource absente ;
- l’accès par un autre utilisateur ;
- les invariants et conflits métier pertinents.

Exécute les contrôles adaptés avec `rtk make …`. Pour un changement backend complet, lance au minimum le test ciblé, `rtk make lint`, `rtk make phpstan` et `rtk make psalm`. Si le contrat TypeScript change, lance aussi `rtk make typecheck`.

## Compte rendu

Explique le contrat final, la répartition Controller/Application/Domain/Infrastructure, les contrôles de propriété ajoutés et les tests réellement exécutés. Signale toute migration ou rupture de contrat.
