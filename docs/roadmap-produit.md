# Feuille de route produit FraisZen

## Objectif

Faire évoluer FraisZen d'un outil de saisie et de calcul vers un assistant fiscal complet. L'application doit aider l'utilisateur à enregistrer ses frais, contrôler la qualité de son dossier et préparer les éléments nécessaires à sa déclaration.

## Fonctionnalités recommandées

| Priorité | Fonctionnalité | Valeur apportée |
| --- | --- | --- |
| 1 | Assistant de déclaration fiscale | Générer, pour chaque personne, le montant à déclarer, la case correspondante, le détail des frais et un dossier exportable. |
| 2 | Coffre-fort de justificatifs avec OCR | Photographier ou importer un justificatif, en extraire les informations et le rattacher automatiquement à une dépense. |
| 3 | Contrôle de cohérence fiscale | Repérer les justificatifs absents, doublons, trajets inhabituels, distances élevées et dépenses incohérentes. |
| 4 | Comparateur frais réels / abattement de 10 % | Recommander l'option la plus avantageuse pour chaque personne en intégrant salaires, remboursements et allocations employeur. |
| 5 | Gestion complète des véhicules | Suivre séparément chaque véhicule, sa puissance fiscale, sa motorisation, son kilométrage et les trajets associés. |
| 6 | Automatisation du calendrier | Générer les trajets selon les jours travaillés, le télétravail, les congés, les jours fériés et les semaines types. |
| 7 | Nouvelles catégories de frais réels | Gérer le matériel, la documentation, la formation, la double résidence, les vêtements professionnels et les frais de bureau. |
| 8 | Clôture annuelle guidée | Vérifier les données, signaler les éléments manquants, verrouiller l'exercice et ouvrir l'année suivante. |
| 9 | Application mobile progressive | Permettre l'installation sur mobile, la saisie hors connexion et la photographie immédiate des reçus. |
| 10 | Espace foyer partagé | Inviter un conjoint, attribuer des droits et conserver une déclaration séparée par personne. |
| 11 | Rapprochement bancaire intelligent | Reconnaître les péages, parkings et repas dans un relevé puis proposer les dépenses correspondantes. |
| 12 | Rappels personnalisés | Prévenir l'utilisateur des jours non renseignés, justificatifs manquants et dépenses restant à vérifier. |

## Détail des fonctionnalités prioritaires

### 1. Assistant de déclaration fiscale

L'assistant constitue la finalité principale du parcours. Il pourrait fournir :

- le montant calculé pour chaque déclarant ;
- la case de déclaration correspondante, notamment 1AK à 1DK ;
- une ventilation par catégorie et par personne ;
- le détail des calculs et du barème appliqué ;
- un texte explicatif à joindre à la déclaration ;
- un export PDF et CSV du dossier annuel.

### 2. Coffre-fort de justificatifs avec OCR

Chaque dépense pourrait recevoir une photo ou un document PDF. L'OCR proposerait automatiquement la date, le montant, le fournisseur et la catégorie. Le coffre-fort devrait également détecter les doublons, afficher les dépenses sans preuve et garantir la consultation des pièces archivées par année.

### 3. Contrôle de cohérence fiscale

Un score de complétude annuel permettrait de savoir immédiatement si le dossier est prêt. Les contrôles pourraient signaler :

- une dépense sans justificatif lorsqu'il est nécessaire ;
- plusieurs dépenses identiques ;
- plus d'un aller-retour domicile-travail par jour ;
- une distance domicile-travail supérieure à 40 km sans justification ;
- un trajet enregistré pendant un congé ou un jour de télétravail ;
- une dépense inhabituelle un week-end ou un jour férié ;
- une incohérence entre véhicule, puissance fiscale et kilométrage ;
- un péage ou un parking déjà remboursé par l'employeur.

### 4. Comparateur frais réels / abattement de 10 %

Le comparateur doit aller au-delà d'une simple comparaison entre deux montants. Il doit prendre en compte les remboursements, allocations et avantages employeur, présenter les hypothèses utilisées et expliquer l'écart obtenu pour chaque membre du foyer.

### 5. Gestion multi-véhicule

L'utilisateur pourrait créer une fiche par véhicule avec un nom, une immatriculation facultative, une puissance fiscale, une motorisation, une date d'utilisation et un relevé kilométrique. Les calculs annuels resteraient séparés pour chaque véhicule avant consolidation dans le récapitulatif de la personne.

## Feuille de route proposée

### Phase 1 — Sécuriser la déclaration

- assistant de déclaration fiscale ;
- contrôle de cohérence et score de complétude ;
- gestion multi-véhicule ;
- comparateur frais réels / abattement de 10 % enrichi.

### Phase 2 — Constituer le dossier

- coffre-fort de justificatifs et OCR ;
- nouvelles catégories de frais ;
- clôture annuelle guidée ;
- rapprochement bancaire amélioré.

### Phase 3 — Automatiser les usages

- génération intelligente du calendrier ;
- application mobile progressive et fonctionnement hors connexion ;
- espace partagé pour le foyer ;
- rappels personnalisés.

## Principes d'expérience utilisateur

- Expliquer chaque résultat et permettre de consulter le détail du calcul.
- Ne jamais créer définitivement une dépense issue d'une suggestion sans validation de l'utilisateur.
- Afficher les avertissements dans le contexte concerné, sans bloquer la saisie lorsqu'une justification reste possible.
- Séparer clairement les données et calculs de chaque personne et de chaque véhicule.
- Conserver une trace des modifications importantes effectuées après la clôture d'un exercice.
- Présenter les règles fiscales avec leur année d'application et leur source.

## Références fiscales

- [Déclaration des frais réels et justificatifs — impots.gouv.fr](https://www.impots.gouv.fr/particulier/questions/comment-declarer-mes-frais-reels-dans-ma-declaration-de-revenus)
- [Application du barème avec plusieurs véhicules — impots.gouv.fr](https://www.impots.gouv.fr/particulier/questions/jutilise-plusieurs-vehicules-comment-dois-je-appliquer-le-bareme-kilometrique)
- [Comparaison avec l'abattement forfaitaire de 10 % — impots.gouv.fr](https://www.impots.gouv.fr/particulier/questions/la-deduction-de-mes-frais-reels-est-elle-plus-favorable)
- [Fiche 2026 pour les frais engagés en 2025 — impots.gouv.fr](https://www.impots.gouv.fr/sites/default/files/formulaires/2041-alk-auto/2026/2041-alk-auto_5512.pdf)

> Les règles, seuils et barèmes devront être versionnés par année fiscale. Le contenu de FraisZen devra rester informatif et présenter clairement la source et la date de chaque règle.
