# FFTT Match Block

Bloc Gutenberg WordPress pour afficher les résultats de matchs de tennis de table issus de l'API de la [Fédération Française de Tennis de Table (FFTT)](https://www.fftt.com/).

## Fonctionnalités

- Sélection de l'équipe parmi les équipes de votre club
- Liste des matchs triés du plus récent au plus ancien
- Affichage du score global (Équipe A – Équipe B)
- Tableau des parties avec le vainqueur en gras et détail des sets

## Installation

1. Téléchargez `fftt-match-block.zip` depuis la page [Releases](https://github.com/jarash/fftt-match-block/releases)
2. Dans WordPress : **Extensions > Ajouter > Mettre en ligne une extension**
3. Uploadez le zip et activez l'extension
4. Allez dans **Réglages > FFTT Match Block** et renseignez :
   - Identifiant API FFTT
   - Mot de passe API FFTT
   - Numéro de club
   - Nombre de matchs à afficher (optionnel)

## Mise à jour automatique

Le plugin vérifie les nouvelles versions directement depuis les GitHub Releases. Les mises à jour apparaissent dans le tableau de bord WordPress comme n'importe quelle autre extension.

## Prérequis

- WordPress ≥ 6.0
- PHP ≥ 8.1

## Développement local

```bash
composer install
```

Le plugin utilise [jarash/fftt-api](https://github.com/jarash/fftt-api) pour communiquer avec l'API FFTT.

## Licence

MIT
