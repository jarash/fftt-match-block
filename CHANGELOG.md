# Changelog

## [1.1.1] - 2026-05-08

### Corrigé
- URL GitHub mise à jour pour le système d'auto-update (`jarash/fftt-match-block`)
- Version du plugin synchronisée avec le changelog

---

## [1.1.0] - 2026-05-08

### Ajouté
- **Cache API** : Système de cache via transients WordPress pour améliorer les performances (TTL par défaut : 3600s)
- **Tables extensibles** : Affichage de 4 lignes visibles avec bouton de déroulement pour les matchs supplémentaires
- **Design mobile optimisé** : Refonte complète du responsive avec deux points de rupture (640px et 400px)

### Modifié
- **Aperçu admin** : Simplifié pour utiliser les données en cache au lieu d'appels API supplémentaires
- **Sélection de matchs** : Enrichie avec les dates des matchs, triés du plus récent au plus ancien

### Corrigé
- Alignement du bloc dans l'éditeur Gutenberg (utilise maintenant `useBlockProps()`)
- Visibilité du bouton "Voir..." (z-index 2 au-dessus du dégradé z-index 1)
- Problèmes de sélection des phases (agrégation des deux phases)

---

## [1.0.0] - 2026-05-04

### Ajouté
- Bloc Gutenberg de sélection et d'affichage d'un match FFTT
- Sélection de l'équipe parmi les équipes du club configuré
- Liste des matchs triés du plus récent au plus ancien
- Rendu côté serveur : score global + tableau des parties avec vainqueur en gras
- Détail des sets sous forme de badges
- Page de réglages (ID API, mot de passe, numéro de club, limite de matchs)
- Mise à jour automatique depuis GitHub Releases
