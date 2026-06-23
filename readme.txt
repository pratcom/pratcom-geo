=== Pratcom GEO ===
Contributors: pratcommedia
Tags: llms.txt, geo, ai, seo, wpml, llmstxt
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Genere automatiquement /llms.txt (format llmstxt.org) a partir du contenu publie. Zero configuration. Compatible WPML et Yoast.

== Description ==
Pratcom GEO sert /llms.txt (et /xx/llms.txt sur les sites multilingues) en text/plain,
au format llmstxt.org, pour que les moteurs de reponse IA (ChatGPT, Claude, Gemini,
Perplexity) trouvent et citent facilement le contenu du site.

Aucune configuration : le titre vient du nom du site, le resume de la meta description
Yoast de la page d'accueil (repli sur le slogan), et les sections sont deduites de la
structure du site (pages parentes et leurs enfants, blog avec categories et articles
recents, pages legales regroupees). Les pages en noindex sont exclues. Aucun tiret
cadratin dans la sortie. Cache par transient, regenere automatiquement a la publication.

Personnalisation optionnelle par filtres : pratcom_geo_intro, pratcom_geo_exclude_ids,
pratcom_geo_recent_count, pratcom_geo_legal_slugs, pratcom_geo_cache_ttl.

== Installation ==
1. Extensions > Ajouter une extension > Televerser une extension. Choisir pratcom-geo.zip, Installer, Activer.
2. Supprimer tout fichier llms.txt STATIQUE a la racine web (sinon il a priorite).
3. Sur Kinsta/CDN : exclure /llms.txt du cache (ou vider le cache apres publication).
4. Visiter https://VOTRE-SITE/llms.txt pour verifier.

== Changelog ==
= 1.0.2 =
* Sections croisees : liens rendus dans la langue cible pour obtenir les bons slugs traduits (corrige p. ex. /en/about/ au lieu de /en/a-propos/).

= 1.0.1 =
* Correctif WPML : /en/llms.txt rend desormais la vraie version anglaise (langue resolue par WPML, meme si le prefixe /en/ est retire avant la detection). URLs forcees dans la bonne langue via le filtre wpml_permalink (la section croisee pointe vers /en/).

= 1.0.0 =
* Version initiale. Generation auto FR + multilingue WPML, compatible Yoast, zero tiret cadratin.
