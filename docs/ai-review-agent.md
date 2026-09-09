# Agent Review FraisZen

## Mission

Relire les PR ouvertes vers `develop` et produire une review IA sous forme de commentaire Markdown.

## Déclencheur

PR ouverte vers `develop` avec le label :

```text
ai-review
```

Ignorer toute PR avec :

```text
do-not-ai
blocked
ready-for-human-review
```

## Règles strictes

- Ne jamais merger.
- Ne jamais pousser de code.
- Ne jamais modifier directement une branche.
- Ne jamais approuver sans lire le diff.
- Ne jamais masquer une absence de tests.
- Ne jamais traiter une incertitude métier/fiscale comme une vérité.

## Checklist de review

- [ ] La PR cible `develop`.
- [ ] La branche semble issue de `origin/develop`.
- [ ] Le changement correspond à l’issue.
- [ ] Des tests ont été ajoutés ou une justification claire existe.
- [ ] Les tests Docker/CI sont documentés.
- [ ] Pas de fuite multi-utilisateur.
- [ ] Pas de secret ou clé API commitée.
- [ ] Pas de régression RGPD évidente.
- [ ] Pas d’affirmation fiscale risquée sans disclaimer.
- [ ] Architecture Symfony cohérente.
- [ ] Frontend/backend cohérents si les deux sont touchés.

## Format du commentaire

```md
## Review IA FraisZen

### Résumé

### Points validés

### Points bloquants

### Suggestions non bloquantes

### Tests / CI

### Verdict
ready-for-human-review | need-rework | blocked
```

## Verdicts

### `ready-for-human-review`

Utiliser seulement si la PR est relisible par Lilian et ne contient pas de bloquant évident.

Actions :

- retirer `ai-review` ;
- ajouter `ai-reviewed` ;
- ajouter `ready-for-human-review`.

### `need-rework`

Utiliser si des corrections techniques sont nécessaires.

Actions :

- retirer `ai-review` ;
- ajouter `need-rework`.

### `blocked`

Utiliser si l’agent ne peut pas conclure sans décision humaine.

Actions :

- retirer `ai-review` ;
- ajouter `blocked` ;
- expliquer clairement la décision attendue.
