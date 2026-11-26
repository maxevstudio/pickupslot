<?php
/**
 * Passage de commande et affichage du retrait (récap, e-mails, compte client, admin).
 * Métas de commande : `_pickupslot_date` (Y-m-d) et `_pickupslot_location` (nom du lieu).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * Passage de commande
 * ========================================================================= */

add_action( 'woocommerce_before_order_notes', function () {
	$r     = pickupslot_reglages();
	$dates = pickupslot_dates( null, $r );
	$lieux = pickupslot_noms_lieux( $r );

	echo '<div id="pickupslot-retrait" class="pickupslot-retrait"><h3>Retrait de commande</h3>';

	if ( ! $dates ) {
		echo '<p class="woocommerce-info">Aucun créneau de retrait n\'est disponible pour le moment. Merci de réessayer plus tard.</p></div>';
		return;
	}

	if ( count( $lieux ) > 1 ) {
		woocommerce_form_field( 'pickupslot_lieu', array(
			'type'     => 'select',
			'label'    => 'Lieu de retrait',
			'required' => true,
			'options'  => array( '' => 'Choisissez un lieu' ) + array_combine( $lieux, $lieux ),
		), WC()->checkout()->get_value( 'pickupslot_lieu' ) );
	} else {
		$l = $r['lieux'][0];
		echo '<p class="form-row form-row-wide pickupslot-lieu-unique"><label>Lieu de retrait</label><strong>' . esc_html( $l['nom'] ) . '</strong>';
		if ( $l['adresse'] ) {
			echo '<br>' . nl2br( esc_html( $l['adresse'] ) );
		}
		if ( pickupslot_itineraire( $l ) ) {
			echo '<br><a href="' . esc_url( pickupslot_itineraire( $l ) ) . '" target="_blank" rel="noopener">Itinéraire</a>';
		}
		echo '</p>';
	}

	$options = array( '' => 'Choisissez un jour' );
	foreach ( $dates as $d ) {
		$options[ $d ] = pickupslot_libelle_long( $d );
	}
	woocommerce_form_field( 'pickupslot_date', array(
		'type'     => 'select',
		'label'    => 'Jour de retrait',
		'required' => true,
		'options'  => $options,
	), WC()->checkout()->get_value( 'pickupslot_date' ) );

	echo '</div>';
} );

add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {
	$date = isset( $_POST['pickupslot_date'] ) ? sanitize_text_field( wp_unslash( $_POST['pickupslot_date'] ) ) : '';
	if ( ! in_array( $date, pickupslot_dates(), true ) ) {
		$errors->add( 'pickupslot_date', '<strong>Jour de retrait</strong> : merci de choisir un jour dans la liste.' );
	}
	$lieux = pickupslot_noms_lieux();
	if ( count( $lieux ) > 1 ) {
		$lieu = isset( $_POST['pickupslot_lieu'] ) ? sanitize_text_field( wp_unslash( $_POST['pickupslot_lieu'] ) ) : '';
		if ( ! in_array( $lieu, $lieux, true ) ) {
			$errors->add( 'pickupslot_lieu', '<strong>Lieu de retrait</strong> : merci de choisir un lieu dans la liste.' );
		}
	}
}, 10, 2 );

add_action( 'woocommerce_checkout_create_order', function ( $order ) {
	if ( empty( $_POST['pickupslot_date'] ) ) {
		return;
	}
	$lieux = pickupslot_noms_lieux();
	$date  = sanitize_text_field( wp_unslash( $_POST['pickupslot_date'] ) );
	$lieu  = count( $lieux ) > 1 && isset( $_POST['pickupslot_lieu'] ) ? sanitize_text_field( wp_unslash( $_POST['pickupslot_lieu'] ) ) : $lieux[0];

	$order->update_meta_data( '_pickupslot_date', $date );
	$order->update_meta_data( '_pickupslot_location', $lieu );
} );

/* =========================================================================
 * Affichage : e-mails, page de confirmation, compte client, admin
 * ========================================================================= */

add_filter( 'woocommerce_get_order_item_totals', function ( $lignes, $order ) {
	$date = pickupslot_meta( $order, 'date' );
	if ( $date ) {
		$lignes['pickupslot_date'] = array(
			'label' => 'Jour de retrait',
			'value' => esc_html( wp_date( 'd.m.Y', strtotime( $date . ' 12:00:00' ) ) ),
		);
	}
	$lieu = pickupslot_meta( $order, 'location' );
	if ( $lieu ) {
		$lignes['pickupslot_location'] = array(
			'label' => 'Lieu de retrait',
			'value' => esc_html( $lieu ),
		);
	}
	return $lignes;
}, 10, 2 );

add_action( 'woocommerce_admin_order_data_after_shipping_address', function ( $order ) {
	$date = pickupslot_meta( $order, 'date' );
	$lieu = pickupslot_meta( $order, 'location' );
	if ( ! $date && ! $lieu ) {
		return;
	}
	?>
	<div class="pickupslot-admin" style="clear:both;padding-top:12px">
		<h3>Retrait</h3>
		<p>
			<strong><?php echo $date ? esc_html( pickupslot_libelle_long( $date ) . wp_date( ' Y', strtotime( $date . ' 12:00:00' ) ) ) : '—'; ?></strong><br>
			<?php echo esc_html( $lieu ); ?>
		</p>
		<p class="form-field form-field-wide">
			<label for="pickupslot_admin_date">Changer le jour de retrait</label>
			<input type="date" id="pickupslot_admin_date" name="pickupslot_admin_date" value="<?php echo esc_attr( $date ); ?>">
		</p>
	</div>
	<?php
} );

add_action( 'woocommerce_process_shop_order_meta', function ( $order_id ) {
	if ( empty( $_POST['pickupslot_admin_date'] ) ) {
		return;
	}
	$date  = sanitize_text_field( wp_unslash( $_POST['pickupslot_admin_date'] ) );
	$order = wc_get_order( $order_id );
	if ( ! $order || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || $date === pickupslot_meta( $order, 'date' ) ) {
		return;
	}
	$order->update_meta_data( '_pickupslot_date', $date );
	// Ancienne commande : le lieu passe aussi sous la nouvelle clé (l'ancienne méta reste en place).
	if ( '' === (string) $order->get_meta( '_pickupslot_location' ) && pickupslot_meta( $order, 'location' ) ) {
		$order->update_meta_data( '_pickupslot_location', pickupslot_meta( $order, 'location' ) );
	}
	$order->save();
}, 50 );

// Colonne « Retrait » dans la liste des commandes (HPOS et ancien stockage).
foreach ( array( 'manage_woocommerce_page_wc-orders_columns', 'manage_edit-shop_order_columns' ) as $pickupslot_hook ) {
	add_filter( $pickupslot_hook, function ( $colonnes ) {
		$res = array();
		foreach ( $colonnes as $cle => $libelle ) {
			$res[ $cle ] = $libelle;
			if ( 'order_status' === $cle ) {
				$res['pickupslot'] = 'Retrait';
			}
		}
		return isset( $res['pickupslot'] ) ? $res : $colonnes + array( 'pickupslot' => 'Retrait' );
	} );
}
foreach ( array( 'manage_woocommerce_page_wc-orders_custom_column', 'manage_shop_order_posts_custom_column' ) as $pickupslot_hook ) {
	add_action( $pickupslot_hook, function ( $colonne, $order ) {
		if ( 'pickupslot' !== $colonne ) {
			return;
		}
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );
		$date  = $order ? pickupslot_meta( $order, 'date' ) : '';
		echo $date ? esc_html( wp_date( 'D j M', strtotime( $date . ' 12:00:00' ) ) ) : '—';
	}, 10, 2 );
}

/* =========================================================================
 * Encart « Retrait de votre commande » : e-mails WooCommerce et page de confirmation.
 * Sans style propre : il reprend la charte des e-mails WooCommerce (titres, couleurs, liens).
 * ========================================================================= */

/**
 * @param WC_Order $order
 * @param bool     $texte Version texte brut (e-mails en texte seul).
 */
function pickupslot_encart( $order, $texte = false ) {
	$date = pickupslot_meta( $order, 'date' );
	$nom  = pickupslot_meta( $order, 'location' );
	if ( ! $date && ! $nom ) {
		return '';
	}
	$r     = pickupslot_reglages();
	$lieu  = $nom ? pickupslot_lieu( $nom, $r ) : null;
	$jour  = $date ? pickupslot_libelle_long( $date ) . wp_date( ' Y', strtotime( $date . ' 12:00:00' ) ) : '';
	$lien  = $lieu ? pickupslot_itineraire( $lieu ) : '';
	$titre = apply_filters( 'pickupslot_titre_encart', 'Retrait de votre commande' );

	if ( $texte ) {
		$lignes = array( mb_strtoupper( $titre ) );
		if ( $jour ) {
			$lignes[] = 'Jour : ' . $jour;
		}
		if ( $nom ) {
			$lignes[] = 'Lieu : ' . $nom;
		}
		if ( $lieu && $lieu['adresse'] ) {
			$lignes[] = $lieu['adresse'];
		}
		if ( $lien ) {
			$lignes[] = 'Itinéraire : ' . $lien;
		}
		if ( $lieu && $lieu['horaires'] ) {
			$lignes[] = "Horaires d'ouverture :\n" . $lieu['horaires'];
		}
		if ( $r['consigne'] ) {
			$lignes[] = $r['consigne'];
		}
		return "\n" . implode( "\n", $lignes ) . "\n\n";
	}

	$html = '<section class="pickupslot-encart" style="margin-bottom:40px">';
	$html .= '<h2>' . esc_html( $titre ) . '</h2>';
	if ( $jour ) {
		$html .= '<p><strong>' . esc_html( $jour ) . '</strong></p>';
	}
	if ( $nom ) {
		$html .= '<p><strong>' . esc_html( $nom ) . '</strong>';
		if ( $lieu && $lieu['adresse'] ) {
			$html .= '<br>' . nl2br( esc_html( $lieu['adresse'] ) );
		}
		if ( $lien ) {
			$html .= '<br><a href="' . esc_url( $lien ) . '" target="_blank" rel="noopener">Itinéraire</a>';
		}
		$html .= '</p>';
	}
	if ( $lieu && $lieu['horaires'] ) {
		$html .= '<p>Horaires d\'ouverture :<br>' . nl2br( esc_html( $lieu['horaires'] ) ) . '</p>';
	}
	if ( $r['consigne'] ) {
		$html .= '<p>' . nl2br( esc_html( $r['consigne'] ) ) . '</p>';
	}
	return $html . '</section>';
}

add_action( 'woocommerce_email_after_order_table', function ( $order, $sent_to_admin, $plain_text ) {
	if ( $order instanceof WC_Order ) {
		echo pickupslot_encart( $order, (bool) $plain_text ); // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans pickupslot_encart()
	}
}, 15, 3 );

// Page de confirmation et détail de la commande dans le compte client.
add_action( 'woocommerce_order_details_after_order_table', function ( $order ) {
	if ( $order instanceof WC_Order ) {
		echo pickupslot_encart( $order ); // phpcs:ignore WordPress.Security.EscapeOutput -- échappé dans pickupslot_encart()
	}
} );
