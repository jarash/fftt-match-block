# Release Process - FFTT Match Block

Guide pour créer une nouvelle version du plugin.

## Étapes du processus

### 1. **Mettre à jour le changelog**

Éditer [CHANGELOG.md](CHANGELOG.md) et ajouter la nouvelle version **au début** du fichier.

Format :
```markdown
## [X.Y.Z] - YYYY-MM-DD

### Ajouté
- ...

### Modifié
- ...

### Corrigé
- ...

---

## [X.Y.Z-1] - ...
```

**Exemple :**
```markdown
## [1.2.0] - 2026-11-08

### Ajouté
- Nouvelle fonctionnalité X
- Nouvelle fonctionnalité Y

### Corrigé
- Bug avec la phase 2

---

## [1.1.1] - 2026-05-08
...
```

### 2. **Mettre à jour la version dans le plugin**

Éditer [fftt-match-block.php](fftt-match-block.php) - deux lignes à changer :

**Ligne 3** - Mettre à jour le header du plugin :
```php
/**
 * Plugin Name: FFTT Match Block
 * Description: Bloc Gutenberg pour afficher un match FFTT...
 * Version: X.Y.Z          ← À jour
 * Author: Vincent Rousseau
 * Update URI: https://github.com/jarash/fftt-match-block
 */
```

**Ligne 15** - Mettre à jour la constante :
```php
define('FFTT_MATCH_BLOCK_VERSION', 'X.Y.Z');  ← À jour
```

### 3. **Commiter les changements**

```bash
git add CHANGELOG.md fftt-match-block.php
git commit -m "chore: bump to X.Y.Z with changelog"
git push
```

### 4. **Créer et pousser le tag**

```bash
git tag -a vX.Y.Z -m "vX.Y.Z: [Description courte]"
git push origin vX.Y.Z
```

**Exemple :**
```bash
git tag -a v1.2.0 -m "v1.2.0: Mobile improvements and phase bug fixes"
git push origin v1.2.0
```

## Automatisation

✅ **GitHub Actions gère automatiquement** :
1. Extrait la section du changelog pour cette version
2. Construit le ZIP du plugin
3. Crée la release GitHub avec changelog + ZIP attaché

👉 **Vérifier la release** : https://github.com/jarash/fftt-match-block/releases

## Versionning

Suivre [Semantic Versioning](https://semver.org/) :

- **MAJOR** (X.0.0) : Breaking changes ou grandes refonte
- **MINOR** (0.X.0) : Nouvelle fonctionnalité, compatible
- **PATCH** (0.0.X) : Bug fixes seulement

## Checklist complète

- [ ] Éditer [CHANGELOG.md](CHANGELOG.md)
- [ ] Éditer [fftt-match-block.php](fftt-match-block.php) - header + constante
- [ ] Commit + push : `git commit -m "chore: bump to X.Y.Z"` + `git push`
- [ ] Créer tag : `git tag -a vX.Y.Z -m "vX.Y.Z: description"`
- [ ] Pousser tag : `git push origin vX.Y.Z`
- [ ] Vérifier la release sur GitHub dans 2-3 min

## Dépannage

### "Release non créée" ?
→ Attendre 2-3 minutes que GitHub Actions finisse son travail  
→ Aller sur https://github.com/jarash/fftt-match-block/actions

### "Le changelog n'apparaît pas" ?
→ Vérifier que le format du changelog est bon (`## [X.Y.Z] - YYYY-MM-DD`)  
→ Vérifier que le tag match la version du changelog

### "WordPress ne détecte pas la mise à jour" ?
→ Vérifier que fftt-match-block.php a la bonne version  
→ Mettre à jour le cache du plugin : Settings → Purge cache
