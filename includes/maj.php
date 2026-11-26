<?php
/**
 * Mises à jour depuis les releases GitHub.
 *
 * L'en-tête `Update URI` pointe vers github.com : WordPress ne consulte jamais wordpress.org
 * pour cette extension et appelle le filtre natif `update_plugins_github.com`.
 *
 * La dernière version est décrite par le manifeste `pickupslot.json` joint à chaque release
 * (version, package, url, changelog), lu sur github.com/…/releases/latest/download/.
 * Surtout pas l'API api.github.com : sans jeton, elle n'accepte que 60 appels par heure et par adresse IP,
 * et sur un hébergement mutualisé (one.com) l'adresse est partagée avec d'autres sites qui épuisent ce quota
 * (réponse 403 constatée sur balicco.fr le 2026-11-26 : aucune mise à jour n'apparaissait).
 *
 * Tout passe par le système natif, comme pour Onside : bandeau « nouvelle version disponible »,
 * bouton « Mettre à jour maintenant », lien « Activer / Désactiver les mises à jour auto ».
 * Les mises à jour auto sont activées une seule fois, à la première installation ; l'admin du site garde ensuite la main.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PICKUPSLOT_MAJ_CACHE = 'pickupslot_release';

/** Option posée après la première activation des mises à jour auto (on ne la force qu'une fois). */
const PICKUPSLOT_MAJ_AUTO_INIT = 'pickupslot_auto_update_init';

/** Dépôt GitHub `compte/depot`. */
function pickupslot_depot() {
	return (string) apply_filters( 'pickupslot_github_repo', 'maxevstudio/pickupslot' );
}

/**
 * Dernière release publiée, en cache 15 min (30 min en cas d'échec) : WordPress espace déjà ses propres contrôles.
 *
 * @return array|null { version, package, url, body }
 */
function pickupslot_derniere_release() {
	$cache = get_transient( PICKUPSLOT_MAJ_CACHE );
	if ( false !== $cache ) {
		return $cache ? $cache : null;
	}

	$reponse = wp_remote_get(
		'https://github.com/' . pickupslot_depot() . '/releases/latest/download/pickupslot.json',
		// Pas d'en-tête « Accept: application/json » : github.com répond alors 404 au lieu de rediriger vers le fichier.
		array(
			'timeout'     => 10,
			'redirection' => 5,
		)
	);
	$corps = is_wp_error( $reponse ) || 200 !== wp_remote_retrieve_response_code( $reponse )
		? null
		: json_decode( wp_remote_retrieve_body( $reponse ), true );

	$version = is_array( $corps ) && isset( $corps['version'] ) ? (string) $corps['version'] : '';
	$package = is_array( $corps ) && isset( $corps['package'] ) ? (string) $corps['package'] : '';

	// Seul un ZIP de la release, servi en HTTPS depuis github.com, est installable.
	if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) || 0 !== strpos( $package, 'https://github.com/' ) || '.zip' !== substr( $package, -4 ) ) {
		set_transient( PICKUPSLOT_MAJ_CACHE, array(), 30 * MINUTE_IN_SECONDS );
		return null;
	}

	$release = array(
		'version' => $version,
		'package' => $package,
		'url'     => isset( $corps['url'] ) ? (string) $corps['url'] : 'https://github.com/' . pickupslot_depot(),
		'body'    => isset( $corps['changelog'] ) ? (string) $corps['changelog'] : '',
	);
	set_transient( PICKUPSLOT_MAJ_CACHE, $release, 15 * MINUTE_IN_SECONDS );
	return $release;
}

add_filter( 'update_plugins_github.com', function ( $update, $plugin_data, $plugin_file ) {
	if ( PICKUPSLOT_BASENAME !== $plugin_file ) {
		return $update;
	}
	$release = pickupslot_derniere_release();
	if ( ! $release ) {
		return $update;
	}
	// WordPress compare lui-même `version` à la version installée.
	return array(
		'id'      => isset( $plugin_data['UpdateURI'] ) ? $plugin_data['UpdateURI'] : '',
		'slug'    => 'pickupslot',
		'version' => $release['version'],
		'url'     => $release['url'],
		'package' => $release['package'],
	);
}, 10, 3 );

// Fenêtre « Voir les détails » de l'écran Extensions.
add_filter( 'plugins_api', function ( $resultat, $action, $args ) {
	if ( 'plugin_information' !== $action || empty( $args->slug ) || 'pickupslot' !== $args->slug ) {
		return $resultat;
	}
	$release = pickupslot_derniere_release();
	if ( ! $release ) {
		return $resultat;
	}
	return (object) array(
		'name'          => 'Pickup Slot',
		'slug'          => 'pickupslot',
		'version'       => $release['version'],
		'author'        => '<a href="https://maxev.fr/">Maxev</a>',
		'homepage'      => 'https://github.com/' . pickupslot_depot(),
		'download_link' => $release['package'],
		'requires'      => '6.5',
		'requires_php'  => '7.4',
		'sections'      => array( 'changelog' => wpautop( esc_html( $release['body'] ) ) ),
	);
}, 20, 3 );

/** Version installée, relue sur le disque (juste après une mise à jour, la constante est encore l'ancienne). */
function pickupslot_version_installee() {
	$v = get_file_data( PICKUPSLOT_FILE, array( 'Version' => 'Version' ) );
	return ! empty( $v['Version'] ) ? $v['Version'] : PICKUPSLOT_VERSION;
}

/*
 * WordPress ne refait son propre contrôle qu'au plus toutes les heures (12 h hors écran Extensions) :
 * on ajoute notre réponse à chaque lecture de la liste des mises à jour, pour qu'une nouvelle release
 * apparaisse dans le quart d'heure. Admin, cron et WP-CLI seulement, jamais sur le site public.
 */
add_filter( 'site_transient_update_plugins', function ( $transient ) {
	if ( ! is_object( $transient ) || ! ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) ) {
		return $transient;
	}
	// Même si le contrôle échoue, l'extension figure dans la liste : WordPress affiche alors
	// le lien « Activer / Désactiver les mises à jour auto » (sinon la colonne reste vide).
	$installee = pickupslot_version_installee();
	$release   = pickupslot_derniere_release();
	if ( ! $release ) {
		$release = array(
			'version' => $installee,
			'package' => '',
			'url'     => 'https://github.com/' . pickupslot_depot(),
		);
	}
	$item = (object) array(
		'id'          => 'https://github.com/' . pickupslot_depot(),
		'slug'        => 'pickupslot',
		'plugin'      => PICKUPSLOT_BASENAME,
		'new_version' => $release['version'],
		'url'         => $release['url'],
		'package'     => $release['package'],
		'icons'       => array(),
		'banners'     => array(),
		'tested'      => '',
	);
	$transient->response  = isset( $transient->response ) ? (array) $transient->response : array();
	$transient->no_update = isset( $transient->no_update ) ? (array) $transient->no_update : array();
	if ( $release['package'] && version_compare( $release['version'], $installee, '>' ) ) {
		$transient->response[ PICKUPSLOT_BASENAME ] = $item;
		unset( $transient->no_update[ PICKUPSLOT_BASENAME ] );
	} else {
		unset( $transient->response[ PICKUPSLOT_BASENAME ] );
		$transient->no_update[ PICKUPSLOT_BASENAME ] = $item;
	}
	return $transient;
} );

/*
 * Active les mises à jour auto une seule fois (comme un clic sur « Activer les mises à jour auto ») ;
 * ensuite, le choix fait dans l'écran Extensions est respecté.
 */
add_action( 'admin_init', function () {
	if ( get_site_option( PICKUPSLOT_MAJ_AUTO_INIT ) ) {
		return;
	}
	update_site_option( PICKUPSLOT_MAJ_AUTO_INIT, 1 );
	$auto = (array) get_site_option( 'auto_update_plugins', array() );
	if ( ! in_array( PICKUPSLOT_BASENAME, $auto, true ) ) {
		$auto[] = PICKUPSLOT_BASENAME;
		update_site_option( 'auto_update_plugins', $auto );
	}
} );

add_action( 'upgrader_process_complete', 'pickupslot_vider_cache_maj', 10, 0 );
// « Vérifier à nouveau » (Tableau de bord → Mises à jour) vide le cache de WordPress : on vide le nôtre aussi.
add_action( 'delete_site_transient_update_plugins', 'pickupslot_vider_cache_maj', 10, 0 );

function pickupslot_vider_cache_maj() {
	delete_transient( PICKUPSLOT_MAJ_CACHE );
	delete_transient( 'pickupslot_github_release' ); // cache des versions ≤ 2.1.1 (API GitHub)
}
