# Pratcom GEO

Plugin WordPress qui génère automatiquement `/llms.txt` (et `/xx/llms.txt` sur les sites WPML) au format [llmstxt.org](https://llmstxt.org/), à partir du contenu **publié**. **Zéro configuration.** Compatible WPML et Yoast SEO.

## Ce qu'il fait (sans réglage)
- Titre `#` = nom du site ; résumé `>` = méta description Yoast de l'accueil (repli : slogan).
- Une **section par page parente** ayant des enfants (ex. Services, Pratcom Connect), nommée d'après la page.
- Section **Articles** (blog + catégories + articles récents), **Pages** (pages de 1er niveau), **Optional** (pages légales).
- **WPML** : un fichier par langue + section croisée avec les bonnes URLs traduites.
- Pages **noindex** (Yoast) exclues. **Aucun tiret cadratin** dans la sortie. Cache transient régénéré à la publication.

## Installation
1. `Extensions > Ajouter > Téléverser`, installer le zip, **Activer**.
2. **Supprimer** tout fichier `llms.txt` statique à la racine web (sinon il a priorité).
3. **Exclure** `/llms.txt` (et `/xx/llms.txt`) du cache CDN/Kinsta, ou purger après publication.
4. Vérifier `https://VOTRE-SITE/llms.txt`.

## Mises à jour automatiques (Git Updater)
Ce plugin est compatible **[Git Updater](https://git-updater.com/)** (en-têtes `GitHub Plugin URI` / `Primary Branch` / `Update URI`).
1. Installer et activer **Git Updater** sur chaque site.
2. Dépôt **public** : aucun jeton requis. (Pour un dépôt privé : ajouter un jeton GitHub dans `Réglages > Git Updater > Settings`.)
3. Quand une nouvelle version est poussée sur `main` (numéro de **Version** augmenté), WordPress propose la mise à jour dans wp-admin, comme pour n'importe quel plugin.

## Personnalisation (filtres PHP)
`pratcom_geo_intro`, `pratcom_geo_exclude_ids`, `pratcom_geo_recent_count`, `pratcom_geo_legal_slugs`, `pratcom_geo_cache_ttl`.

## Licence
GPL-2.0-or-later. © Pratcom Media.
