# Documentation fonctionnelle FraisZen

FraisZen est une application web destinée aux salariés français qui veulent préparer une déclaration de frais professionnels au régime des frais réels. Elle centralise les personnes du foyer fiscal, les dépenses professionnelles et les exports nécessaires pour contrôler les montants à reporter dans la déclaration de revenus.

> Cette documentation décrit ce qui est présent dans le code et les documents du projet. Les éléments uniquement prévus sont indiqués comme **roadmap**.

## Proposition de valeur

FraisZen aide à :

- saisir les frais professionnels récurrents ou ponctuels ;
- calculer automatiquement les indemnités kilométriques selon le barème kilométrique connu par l’application ;
- suivre les frais de télétravail, péages, repas et parking ;
- produire un récapitulatif annuel par personne ;
- exporter ce récapitulatif en PDF ou CSV pour faciliter la déclaration et l’archivage.

L’objectif produit est de réduire les calculs manuels, les risques d’oubli et la dispersion des justificatifs.

## Profils utilisateurs ciblés

### Utilisateur salarié

Le profil principal est un salarié qui déclare des frais professionnels réels au lieu de l’abattement forfaitaire de 10 %. Il peut suivre ses trajets domicile-travail, ses jours de télétravail et ses dépenses associées à l’activité professionnelle.

### Foyer fiscal

Un même compte peut gérer plusieurs personnes via le module `Person`. Cela permet de séparer les dépenses par membre du foyer fiscal et d’obtenir un récapitulatif annuel par personne.

### Administrateur

Une interface d’administration existe pour gérer les utilisateurs, exporter la liste des utilisateurs, suivre des statistiques et administrer des paramètres fiscaux comme les barèmes kilométriques et les valeurs de référence utilisées dans les calculs.

## Parcours utilisateur principal

1. L’utilisateur crée un compte ou se connecte.
2. Il ajoute une ou plusieurs personnes du foyer fiscal.
3. Il saisit ses dépenses au fil de l’année depuis le calendrier ou les écrans dédiés.
4. Il peut créer des trajets favoris pour réutiliser rapidement des trajets réguliers.
5. Il consulte le récapitulatif annuel par personne.
6. Il exporte les données au format PDF ou CSV.
7. Il utilise les montants calculés pour préparer sa déclaration de revenus.

## Gestion du foyer fiscal et des personnes

Le module personnes permet de créer, modifier, supprimer et lister les membres suivis dans le compte. Chaque personne contient notamment un prénom, un nom, un email optionnel, et peut être marquée comme favorite côté interface.

Les dépenses sont rattachées à une personne. Le résumé annuel, les exports PDF/CSV et les filtres de dépenses utilisent ce rattachement pour éviter de mélanger les montants entre membres du foyer.

## Types de frais gérés

### Trajets

Les trajets représentent les déplacements professionnels ou domicile-travail. Les données gérées incluent :

- date ;
- distance en kilomètres ;
- puissance fiscale éventuelle ;
- type de véhicule : voiture, moto ou cyclomoteur ;
- véhicule électrique pour les voitures ;
- départ et arrivée optionnels ;
- aller-retour ;
- description optionnelle.

L’application prend en compte la distance effective, notamment l’aller-retour quand il est renseigné.

### Télétravail

Les frais de télétravail sont comptés par jour saisi. Le récapitulatif annuel applique une indemnité journalière issue de la configuration fiscale de l’année quand elle existe, avec une valeur de repli présente dans le code.

### Péages

Les péages sont saisis avec un montant, une date, un départ/arrivée optionnels et une description optionnelle. Ils sont additionnés dans le récapitulatif annuel.

### Repas

Les repas professionnels sont saisis avec un montant de repas, une éventuelle contribution employeur et une option `sans justificatif`. Le récapitulatif additionne le montant déductible calculé par l’entité métier.

### Parking

Les frais de parking sont gérés avec un montant, une localisation optionnelle et une description. Le code prévoit aussi la gestion d’un nom de justificatif.

### Justificatifs

La gestion de justificatifs est présente pour les frais de parking : upload, téléchargement et suppression via l’API `/api/expenses/{id}/receipt`. Les fichiers sont stockés dans le répertoire de partage applicatif configuré par `APP_SHARE_DIR` et par le volume Docker `receipts_data`.

### Import CSV

L’interface contient un composant d’import CSV pour faciliter la création de dépenses à partir d’un relevé bancaire. La roadmap du projet indique l’objectif de détecter automatiquement les péages et repas depuis un export bancaire.

## Calcul des indemnités kilométriques

Le calcul kilométrique est centralisé dans `KilometricAllowanceCalculator` et `BaremeKilometriqueProvider`.

Fonctionnement métier :

- les trajets sont regroupés par type de véhicule, puissance fiscale et caractère électrique ;
- le total annuel de kilomètres est calculé par groupe ;
- le barème applique des tranches kilométriques ;
- les voitures sont bornées entre 3 et 7 CV ;
- les motos sont regroupées par puissance ;
- les cyclomoteurs utilisent un barème dédié ;
- les voitures 100 % électriques ont une majoration de 20 %.

Le fournisseur de barème contient des valeurs pour 2023, avec réutilisation pour 2024 et 2025 quand aucun barème spécifique n’est défini. Une entité d’administration permet aussi de stocker des barèmes par année.

## Comparaison frais réels vs abattement de 10 %

La comparaison automatique entre frais réels et abattement forfaitaire de 10 % est indiquée comme **roadmap** dans `FEATURES.md`. Elle n’est pas documentée ici comme fonctionnalité existante, car le code inspecté ne montre pas de calcul complet du seuil de rentabilité ni de gain fiscal final basé sur le revenu imposable.

## Exports PDF et CSV

FraisZen propose deux exports de résumé annuel :

- PDF : document formaté généré avec dompdf ;
- CSV : fichier compatible tableur avec les montants récapitulatifs et les détails utiles.

Les exports sont produits depuis le résumé annuel d’une personne pour une année donnée. Le PDF ajoute le nom de la personne quand elle est retrouvée.

## Aide à la déclaration 2042

Le `README.md` et `FEATURES.md` indiquent une aide au remplissage de la déclaration 2042, avec les cases 1AK ou 1BK selon le déclarant. Cette aide est considérée comme existante au niveau produit documenté par le dépôt. Les montants restent à vérifier par l’utilisateur avant déclaration officielle.

FraisZen n’est pas un conseil fiscal personnalisé : l’application aide à préparer et organiser les informations, mais l’utilisateur reste responsable de sa déclaration.

## Abonnement et accès

Le projet contient une intégration Stripe :

- création de session de paiement ;
- portail client ;
- webhooks Stripe ;
- statut d’abonnement sur l’utilisateur ;
- middleware de protection d’accès.

Le `SubscriptionMiddleware` bloque les routes `/api/*` quand le statut d’abonnement de l’utilisateur n’est pas `active`, à l’exception des routes d’authentification, de billing et d’administration. Le frontend redirige vers `/pricing` lorsqu’une réponse HTTP 402 est reçue.

La roadmap prévoit encore des améliorations SaaS, notamment un plan gratuit limité et une période d’essai avec relance.

## Administration

Les écrans d’administration présents côté frontend couvrent :

- tableau de bord ;
- gestion des utilisateurs ;
- détail utilisateur ;
- configuration fiscale ;
- barème kilométrique.

Côté API, les routes d’administration sont protégées par `ROLE_ADMIN`.

## Limites connues et roadmap

Fonctionnalités indiquées comme **roadmap** ou à compléter :

- comparaison automatique complète frais réels vs abattement 10 % ;
- barèmes multi-années au-delà de la structure actuelle et de l’administration existante ;
- simulateur “et si” ;
- calcul du vrai salaire net ;
- mode multi-employeur ;
- foyer fiscal partagé entre deux déclarants avec usage collaboratif ;
- rappels mensuels par email ;
- plan gratuit limité ;
- période d’essai avec relance.

## Points de vigilance utilisateur

- Les barèmes et valeurs fiscales doivent être contrôlés avant usage déclaratif réel.
- Les justificatifs doivent être conservés selon les exigences fiscales applicables.
- Les exports facilitent la préparation du dossier mais ne remplacent pas une validation fiscale ou comptable.
