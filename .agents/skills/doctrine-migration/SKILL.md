---
name: doctrine-migration
description: Faire évoluer les entités, mappings, repositories et migrations Doctrine de fraisZen sans casser les données ni les frontières DDD. Utiliser ce skill pour toute modification de schéma, STI, relation, index, contrainte ou migration MySQL.
---

# Doctrine Migration

Utilise ce skill pour les changements de modèle persistant. Respecte les frontières DDD/CQRS de `AGENTS.md` et vérifie d’abord l’état Git et l’historique de la migration concernée.

## Décider entre modifier et créer une migration

- Une migration qui n’a jamais été déployée, partagée ni fusionnée peut être consolidée avec les changements de la même fonctionnalité.
- Une migration déjà fusionnée, déployée ou potentiellement exécutée doit rester immuable : crée une nouvelle migration corrective.
- En cas de doute sur son exécution, considère-la comme déployée.

Ne déduis pas la situation uniquement depuis son nom ou sa date. Vérifie l’historique Git, la branche de base et le contexte fourni par l’utilisateur.

## Concevoir le changement

1. Identifie les données existantes et les risques de valeur `NULL`, doublon, incompatibilité de type ou perte de données.
2. Modifie le modèle Domain uniquement si le concept appartient réellement au domaine.
3. Configure le mapping Doctrine dans Infrastructure ou selon les conventions existantes du contexte.
4. Pour une hiérarchie STI, vérifie le discriminateur, les colonnes partagées et le comportement de toutes les sous-classes.
5. Ajoute les index et contraintes qui soutiennent une règle ou une requête réelle.

## Écriture de la migration

- Génère un diff avec la commande du projet, puis relis intégralement le SQL produit.
- Retire les changements parasites provenant d’un schéma local divergent.
- Rends les opérations `up()` et `down()` cohérentes lorsque le retour arrière est raisonnablement possible.
- Pour une colonne obligatoire sur une table peuplée, procède par étapes sûres : ajout nullable ou valeur temporaire, migration des données, puis contrainte finale.
- Ne place pas de logique métier durable dans une migration ; limite le traitement à la transformation nécessaire des données existantes.

## Repositories et transaction

- Implémente les interfaces métier dans `Infrastructure/Persistence`.
- Évite d’exposer QueryBuilder, EntityManager ou entités Doctrine hors de l’adaptateur.
- Persiste toutes les modifications d’un cas d’usage avant un seul `flush()` final, sauf contrainte documentée.

## Validation

Contrôle au minimum :

- le diff SQL de la migration ;
- l’application sur une base à jour ;
- le statut des migrations après application ;
- les mappings Doctrine ;
- les tests des repositories ou handlers concernés ;
- `rtk make phpstan` et `rtk make psalm` pour les changements PHP associés.

Dans le compte rendu, précise si la migration a été créée ou consolidée, pourquoi ce choix est sûr et quels impacts elle a sur les données existantes.
