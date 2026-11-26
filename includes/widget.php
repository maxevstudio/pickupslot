<?php
/**
 * Widget « Click & Collect » du tableau de bord : le seul écran de réglages de l'extension.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_dashboard_setup', function () {
	if ( ! current_user_can( pickupslot_capacite() ) ) {
		return;
	}
	wp_add_dashboard_widget( 'pickupslot', 'Click & Collect', 'pickupslot_widget' );
} );

// Place le widget en tête de la première colonne, après les extensions qui font de même (Elementor…).
add_action( 'wp_dashboard_setup', function () {
	global $wp_meta_boxes;
	if ( empty( $wp_meta_boxes['dashboard']['normal']['core']['pickupslot'] ) ) {
		return;
	}
	$normal = $wp_meta_boxes['dashboard']['normal']['core'];
	$widget = array( 'pickupslot' => $normal['pickupslot'] );
	unset( $normal['pickupslot'] );
	$wp_meta_boxes['dashboard']['normal']['core'] = array_merge( $widget, $normal );
}, 999 );

add_action( 'admin_enqueue_scripts', function ( $page ) {
	if ( 'index.php' !== $page || ! current_user_can( pickupslot_capacite() ) ) {
		return;
	}
	wp_enqueue_style( 'pickupslot-widget', PICKUPSLOT_URL . 'assets/widget.css', array(), PICKUPSLOT_VERSION );
	wp_enqueue_script( 'pickupslot-widget', PICKUPSLOT_URL . 'assets/widget.js', array(), PICKUPSLOT_VERSION, true );
} );

function pickupslot_widget() {
	$r     = pickupslot_reglages();
	$dates = pickupslot_dates( null, $r );

	if ( isset( $_GET['pickupslot'] ) && 'ok' === $_GET['pickupslot'] ) {
		echo '<div class="notice notice-success inline"><p>Réglages du Click &amp; Collect enregistrés.</p></div>';
	}
	if ( pickupslot_ancien_mu_plugin() ) {
		echo '<div class="notice notice-warning inline"><p>L\'ancien MU-plugin <code>balicco-click-collect.php</code> est encore présent : c\'est lui qui gère le passage de commande. Retirez-le de <code>wp-content/mu-plugins/</code> pour que Pickup Slot prenne le relais (les réglages et les commandes sont repris).</p></div>';
	}
	?>
	<div class="pickupslot">
		<p class="pickupslot-apercu">
			Pour une commande passée maintenant :
			<?php if ( $dates ) : ?>
				retrait possible à partir du <strong><?php echo esc_html( mb_strtolower( pickupslot_libelle_long( $dates[0] ) ) ); ?></strong>
				(<?php echo count( $dates ); ?> jour<?php echo count( $dates ) > 1 ? 's' : ''; ?> au choix).
			<?php else : ?>
				<strong>aucun jour de retrait disponible</strong> — la commande sera bloquée.
			<?php endif; ?>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="pickupslot_enregistrer">
			<?php wp_nonce_field( 'pickupslot_enregistrer' ); ?>

			<h3>Lieux de retrait</h3>
			<div id="pickupslot-lieux">
				<?php foreach ( $r['lieux'] as $i => $l ) : ?>
					<div class="pickupslot-lieu">
						<input type="text" name="lieux[<?php echo (int) $i; ?>][nom]" value="<?php echo esc_attr( $l['nom'] ); ?>" placeholder="Nom du point de retrait">
						<input type="url" name="lieux[<?php echo (int) $i; ?>][lien]" value="<?php echo esc_attr( $l['lien'] ); ?>" placeholder="Lien Google Maps (facultatif)">
						<button type="button" class="button-link pickupslot-lieu-x" aria-label="Retirer ce lieu">&times;</button>
					</div>
				<?php endforeach; ?>
			</div>
			<p><button type="button" class="button" id="pickupslot-lieu-ajouter">Ajouter un lieu</button></p>
			<p class="description">Avec un seul lieu, il est choisi d'office ; à partir de deux, le client choisit dans une liste.</p>

			<h3>Jours de retrait et délais de préparation</h3>
			<table class="pickupslot-table">
				<thead><tr><th></th><th>Retrait possible</th><th>Commande passée ce jour-là : prête</th></tr></thead>
				<tbody>
				<?php foreach ( pickupslot_jours() as $n => $nom ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $nom ); ?></th>
						<td><input type="checkbox" name="ouverts[]" value="<?php echo (int) $n; ?>" <?php checked( in_array( $n, array_map( 'intval', $r['ouverts'] ), true ) ); ?>></td>
						<td><input type="number" min="0" max="30" name="delais[<?php echo (int) $n; ?>]" value="<?php echo (int) ( isset( $r['delais'][ $n ] ) ? $r['delais'][ $n ] : 1 ); ?>"> jour(s) plus tard</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p>Le client peut choisir parmi les <input type="number" min="1" max="60" name="fenetre" value="<?php echo (int) $r['fenetre']; ?>" class="pickupslot-court"> jours qui suivent le premier jour possible.</p>
			<p>
				Heure limite de commande : <input type="time" name="limite" value="<?php echo esc_attr( $r['limite'] ); ?>">
				<span class="description">— passé cette heure, la commande compte comme passée le lendemain. Laissez vide pour ne pas en tenir compte.</span>
			</p>

			<h3>Fermetures exceptionnelles</h3>
			<div class="pickupslot-ajout">
				<input type="date" id="pickupslot-f-debut" aria-label="Date de fermeture" min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>">
				<span>au</span>
				<input type="date" id="pickupslot-f-fin" aria-label="Jusqu'au (facultatif)" min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>">
				<input type="text" id="pickupslot-f-motif" placeholder="Motif (facultatif)">
				<button type="button" class="button" id="pickupslot-f-ajouter">Ajouter</button>
			</div>
			<div class="pickupslot-pastilles" id="pickupslot-pastilles" data-vide="Aucune fermeture prévue.">
				<?php foreach ( $r['fermetures'] as $i => $f ) : ?>
					<span class="pickupslot-pastille" data-debut="<?php echo esc_attr( $f['debut'] ); ?>">
						<?php echo esc_html( pickupslot_libelle_fermeture( $f ) ); ?>
						<button type="button" class="pickupslot-pastille-x" aria-label="Retirer cette fermeture">&times;</button>
						<input type="hidden" name="fermetures[<?php echo (int) $i; ?>][debut]" value="<?php echo esc_attr( $f['debut'] ); ?>">
						<input type="hidden" name="fermetures[<?php echo (int) $i; ?>][fin]" value="<?php echo esc_attr( $f['fin'] ); ?>">
						<input type="hidden" name="fermetures[<?php echo (int) $i; ?>][motif]" value="<?php echo esc_attr( $f['motif'] ); ?>">
					</span>
				<?php endforeach; ?>
			</div>
			<p class="description">Aucun retrait ne sera proposé ces jours-là. Choisissez une date et cliquez sur « Ajouter » ; remplissez « au » pour une période. Pensez à enregistrer. Les dates passées sont retirées automatiquement.</p>

			<?php submit_button( 'Enregistrer', 'primary', 'submit', false ); ?>
		</form>
	</div>
	<template id="pickupslot-lieu-modele">
		<div class="pickupslot-lieu">
			<input type="text" data-champ="nom" placeholder="Nom du point de retrait">
			<input type="url" data-champ="lien" placeholder="Lien Google Maps (facultatif)">
			<button type="button" class="button-link pickupslot-lieu-x" aria-label="Retirer ce lieu">&times;</button>
		</div>
	</template>
	<?php
}

add_action( 'admin_post_pickupslot_enregistrer', function () {
	if ( ! current_user_can( pickupslot_capacite() ) ) {
		wp_die( "Vous n'avez pas les droits pour modifier le Click & Collect." );
	}
	check_admin_referer( 'pickupslot_enregistrer' );

	$date = function ( $v ) {
		$v = sanitize_text_field( wp_unslash( (string) $v ) );
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
	};

	$r = pickupslot_defaut();

	$r['lieux'] = array();
	foreach ( isset( $_POST['lieux'] ) ? (array) $_POST['lieux'] : array() as $l ) {
		$l   = (array) $l;
		$nom = sanitize_text_field( wp_unslash( isset( $l['nom'] ) ? $l['nom'] : '' ) );
		if ( '' !== $nom ) {
			$r['lieux'][] = array(
				'nom'  => $nom,
				'lien' => esc_url_raw( wp_unslash( isset( $l['lien'] ) ? $l['lien'] : '' ) ),
			);
		}
	}
	if ( ! $r['lieux'] ) {
		$r['lieux'] = pickupslot_reglages()['lieux']; // on garde toujours au moins un lieu
	}

	$ouverts      = isset( $_POST['ouverts'] ) ? array_map( 'intval', (array) $_POST['ouverts'] ) : array();
	$r['ouverts'] = array_values( array_intersect( array_keys( pickupslot_jours() ), $ouverts ) );

	$delais = isset( $_POST['delais'] ) ? (array) $_POST['delais'] : array();
	foreach ( array_keys( pickupslot_jours() ) as $n ) {
		$r['delais'][ $n ] = max( 0, min( 30, (int) ( isset( $delais[ $n ] ) ? $delais[ $n ] : 1 ) ) );
	}
	$r['fenetre'] = max( 1, min( 60, (int) ( isset( $_POST['fenetre'] ) ? $_POST['fenetre'] : 10 ) ) );
	$limite       = isset( $_POST['limite'] ) ? sanitize_text_field( wp_unslash( $_POST['limite'] ) ) : '';
	$r['limite']  = preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $limite ) ? $limite : '';

	$aujourdhui      = wp_date( 'Y-m-d' );
	$r['fermetures'] = array();
	foreach ( isset( $_POST['fermetures'] ) ? (array) $_POST['fermetures'] : array() as $f ) {
		$f     = (array) $f;
		$debut = $date( isset( $f['debut'] ) ? $f['debut'] : '' );
		if ( ! $debut ) {
			continue;
		}
		$fin = $date( isset( $f['fin'] ) ? $f['fin'] : '' );
		$fin = ( $fin && $fin >= $debut ) ? $fin : $debut;
		if ( $fin < $aujourdhui ) {
			continue; // fermeture passée
		}
		$r['fermetures'][] = array(
			'debut' => $debut,
			'fin'   => $fin,
			'motif' => sanitize_text_field( wp_unslash( isset( $f['motif'] ) ? $f['motif'] : '' ) ),
		);
	}
	usort( $r['fermetures'], function ( $a, $b ) {
		return strcmp( $a['debut'], $b['debut'] );
	} );

	update_option( PICKUPSLOT_OPTION, $r, false );
	wp_safe_redirect( add_query_arg( 'pickupslot', 'ok', admin_url( 'index.php' ) ) . '#pickupslot' );
	exit;
} );
