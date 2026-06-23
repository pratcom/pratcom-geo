<?php
/**
 * Plugin Name: Pratcom GEO
 * Plugin URI:  https://pratcom.net/
 * Description: Genere automatiquement /llms.txt (et /xx/llms.txt sur les sites multilingues) au format llmstxt.org, a partir du contenu publie. Zero configuration : titre = nom du site, resume = description Yoast ou slogan, sections deduites de la structure (pages, blog, pages legales). Compatible WPML et Yoast SEO. Aucun tiret cadratin dans la sortie.
 * Version:     1.0.4
 * Author:      Pratcom Media
 * Author URI:  https://pratcom.net/
 * License:     GPL-2.0-or-later
 * Text Domain: pratcom-geo
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * GitHub Plugin URI: pratcom/pratcom-geo
 * Primary Branch:    main
 * Update URI:        https://github.com/pratcom/pratcom-geo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'PRATCOM_GEO_VERSION' ) ) {
	define( 'PRATCOM_GEO_VERSION', '1.0.4' );
}

/* =========================================================================
 * 1. Langues (WPML)
 * ====================================================================== */

function pratcom_geo_default_lang() {
	$d = apply_filters( 'wpml_default_language', null );
	return $d ? $d : 'fr';
}

function pratcom_geo_langs() {
	$default = pratcom_geo_default_lang();
	$langs   = array( $default );
	$active  = apply_filters( 'wpml_active_languages', null );
	if ( is_array( $active ) ) {
		foreach ( array_keys( $active ) as $code ) {
			if ( ! in_array( $code, $langs, true ) ) {
				$langs[] = $code;
			}
		}
	}
	return $langs;
}

function pratcom_geo_other_langs( $lang ) {
	$out = array();
	foreach ( pratcom_geo_langs() as $code ) {
		if ( $code !== $lang ) {
			$out[] = $code;
		}
	}
	return $out;
}

function pratcom_geo_lang_name( $code ) {
	$active = apply_filters( 'wpml_active_languages', null );
	if ( is_array( $active ) && ! empty( $active[ $code ]['native_name'] ) ) {
		return $active[ $code ]['native_name'];
	}
	return strtoupper( $code );
}

/* =========================================================================
 * 2. Routage : rewrite rule + template_redirect
 * ====================================================================== */

function pratcom_geo_add_rewrite() {
	add_rewrite_rule( '^llms\.txt$', 'index.php?pratcom_geo=1', 'top' );
	$default = pratcom_geo_default_lang();
	foreach ( pratcom_geo_langs() as $code ) {
		if ( $code === $default ) {
			continue;
		}
		add_rewrite_rule(
			'^' . preg_quote( $code, '/' ) . '/llms\.txt$',
			'index.php?pratcom_geo=' . $code,
			'top'
		);
	}
}
add_action( 'init', 'pratcom_geo_add_rewrite' );

function pratcom_geo_query_vars( $vars ) {
	$vars[] = 'pratcom_geo';
	return $vars;
}
add_filter( 'query_vars', 'pratcom_geo_query_vars' );

function pratcom_geo_maybe_flush() {
	if ( get_option( 'pratcom_geo_rw_version' ) !== PRATCOM_GEO_VERSION ) {
		pratcom_geo_add_rewrite();
		flush_rewrite_rules( false );
		pratcom_geo_clear_cache(); // purge le cache transient a chaque changement de version.
		update_option( 'pratcom_geo_rw_version', PRATCOM_GEO_VERSION );
	}
}
add_action( 'init', 'pratcom_geo_maybe_flush', 99 );

function pratcom_geo_activate() {
	pratcom_geo_add_rewrite();
	flush_rewrite_rules( false );
	update_option( 'pratcom_geo_rw_version', PRATCOM_GEO_VERSION );
}
register_activation_hook( __FILE__, 'pratcom_geo_activate' );

function pratcom_geo_deactivate() {
	flush_rewrite_rules( false );
	delete_option( 'pratcom_geo_rw_version' );
	pratcom_geo_clear_cache();
}
register_deactivation_hook( __FILE__, 'pratcom_geo_deactivate' );

function pratcom_geo_requested_lang() {
	$default = pratcom_geo_default_lang();
	$qv      = get_query_var( 'pratcom_geo' );

	// Langue explicite dans la variable de requete (regle /xx/llms.txt).
	if ( $qv && '1' !== (string) $qv ) {
		return (string) $qv;
	}

	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$path = '/' . trim( (string) wp_parse_url( $uri, PHP_URL_PATH ), '/' );

	// /xx/llms.txt explicite dans le chemin brut.
	foreach ( pratcom_geo_langs() as $code ) {
		if ( $code !== $default && '/' . $code . '/llms.txt' === $path ) {
			return $code;
		}
	}

	// Endpoint generique : /llms.txt, ou /xx/llms.txt deja reecrit par WPML en
	// /llms.txt (prefixe retire), ou ?pratcom_geo=1. On utilise la langue que
	// WPML a resolue pour CETTE requete, pas la langue par defaut.
	if ( $qv || '/llms.txt' === $path ) {
		$current = apply_filters( 'wpml_current_language', $default );
		return $current ? $current : $default;
	}

	return null;
}

function pratcom_geo_no_canonical( $redirect_url, $requested_url ) {
	if ( null !== pratcom_geo_requested_lang() ) {
		return false;
	}
	return $redirect_url;
}
add_filter( 'redirect_canonical', 'pratcom_geo_no_canonical', 10, 2 );

function pratcom_geo_output() {
	$lang = pratcom_geo_requested_lang();
	if ( null === $lang ) {
		return;
	}
	if ( ! in_array( $lang, pratcom_geo_langs(), true ) ) {
		$lang = pratcom_geo_default_lang();
	}
	$body = pratcom_geo_get_cached( $lang );
	if ( ! headers_sent() ) {
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Cache-Control: public, max-age=3600' );
	}
	echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}
add_action( 'template_redirect', 'pratcom_geo_output', 0 );

/* =========================================================================
 * 3. Cache (transient)
 * ====================================================================== */

function pratcom_geo_get_cached( $lang ) {
	$key    = 'pratcom_geo_llms_' . $lang;
	$cached = get_transient( $key );
	if ( is_string( $cached ) && '' !== $cached ) {
		return $cached;
	}
	$body = pratcom_geo_render( $lang );
	set_transient( $key, $body, (int) apply_filters( 'pratcom_geo_cache_ttl', 12 * HOUR_IN_SECONDS ) );
	return $body;
}

function pratcom_geo_clear_cache() {
	foreach ( pratcom_geo_langs() as $lang ) {
		delete_transient( 'pratcom_geo_llms_' . $lang );
	}
}
add_action( 'save_post', 'pratcom_geo_clear_cache' );
add_action( 'deleted_post', 'pratcom_geo_clear_cache' );
add_action( 'transition_post_status', 'pratcom_geo_clear_cache' );
add_action( 'edited_term', 'pratcom_geo_clear_cache' );
add_action( 'created_term', 'pratcom_geo_clear_cache' );
add_action( 'delete_term', 'pratcom_geo_clear_cache' );

/* =========================================================================
 * 4. Nettoyage du texte (regle : aucun tiret cadratin)
 * ====================================================================== */

function pratcom_geo_clean( $text ) {
	$text = wp_strip_all_tags( (string) $text );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	// Tirets longs (U+2012 a U+2015), signe moins (U+2212), tirets de quadratin
	// (U+2E3A, U+2E3B) remplaces par une virgule.
	$text = preg_replace( '/\s*[\x{2012}\x{2013}\x{2014}\x{2015}\x{2212}\x{2E3A}\x{2E3B}]\s*/u', ', ', $text );
	$text = str_replace( '--', ', ', $text );
	$text = preg_replace( '/\s+/u', ' ', $text );
	$text = preg_replace( '/\s+,/u', ',', $text );
	$text = preg_replace( '/,(?:\s*,)+/u', ',', $text );
	return trim( $text, " \t\n\r\0\x0B" );
}

function pratcom_geo_trim_desc( $text, $max = 250 ) {
	$text = pratcom_geo_clean( $text );
	if ( '' === $text ) {
		return '';
	}
	if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > $max ) {
		$text  = mb_substr( $text, 0, $max );
		$space = mb_strrpos( $text, ' ' );
		if ( $space && $space > 60 ) {
			$text = mb_substr( $text, 0, $space );
		}
		$text = rtrim( $text, " ,;:." ) . '...';
	}
	return $text;
}

/* =========================================================================
 * 5. Donnees + traduction WPML
 * ====================================================================== */

function pratcom_geo_pub( $post ) {
	return ( $post instanceof WP_Post && 'publish' === $post->post_status ) ? $post : null;
}

function pratcom_geo_oid( $id, $type, $lang ) {
	$id = (int) $id;
	if ( ! $id ) {
		return 0;
	}
	if ( has_filter( 'wpml_object_id' ) ) {
		$translated = apply_filters( 'wpml_object_id', $id, $type, false, $lang );
		if ( $translated ) {
			return (int) $translated;
		}
		return ( $lang === pratcom_geo_default_lang() ) ? $id : 0;
	}
	return $id;
}

function pratcom_geo_is_noindex( $id ) {
	return '1' === (string) get_post_meta( $id, '_yoast_wpseo_meta-robots-noindex', true );
}

function pratcom_geo_children( $parent_id ) {
	if ( ! $parent_id ) {
		return array();
	}
	$query = new WP_Query(
		array(
			'post_type'        => 'page',
			'post_parent'      => $parent_id,
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'orderby'          => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);
	return $query->posts;
}

function pratcom_geo_top_pages() {
	$query = new WP_Query(
		array(
			'post_type'        => 'page',
			'post_parent'      => 0,
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'orderby'          => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'no_found_rows'    => true,
			'suppress_filters' => false,
		)
	);
	return $query->posts;
}

function pratcom_geo_post_desc( $id ) {
	$desc = (string) get_post_meta( $id, '_yoast_wpseo_metadesc', true );
	if ( '' === $desc ) {
		$post = get_post( $id );
		if ( $post ) {
			$desc = ( '' !== $post->post_excerpt )
				? $post->post_excerpt
				: wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 32, '' );
		}
	}
	return $desc;
}

function pratcom_geo_is_legal_slug( $slug ) {
	$patterns = apply_filters(
		'pratcom_geo_legal_slugs',
		array(
			'politique-de-confidentialite', 'politique-confidentialite', 'confidentialite',
			'politique-relative-aux-temoins', 'temoins', 'cookies', 'cookie-policy',
			'conditions-utilisation', 'conditions-generales', 'conditions', 'mentions-legales',
			'plan-du-site', 'privacy-policy', 'privacy', 'terms', 'terms-of-service',
			'terms-of-use', 'terms-and-conditions', 'legal', 'sitemap', 'accessibilite', 'accessibility',
		)
	);
	$slug = strtolower( $slug );
	foreach ( $patterns as $pattern ) {
		if ( $slug === $pattern || false !== strpos( $slug, $pattern ) ) {
			return true;
		}
	}
	return false;
}

function pratcom_geo_line( $title, $url, $desc, $sep ) {
	$title = trim( $title );
	$url   = esc_url_raw( $url );
	if ( '' === $title || '' === $url ) {
		return null;
	}
	return ( '' !== $desc )
		? '- [' . $title . '](' . $url . ')' . $sep . $desc
		: '- [' . $title . '](' . $url . ')';
}

function pratcom_geo_entry( $id, $type, $lang, $sep, $suffix = '' ) {
	$tid = pratcom_geo_oid( $id, $type, $lang );
	if ( ! $tid ) {
		return null;
	}
	if ( 'category' === $type ) {
		$url = get_term_link( $tid, 'category' );
		if ( is_wp_error( $url ) ) {
			return null;
		}
		$term  = get_term( $tid, 'category' );
		$title = $term ? $term->name : '';
		$desc  = $term ? $term->description : '';
		if ( '' === $desc ) {
			$desc = (string) get_term_meta( $tid, '_yoast_wpseo_metadesc', true );
		}
	} else {
		if ( 'publish' !== get_post_status( $tid ) ) {
			return null;
		}
		$url   = get_permalink( $tid );
		$title = get_the_title( $tid );
		$desc  = pratcom_geo_post_desc( $tid );
	}
	$title = pratcom_geo_clean( $title ) . $suffix;
	$desc  = pratcom_geo_trim_desc( $desc, 250 );
	return pratcom_geo_line( $title, $url, $desc, $sep );
}

function pratcom_geo_section( $heading, $lines ) {
	$lines = array_values(
		array_filter(
			$lines,
			static function ( $line ) {
				return null !== $line && '' !== $line;
			}
		)
	);
	if ( empty( $lines ) ) {
		return null;
	}
	return '## ' . pratcom_geo_clean( $heading ) . "\n\n" . implode( "\n", $lines );
}

function pratcom_geo_label( $key, $lang ) {
	// Etiquettes generiques (les sections de pages prennent le titre de la page).
	$labels = array(
		'articles' => 'Articles',
		'pages'    => 'Pages',
		'optional' => 'Optional',
	);
	return isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
}

/* =========================================================================
 * 6. Intro auto (titre = nom du site, resume = Yoast/slogan)
 * ====================================================================== */

function pratcom_geo_intro_summary( $lang, $front_id ) {
	$summary = '';
	$front_t = pratcom_geo_oid( $front_id, 'page', $lang );
	if ( $front_t ) {
		$summary = (string) get_post_meta( $front_t, '_yoast_wpseo_metadesc', true );
	}
	if ( '' === $summary ) {
		$titles = get_option( 'wpseo_titles' );
		if ( is_array( $titles ) && ! empty( $titles['metadesc-home-wpseo'] ) ) {
			$summary = (string) $titles['metadesc-home-wpseo'];
		}
	}
	if ( '' === $summary ) {
		$summary = (string) get_bloginfo( 'description' );
	}
	return $summary;
}

/* =========================================================================
 * 7. Rendu du fichier (detection automatique)
 * ====================================================================== */

function pratcom_geo_render( $lang ) {
	$default = pratcom_geo_default_lang();
	$sep     = ( 'en' === $lang ) ? ': ' : ' : ';
	$origin  = apply_filters( 'wpml_current_language', $default );

	/* --- Phase 1 : resoudre la structure dans la LANGUE PAR DEFAUT --- */
	do_action( 'wpml_switch_language', $default );

	$front_id   = ( 'page' === get_option( 'show_on_front' ) ) ? (int) get_option( 'page_on_front' ) : 0;
	$posts_id   = (int) get_option( 'page_for_posts' );
	$privacy_id = (int) get_option( 'wp_page_for_privacy_policy' );
	$exclude    = array_map( 'intval', (array) apply_filters( 'pratcom_geo_exclude_ids', array() ) );

	$hier       = array();
	$leaf_pages = array();
	$leaf_legal = array();

	foreach ( pratcom_geo_top_pages() as $page ) {
		$id = (int) $page->ID;
		if ( $id === $front_id || $id === $posts_id || in_array( $id, $exclude, true ) ) {
			continue;
		}
		if ( pratcom_geo_is_noindex( $id ) ) {
			continue;
		}

		$kid_ids = array();
		foreach ( pratcom_geo_children( $id ) as $child ) {
			if ( pratcom_geo_is_noindex( $child->ID ) || in_array( (int) $child->ID, $exclude, true ) ) {
				continue;
			}
			$kid_ids[] = (int) $child->ID;
		}

		if ( ! empty( $kid_ids ) ) {
			$hier[] = array(
				'top'      => $id,
				'children' => $kid_ids,
			);
		} elseif ( $id === $privacy_id || pratcom_geo_is_legal_slug( $page->post_name ) ) {
			$leaf_legal[] = $id;
		} else {
			$leaf_pages[] = $id;
		}
	}

	$cat_ids = array();
	$cats    = get_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);
	if ( is_array( $cats ) ) {
		foreach ( $cats as $cat ) {
			$cat_ids[] = (int) $cat->term_id;
		}
	}

	$recent = new WP_Query(
		array(
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => (int) apply_filters( 'pratcom_geo_recent_count', 10 ),
			'orderby'             => 'date',
			'order'               => 'DESC',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'suppress_filters'    => false,
		)
	);
	$recent_ids = wp_list_pluck( $recent->posts, 'ID' );

	/* --- Phase 2 : rendre dans la LANGUE CIBLE --- */
	do_action( 'wpml_switch_language', $lang );

	$intro = apply_filters(
		'pratcom_geo_intro',
		array(
			'title'   => (string) get_bloginfo( 'name' ),
			'summary' => pratcom_geo_intro_summary( $lang, $front_id ),
			'body'    => '',
		),
		$lang
	);

	$parts   = array();
	$parts[] = '# ' . pratcom_geo_clean( $intro['title'] );
	if ( '' !== trim( (string) $intro['summary'] ) ) {
		$parts[] = '> ' . pratcom_geo_clean( $intro['summary'] );
	}
	if ( '' !== trim( (string) $intro['body'] ) ) {
		$parts[] = pratcom_geo_clean( $intro['body'] );
	}

	// Sections deduites des pages parentes (ex. Services, Pratcom Connect).
	foreach ( $hier as $section ) {
		$top_t = pratcom_geo_oid( $section['top'], 'page', $lang );
		if ( ! $top_t ) {
			continue;
		}
		$lines   = array();
		$lines[] = pratcom_geo_entry( $section['top'], 'page', $lang, $sep );
		foreach ( $section['children'] as $kid ) {
			$lines[] = pratcom_geo_entry( $kid, 'page', $lang, $sep );
		}
		$parts[] = pratcom_geo_section( get_the_title( $top_t ), $lines );
	}

	// Articles : index + categories + articles recents.
	$lines = array();
	if ( $posts_id ) {
		$lines[] = pratcom_geo_entry( $posts_id, 'page', $lang, $sep );
	}
	foreach ( $cat_ids as $tid ) {
		$lines[] = pratcom_geo_entry( $tid, 'category', $lang, $sep );
	}
	foreach ( $recent_ids as $pid ) {
		$lines[] = pratcom_geo_entry( $pid, 'post', $lang, $sep );
	}
	$articles_heading = pratcom_geo_label( 'articles', $lang );
	if ( $posts_id ) {
		$posts_t = pratcom_geo_oid( $posts_id, 'page', $lang );
		if ( $posts_t ) {
			$articles_heading = get_the_title( $posts_t );
		}
	}
	$parts[] = pratcom_geo_section( $articles_heading, $lines );

	// Pages (feuilles de premier niveau : a propos, contact, etc.).
	$lines = array();
	foreach ( $leaf_pages as $id ) {
		$lines[] = pratcom_geo_entry( $id, 'page', $lang, $sep );
	}
	$parts[] = pratcom_geo_section( pratcom_geo_label( 'pages', $lang ), $lines );

	// Sections croisees vers les autres langues.
	foreach ( pratcom_geo_other_langs( $lang ) as $other ) {
		$suffix    = ' (' . strtoupper( $other ) . ')';
		$landing   = array();
		if ( $front_id ) {
			$landing[] = $front_id;
		}
		foreach ( $hier as $section ) {
			$landing[] = $section['top'];
		}
		if ( $posts_id ) {
			$landing[] = $posts_id;
		}
		foreach ( $leaf_pages as $id ) {
			$landing[] = $id;
		}
		// On bascule dans la langue cible pour que get_permalink rende les bons
		// slugs traduits (un slug peut differer entre les langues, ex. a-propos / about).
		do_action( 'wpml_switch_language', $other );
		$lines = array();
		foreach ( $landing as $id ) {
			$lines[] = pratcom_geo_entry( $id, 'page', $other, $sep, $suffix );
		}
		do_action( 'wpml_switch_language', $lang );
		$parts[] = pratcom_geo_section( pratcom_geo_lang_name( $other ), $lines );
	}

	// Optional : pages legales et utilitaires.
	$lines = array();
	foreach ( $leaf_legal as $id ) {
		$lines[] = pratcom_geo_entry( $id, 'page', $lang, $sep );
	}
	$parts[] = pratcom_geo_section( pratcom_geo_label( 'optional', $lang ), $lines );

	do_action( 'wpml_switch_language', $origin );

	$doc = '';
	foreach ( $parts as $part ) {
		if ( null === $part || '' === $part ) {
			continue;
		}
		$doc .= $part . "\n\n";
	}
	return rtrim( $doc ) . "\n";
}

/* =========================================================================
 * 8. Auto-update depuis GitHub (depot public, sans plugin externe)
 *    Lit le header Version sur la branche, propose la mise a jour dans
 *    wp-admin, et corrige le nom du dossier extrait du zipball (-main).
 * ====================================================================== */

if ( ! defined( 'PRATCOM_GEO_REPO' ) ) {
	define( 'PRATCOM_GEO_REPO', 'pratcom/pratcom-geo' );
}
if ( ! defined( 'PRATCOM_GEO_BRANCH' ) ) {
	define( 'PRATCOM_GEO_BRANCH', 'main' );
}

function pratcom_geo_basename() {
	return plugin_basename( __FILE__ );
}

function pratcom_geo_remote_version() {
	$cached = get_transient( 'pratcom_geo_remote_version' );
	if ( false !== $cached ) {
		return $cached;
	}
	$url  = 'https://raw.githubusercontent.com/' . PRATCOM_GEO_REPO . '/' . PRATCOM_GEO_BRANCH . '/pratcom-geo.php';
	$resp = wp_remote_get( $url, array( 'timeout' => 10 ) );
	$ver  = '';
	if ( ! is_wp_error( $resp ) && 200 === (int) wp_remote_retrieve_response_code( $resp ) ) {
		if ( preg_match( '/^[ \t\/*]*Version:\s*(.+)$/mi', wp_remote_retrieve_body( $resp ), $m ) ) {
			$ver = trim( $m[1] );
		}
	}
	set_transient( 'pratcom_geo_remote_version', $ver, 6 * HOUR_IN_SECONDS );
	return $ver;
}

function pratcom_geo_check_update( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}
	$remote = pratcom_geo_remote_version();
	if ( $remote && version_compare( $remote, PRATCOM_GEO_VERSION, '>' ) ) {
		$transient->response[ pratcom_geo_basename() ] = (object) array(
			'slug'        => 'pratcom-geo',
			'plugin'      => pratcom_geo_basename(),
			'new_version' => $remote,
			'url'         => 'https://github.com/' . PRATCOM_GEO_REPO,
			'package'     => 'https://github.com/' . PRATCOM_GEO_REPO . '/archive/refs/heads/' . PRATCOM_GEO_BRANCH . '.zip',
		);
	}
	return $transient;
}
add_filter( 'pre_set_site_transient_update_plugins', 'pratcom_geo_check_update' );

function pratcom_geo_plugin_info( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || 'pratcom-geo' !== $args->slug ) {
		return $result;
	}
	$remote = pratcom_geo_remote_version();
	return (object) array(
		'name'          => 'Pratcom GEO',
		'slug'          => 'pratcom-geo',
		'version'       => $remote ? $remote : PRATCOM_GEO_VERSION,
		'author'        => '<a href="https://pratcom.net/">Pratcom Media</a>',
		'homepage'      => 'https://github.com/' . PRATCOM_GEO_REPO,
		'download_link' => 'https://github.com/' . PRATCOM_GEO_REPO . '/archive/refs/heads/' . PRATCOM_GEO_BRANCH . '.zip',
		'sections'      => array(
			'description' => 'Genere automatiquement /llms.txt a partir du contenu publie. Mises a jour depuis https://github.com/' . PRATCOM_GEO_REPO,
		),
	);
}
add_filter( 'plugins_api', 'pratcom_geo_plugin_info', 10, 3 );

function pratcom_geo_fix_source_dir( $source, $remote_source, $upgrader, $args = array() ) {
	if ( false === strpos( basename( $source ), 'pratcom-geo' ) ) {
		return $source;
	}
	global $wp_filesystem;
	$desired = trailingslashit( $remote_source ) . 'pratcom-geo';
	if ( untrailingslashit( $source ) === $desired ) {
		return $source;
	}
	if ( $wp_filesystem && $wp_filesystem->move( untrailingslashit( $source ), $desired, true ) ) {
		return trailingslashit( $desired );
	}
	return $source;
}
add_filter( 'upgrader_source_selection', 'pratcom_geo_fix_source_dir', 10, 4 );

function pratcom_geo_after_update() {
	delete_transient( 'pratcom_geo_remote_version' );
}
add_action( 'upgrader_process_complete', 'pratcom_geo_after_update' );
