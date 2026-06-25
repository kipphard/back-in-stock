<?php
/**
 * E-Mail-Versand für Lagerbenachrichtigungen.
 *
 * @package Kipphard\WiederVerfuegbar
 */

namespace Kipphard\WiederVerfuegbar;

defined( 'ABSPATH' ) || exit;

/**
 * Versendet Benachrichtigungs-E-Mails an Abonnenten.
 */
class Emails {

	/**
	 * Sendet die Benachrichtigung an alle übergebenen Abonnenten.
	 *
	 * @param int   $product_id  Produkt-ID.
	 * @param array $subscribers Liste von Abonnenten: array{id:int, email:string}[].
	 */
	public static function notify( $product_id, array $subscribers ) {
		if ( empty( $subscribers ) ) {
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		$product_name = $product->get_name();
		$product_link = get_permalink( $product_id );

		$subject_template = Helpers::get( 'email_subject' );
		$body_template    = Helpers::get( 'email_body' );

		$subject = str_replace( '{product}', $product_name, $subject_template );
		$body    = str_replace(
			array( '{product}', '{link}' ),
			array( $product_name, $product_link ),
			$body_template
		);

		// Plaintext-E-Mails senden.
		foreach ( $subscribers as $subscriber ) {
			$email = sanitize_email( $subscriber['email'] );
			if ( ! is_email( $email ) ) {
				continue;
			}
			wp_mail( $email, sanitize_text_field( $subject ), $body );
		}
	}
}
