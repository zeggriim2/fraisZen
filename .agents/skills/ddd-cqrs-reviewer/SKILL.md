---
name: ddd-cqrs-reviewer
description: Auditer un changement backend de fraisZen pour vérifier les frontières DDD/CQRS, la responsabilité des couches et la testabilité des cas d’usage. Utiliser ce skill lors d’une revue d’architecture, avant un commit backend important ou après l’ajout de contrôleurs, handlers, repositories ou services externes.
---

# DDD CQRS Reviewer

Ce skill effectue une revue ciblée. Il ne réécrit pas automatiquement l’architecture si l’utilisateur demande uniquement un diagnostic.

## Périmètre de la revue

Commence par identifier les fichiers backend modifiés et leur bounded context. Compare-les aux règles obligatoires de `AGENTS.md`, puis contrôle les points suivants.

### HTTP

- Le contrôleur se limite au mapping HTTP, à l’authentification ou l’autorisation, au dispatch et à la réponse.
- Il ne contient aucun accès Doctrine, mutation d’entité, calcul métier, filtrage, pagination, OCR, accès fichier ou assemblage complexe de modèle de lecture.
- Une écriture passe par `command.bus` et une lecture par `query.bus`.

### Application

- Le handler orchestre un seul cas d’usage clairement nommé.
- Il dépend d’interfaces Domain ou Application et pas de classes HTTP Symfony.
- Les transactions et effets externes ont une frontière explicite.
- Les erreurs métier sont représentées de manière stable et traduites en HTTP uniquement dans Infrastructure.

### Domain

- Les invariants résident dans les entités, value objects, enums ou services de domaine appropriés.
- Le Domain n’importe aucune classe Symfony, Doctrine concrète, requête HTTP, fichier uploadé ou adaptateur externe.
- Les interfaces de repository expriment des besoins métier et ne copient pas l’API de l’ORM.

### Infrastructure

- Doctrine, filesystem, OCR, email, paiement et API tierces restent dans des adaptateurs.
- Le mapping entre persistance et domaine respecte les invariants et les types.
- Les implémentations ne font pas remonter de détails techniques inutiles jusqu’au contrôleur.

### Sécurité et tests

- Chaque ressource est vérifiée par rapport à l’utilisateur ou à la personne propriétaire.
- Les handlers importants couvrent le cas nominal, les invariants, l’absence et l’accès interdit.
- Les adaptateurs ayant un mapping ou un I/O significatif possèdent des tests dédiés.

## Restitution

Classe les constats par sévérité et référence les fichiers et lignes concernés. Explique le risque concret, puis propose le déplacement ou l’abstraction minimale. Si aucune violation n’est trouvée, indique les couches contrôlées et les limites de la revue.
