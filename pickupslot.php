<?php
/**
 * Plugin Name:       Pickup Slot
 * Plugin URI:        https://github.com/maxevstudio/pickupslot
 * Description:       Click & Collect pour WooCommerce : jour et lieu de retrait au passage de commande (délais de préparation, jours de fermeture, congés), réglés depuis un widget du tableau de bord.
 * Version:           2.1.3
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Maxev
 * Author URI:        https://maxev.fr/
 * License:           GPL-2.0-or-later
 * Update URI:        https://github.com/maxevstudio/pickupslot
 *
 * WC requires at least: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PICKUPSLOT_VERSION', '2.1.3' );
define( 'PICKUPSLOT_FILE', __FILE__ );
define( 'PICKUPSLOT_BASENAME', plugin_basename( __FILE__ ) );
define( 'PICKUPSLOT_URL', plugin_dir_url( __FILE__ ) );

/** Réglages de l'extension. */
const PICKUPSLOT_OPTION = 'pickupslot';

/** Ancienne option du MU-plugin balicco-click-collect.php, lue en repli (reprise). */
const PICKUPSLOT_OPTION_ANCIENNE = 'bcc_click_collect';

/* =========================================================================
 * Réglages
 * ========================================================================= */

/** Jours de la semaine, indexés comme en ISO : 1 = lundi … 7 = dimanche. */
function pickupslot_jours() {
	return array(
		1 => 'Lundi',
		2 => 'Mardi',
		3 => 'Mercredi',
		4 => 'Jeudi',
		5 => 'Vendredi',
		6 => 'Samedi',
		7 => 'Dimanche',
	);
}

function pickupslot_defaut() {
	return array(
		'lieux'      => array(
			array(
				'nom'      => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'adresse'  => '',
				'horaires' => array(), // [jour 1-7 => [[ 'HH:MM', 'HH:MM' ], …]], jour sans plage = fermé
				'lien'     => '', // ancien lien Google Maps (MU-plugin), utilisé tant qu'il n'y a pas d'adresse
			),
		),
		// Consigne ajoutée à l'encart de retrait (e-mails, confirmation). Facultative.
		'consigne'   => '',
		'ouverts'    => array( 1, 2, 3, 4, 5, 6, 7 ),
		// Délai de préparation (en jours) selon le jour où la commande est passée.
		'delais'     => array( 1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 1, 7 => 1 ),
		'fenetre'    => 10,
		// Après cette heure (HH:MM), la commande compte comme passée le lendemain. Vide = pas d'heure limite.
		'limite'     => '',
		'fermetures' => array(),
	);
}

/**
 * Réglages enregistrés. À défaut, ceux de l'ancien MU-plugin (même format),
 * pour qu'un site comme Balicco bascule sans rien ressaisir.
 */
function pickupslot_reglages() {
	$r = get_option( PICKUPSLOT_OPTION );
	if ( ! is_array( $r ) ) {
		$r = get_option( PICKUPSLOT_OPTION_ANCIENNE );
	}
	$r = is_array( $r ) ? wp_parse_args( $r, pickupslot_defaut() ) : pickupslot_defaut();
	foreach ( $r['lieux'] as $i => $l ) {
		$r['lieux'][ $i ] = wp_parse_args( (array) $l, array( 'nom' => '', 'adresse' => '', 'horaires' => array(), 'lien' => '' ) );
		$r['lieux'][ $i ]['horaires'] = pickupslot_horaires_propres( $r['lieux'][ $i ]['horaires'] );
	}
	return $r;
}

/** Capacité requise pour modifier les réglages : gérer les commandes. */
function pickupslot_capacite() {
	return apply_filters( 'pickupslot_capacite', 'edit_shop_orders' );
}

/**
 * L'ancien MU-plugin balicco-click-collect.php est-il encore chargé ?
 * Dans ce cas, c'est lui qui gère la commande : Pickup Slot n'affiche que son widget,
 * pour ne pas doubler les champs.
 */
function pickupslot_ancien_mu_plugin() {
	return function_exists( 'bcc_dates' );
}

// À l'activation, copie les réglages de l'ancien MU-plugin (l'ancienne option est conservée).
register_activation_hook( __FILE__, function () {
	$ancienne = get_option( PICKUPSLOT_OPTION_ANCIENNE );
	if ( false === get_option( PICKUPSLOT_OPTION ) && is_array( $ancienne ) ) {
		add_option( PICKUPSLOT_OPTION, $ancienne, '', false );
	}
} );

/* =========================================================================
 * Calcul des jours de retrait
 * ========================================================================= */

/** Motif de fermeture d'un jour (« Congés »…), ou '' s'il est ouvert. */
function pickupslot_fermeture( DateTimeImmutable $d, $r ) {
	$cle = $d->format( 'Y-m-d' );
	foreach ( $r['fermetures'] as $f ) {
		if ( $cle >= $f['debut'] && $cle <= $f['fin'] ) {
			return $f['motif'] ? $f['motif'] : 'Fermeture exceptionnelle';
		}
	}
	return in_array( (int) $d->format( 'N' ), array_map( 'intval', $r['ouverts'] ), true ) ? '' : 'Jour de fermeture';
}

/**
 * Jours de retrait proposés pour une commande passée à $maintenant : à partir de
 * aujourd'hui + délai de préparation du jour, sur une fenêtre de N jours, fermetures exclues.
 * Passé l'heure limite, la commande compte comme passée le lendemain (délai du lendemain compris).
 *
 * @return string[] Dates au format Y-m-d.
 */
function pickupslot_dates( $maintenant = null, $r = null ) {
	$r          = $r ? $r : pickupslot_reglages();
	$maintenant = $maintenant ? $maintenant : new DateTimeImmutable( 'now', wp_timezone() );
	$auj        = $maintenant->setTime( 0, 0 );
	if ( $r['limite'] && $maintenant->format( 'H:i' ) >= $r['limite'] ) {
		$auj = $auj->modify( '+1 day' );
	}
	$delai = (int) ( isset( $r['delais'][ (int) $auj->format( 'N' ) ] ) ? $r['delais'][ (int) $auj->format( 'N' ) ] : 1 );
	$debut = $auj->modify( '+' . max( 0, $delai ) . ' days' );
	$dates = array();
	for ( $i = 0; $i < max( 1, (int) $r['fenetre'] ); $i++ ) {
		$d = $debut->modify( "+$i days" );
		if ( '' === pickupslot_fermeture( $d, $r ) ) {
			$dates[] = $d->format( 'Y-m-d' );
		}
	}
	return $dates;
}

/** « Mardi 24 décembre » pour le menu du formulaire. */
function pickupslot_libelle_long( $iso ) {
	$texte = wp_date( 'l j F', strtotime( $iso . ' 12:00:00' ) );
	return mb_strtoupper( mb_substr( $texte, 0, 1 ) ) . mb_substr( $texte, 1 );
}

/** Libellé d'une fermeture : « Mar. 10 nov. · Pont » ou « Du 22 déc. au 2 janv. ». */
function pickupslot_libelle_fermeture( $f ) {
	$court = function ( $iso, $avec_jour ) {
		return wp_date( $avec_jour ? 'D j M' : 'j M', strtotime( $iso . ' 12:00:00' ) );
	};
	if ( $f['fin'] && $f['fin'] !== $f['debut'] ) {
		$texte = 'Du ' . $court( $f['debut'], false ) . ' au ' . $court( $f['fin'], false );
	} else {
		$texte = $court( $f['debut'], true );
		$texte = mb_strtoupper( mb_substr( $texte, 0, 1 ) ) . mb_substr( $texte, 1 );
	}
	return $f['motif'] ? $texte . ' · ' . $f['motif'] : $texte;
}

function pickupslot_noms_lieux( $r = null ) {
	$r = $r ? $r : pickupslot_reglages();
	return wp_list_pluck( $r['lieux'], 'nom' );
}

/** Lieu enregistré sous ce nom (nom, adresse, horaires, lien), ou null s'il n'existe plus. */
function pickupslot_lieu( $nom, $r = null ) {
	$r = $r ? $r : pickupslot_reglages();
	foreach ( $r['lieux'] as $l ) {
		if ( $l['nom'] === $nom ) {
			return $l;
		}
	}
	return null;
}

/**
 * Horaires nettoyés : [jour 1-7 => [[ 'HH:MM', 'HH:MM' ], …]] (2 plages au plus, ouverture avant fermeture).
 * Tout autre format (texte libre…) donne un tableau vide : pas d'horaires.
 */
function pickupslot_horaires_propres( $horaires ) {
	$propres = array();
	if ( ! is_array( $horaires ) ) {
		return $propres;
	}
	$heure = '/^([01]\d|2[0-3]):[0-5]\d$/';
	foreach ( array_keys( pickupslot_jours() ) as $n ) {
		$plages = isset( $horaires[ $n ] ) && is_array( $horaires[ $n ] ) ? $horaires[ $n ] : array();
		foreach ( array_slice( array_values( $plages ), 0, 2 ) as $p ) {
			$de = isset( $p[0] ) ? (string) $p[0] : '';
			$a  = isset( $p[1] ) ? (string) $p[1] : '';
			if ( preg_match( $heure, $de ) && preg_match( $heure, $a ) && $de < $a ) {
				$propres[ $n ][] = array( $de, $a );
			}
		}
	}
	return $propres;
}

/** « 08:00 » → « 8 h », « 19:30 » → « 19 h 30 ». */
function pickupslot_heure( $hhmm ) {
	list( $h, $m ) = array_map( 'intval', explode( ':', $hhmm ) );
	return $h . ' h' . ( $m ? ' ' . sprintf( '%02d', $m ) : '' );
}

/**
 * Horaires mis en forme, toujours de la même façon, jours identiques consécutifs regroupés :
 * « Du mardi au samedi : 8 h – 19 h 30 », « Dimanche : 8 h – 13 h », « Lundi : fermé ».
 *
 * @return string[] Une ligne par groupe de jours, vide s'il n'y a aucun horaire.
 */
function pickupslot_horaires_lignes( $horaires ) {
	$horaires = pickupslot_horaires_propres( $horaires );
	if ( ! $horaires ) {
		return array();
	}
	$jours   = pickupslot_jours();
	$groupes = array();
	foreach ( $jours as $n => $nom ) {
		$texte = 'fermé';
		if ( ! empty( $horaires[ $n ] ) ) {
			$texte = implode( ' et ', array_map( function ( $p ) {
				return pickupslot_heure( $p[0] ) . ' – ' . pickupslot_heure( $p[1] );
			}, $horaires[ $n ] ) );
		}
		$dernier = count( $groupes ) - 1;
		if ( $dernier >= 0 && $groupes[ $dernier ]['texte'] === $texte ) {
			$groupes[ $dernier ]['fin'] = $n;
		} else {
			$groupes[] = array( 'debut' => $n, 'fin' => $n, 'texte' => $texte );
		}
	}
	$lignes = array();
	foreach ( $groupes as $g ) {
		$debut = $jours[ $g['debut'] ];
		$fin   = mb_strtolower( $jours[ $g['fin'] ] );
		if ( $g['debut'] === $g['fin'] ) {
			$libelle = $debut;
		} elseif ( $g['fin'] - $g['debut'] === 1 ) {
			$libelle = $debut . ' et ' . $fin;
		} else {
			$libelle = 'Du ' . mb_strtolower( $debut ) . ' au ' . $fin;
		}
		$lignes[] = $libelle . ' : ' . $g['texte'];
	}
	return $lignes;
}

/** Lien d'itinéraire Google Maps vers l'adresse du lieu ; à défaut, l'ancien lien Google Maps. */
function pickupslot_itineraire( $lieu ) {
	$adresse = trim( preg_replace( '/\s*\n\s*/', ', ', (string) $lieu['adresse'] ) );
	if ( '' !== $adresse ) {
		return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $adresse );
	}
	return (string) $lieu['lien'];
}

/* =========================================================================
 * Métas de commande
 * ========================================================================= */

/**
 * Jour (Y-m-d) ou lieu de retrait d'une commande.
 * Lit `_pickupslot_date` / `_pickupslot_location`, à défaut les anciennes métas
 * `pickup_date` / `pickup_location` (CodeRockz puis MU-plugin) : l'historique reste lisible.
 *
 * @param WC_Order $order
 * @param string   $quoi 'date' ou 'location'.
 */
function pickupslot_meta( $order, $quoi ) {
	$valeur = $order->get_meta( '_pickupslot_' . $quoi );
	if ( '' === $valeur || null === $valeur ) {
		$valeur = $order->get_meta( 'pickup_' . $quoi );
	}
	return is_string( $valeur ) ? stripslashes( $valeur ) : '';
}

/* =========================================================================
 * Chargement
 * ========================================================================= */

// Compatible HPOS (tables de commandes). Le passage de commande utilise le formulaire classique.
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', PICKUPSLOT_FILE, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', PICKUPSLOT_FILE, false );
	}
} );

require __DIR__ . '/includes/widget.php';
require __DIR__ . '/includes/maj.php';
if ( ! pickupslot_ancien_mu_plugin() ) {
	require __DIR__ . '/includes/commande.php';
}
