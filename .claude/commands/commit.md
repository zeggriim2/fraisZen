Prépare et crée un commit en suivant le format Karma (Conventional Commits), avec création optionnelle de branche.

## Étape 1 — Branche

Commence par exécuter `rtk git status` et `rtk git branch` pour connaître la branche courante.

Ensuite, demande à l'utilisateur via `AskUserQuestion` :

**Question 1** — Veux-tu créer une nouvelle branche à partir de `develop` avant de commiter ?
- Oui — choisir le type
- Non — commiter sur la branche courante

Si "Oui", demande le type de branche :
- `feat` — nouvelle fonctionnalité
- `fix` — correction de bug
- `chore` — tâche technique / maintenance
- `refactor` — refactoring sans changement de comportement
- `docs` — documentation
- `test` — ajout ou modification de tests
- `perf` — amélioration de performance
- `ci` — intégration continue / pipeline

Puis demande un **nom court** pour la branche (ex: `add-export-csv`, `fix-psalm-errors`).

Construis le nom de branche : `<type>/<nom-court>` (kebab-case, minuscules).

Exécute alors :
```bash
rtk git checkout develop
rtk git checkout -b <type>/<nom-court>
```

## Étape 2 — Analyse des changements

Exécute en parallèle :
- `rtk git diff --staged`
- `rtk git diff`
- `rtk git status`

Si aucun fichier n'est stagé, exécute `rtk git add -p` — non, à la place affiche la liste des fichiers modifiés/non stagés et demande à l'utilisateur lesquels ajouter. Propose de stager tous les fichiers avec `git add` sur les fichiers listés.

Si des fichiers sont déjà stagés, utilise-les tels quels.

## Étape 3 — Message de commit

Analyse les changements stagés et propose un message de commit au format **Karma** :

```
<type>(<scope>): <description courte en minuscules, impératif>

[corps optionnel : explication du "pourquoi" si non évident]

[footer optionnel : BREAKING CHANGE, Closes #xxx]
```

**Types Karma autorisés :** `feat`, `fix`, `refactor`, `perf`, `test`, `docs`, `chore`, `ci`, `style`, `revert`

**Règles du message :**
- Ligne de titre ≤ 72 caractères
- Description en minuscules, à l'impératif (ex: `add pdf export` pas `added` ni `adds`)
- Scope = module/contexte concerné (ex: `expense`, `auth`, `person`, `billing`)
- Corps uniquement si le "pourquoi" n'est pas évident depuis le titre
- Pas de point final sur la ligne de titre
- Je ne veux pas que tu mets '
  Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>' dans le message de commit

Affiche le message proposé à l'utilisateur et demande confirmation ou modification.

## Étape 4 — Commit

Une fois validé, exécute le commit avec HEREDOC :

```bash
rtk git commit -m "$(cat <<'EOF'
<message validé>

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

Affiche le résultat de `rtk git status` après le commit pour confirmer le succès.

Termine par un récapitulatif : branche, fichiers commités, message utilisé.
