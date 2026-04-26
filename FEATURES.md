# Roadmap des fonctionnalités

Liste des idées de fonctionnalités à implémenter, classées par catégorie.

---

## Export & Déclaration

- [x] **Export PDF formaté** — Générer un document prêt à envoyer aux impôts avec totaux par catégorie, barème appliqué et récapitulatif par personne.
- [x] **Export CSV** — Télécharger un fichier CSV (compatible Excel) avec le récapitulatif et le détail de chaque trajet.
- [x] **Aide au remplissage Cerfa** — Panel dans la vue Récapitulatif indiquant la case 1AK (déclarant 1) ou 1BK (déclarant 2) à renseigner dans la déclaration 2042.

---

## Intelligence & Calculs

- [ ] **Seuil de rentabilité frais réels vs forfait 10 %** — Calculer automatiquement si les frais réels dépassent l'abattement forfaitaire et afficher le gain fiscal réel.
- [ ] **Barème kilométrique multi-années** — Gérer les barèmes 2022, 2023, 2024, 2025 pour permettre des déclarations d'années antérieures.
- [ ] **Simulateur "et si"** — Permettre de simuler le gain avec d'autres paramètres (ex : changement de puissance fiscale, plus de jours de télétravail).

---

## UX & Saisie

- [x] **Calcul de distance automatique** — Saisir une adresse de départ et d'arrivée, l'app calcule la distance via une API de géocodage (ex : OpenRouteService).
- [x] **Trajets récurrents (favoris)** — Définir un trajet "Domicile → Bureau" et le réutiliser en un clic sans ressaisir les informations.
- [x] **Import CSV relevé bancaire** — Détecter automatiquement les péages et repas professionnels depuis un export bancaire.

---

## Fonctionnalités différenciantes

- [ ] **Calcul du vrai salaire net** — Afficher le salaire net effectif en intégrant l'économie d'impôt réalisée grâce aux frais réels.
- [ ] **Mode multi-employeur** — Gérer des frais pour deux employeurs distincts (ex : salarié + auto-entrepreneur).
- [ ] **Foyer fiscal partagé** — Permettre à deux déclarants d'un même foyer de partager un compte et de consolider leurs frais.
- [ ] **Rappels mensuels par email** — Envoyer une notification en fin de mois pour ne pas oublier de saisir les trajets.

---

## SaaS & Monétisation

- [ ] **Plan gratuit limité** — Restreindre le plan gratuit (ex : 50 trajets/an) et débloquer le reste sur plan premium via `SubscriptionMiddleware`.
- [ ] **Période d'essai avec relance** — Email automatique à J-3 avant expiration de la période d'essai.
- [ ] **Intégration Stripe** — Gestion des abonnements via webhooks Stripe pour activation / suspension automatique.