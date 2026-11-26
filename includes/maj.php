<?php
/**
 * Mises à jour automatiques depuis les releases GitHub (même mécanisme que Page Lock Manager).
 *
 * L'en-tête `Update URI` pointe vers github.com : WordPress ne consulte jamais wordpress.org
 * pour cette extension et appelle le filtre natif `update_plugins_github.com`.
 * Aucun jeton : seule l'API publique des releases est lue (le dépôt doit donc être public).
 * La release doit porter un ZIP dont le dossier racine est `pickupslot/` (voir bin/package.sh).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const PICKUPSLOT_MAJ_CACHE = 'pickupslot_github_release';

/** Dépôt GitHub `compte/depot` interrogé. */
function pickupslot_depot() {
	return (string) apply_filters( 'pickupslot_github_repo', 'maxevstudio/pickupslot' );
}

/**
 * Dernière release publiée, en cache 6 h (1 h en cas d'échec).
 *
 * @return array|null { version, package, url, body }
 */
function pickupslot_derniere_release() {
	$cache = get_transient( PICKUPSLOT_MAJ_CACHE );
	if ( false !== $cache ) {
		return $cache ? $cache : null;
	}

	$reponse = wp_remote_get(
		'https://api.github.com/repos/' . pickupslot_depot() . '/releases/latest',
		array(
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'Pickup-Slot-Updater',
			),
			'timeout' => 10,
		)
	);
	$corps = is_wp_error( $reponse ) || 200 !== wp_remote_retrieve_response_code( $reponse )
		? array()
		: (array) json_decode( wp_remote_retrieve_body( $reponse ), true );

	// Seul un ZIP joint à la release est installable (le zipball de GitHub a un dossier racine imprévisible).
	$zip = '';
	foreach ( isset( $corps['assets'] ) ? (array) $corps['assets'] : array() as $asset ) {
		$url = isset( $asset['browser_download_url'] ) ? (string) $asset['browser_download_url'] : '';
		if ( '.zip' === substr( $url, -4 ) ) {
			$zip = $url;
			break;
		}
	}

	if ( empty( $corps['tag_name'] ) || '' === $zip ) {
		set_transient( PICKUPSLOT_MAJ_CACHE, array(), HOUR_IN_SECONDS );
		return null;
	}

	$release = array(
		'version' => ltrim( (string) $corps['tag_name'], 'v' ),
		'package' => $zip,
		'url'     => isset( $corps['html_url'] ) ? (string) $corps['html_url'] : '',
		'body'    => isset( $corps['body'] ) ? (string) $corps['body'] : '',
	);
	set_transient( PICKUPSLOT_MAJ_CACHE, $release, 6 * HOUR_IN_SECONDS );
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

// Installe les nouvelles releases sans clic. Désactivable : add_filter( 'pickupslot_auto_update', '__return_false' );
add_filter( 'auto_update_plugin', function ( $update, $item ) {
	if ( isset( $item->plugin ) && PICKUPSLOT_BASENAME === $item->plugin ) {
		return (bool) apply_filters( 'pickupslot_auto_update', true );
	}
	return $update;
}, 10, 2 );

add_action( 'upgrader_process_complete', function () {
	delete_transient( PICKUPSLOT_MAJ_CACHE );
}, 10, 0 );
