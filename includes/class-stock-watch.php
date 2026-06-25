<?php
/**
 * Lagerbestand-Überwachung: löst Benachrichtigungen aus wenn Produkte wieder auf Lager kommen.
 *
 * @package Kipphard\WiederVerfuegbar
 */

namespace Kipphard\WiederVerfuegbar;

defined( 'ABSPATH' ) || exit;

/**
 * Beobachtet Lagerstatusänderungen und triggert E-Mail-Benachrichtigungen.
 */
class Stock_Watch {

	/**
	 * Transient-Präfix um Doppelversand innerhalb einer Anfrage zu verhindern.
	 */
	const LOCK_TRANSIENT_PREFIX = 'wvb_notify_lock_';

	/**
	 * Hooks registrieren.
	 */
	public function hooks() {
		// Status-Übergang bei einfachen Produkten und Variationen.
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'on_stock_status_changed' ), 10, 3 );
		add_action( 'woocommerce_variation_set_stock_status', array( $this, 'on_stock_status_changed' ), 10, 3 );

		// Zusätzlicher Hook beim direkten Lager-Update.
		add_action( 'woocommerce_updated_product_stock', array( $this, 'on_stock_updated' ) );
	}

	/**
	 * Reagiert auf einen Lagerstatusübergang.
	 *
	 * @param int         $product_id Produkt-ID.
	 * @param string      $status     Neuer Lagerstatus ('instock', 'outofstock', 'onbackorder').
	 * @param \WC_Product $product    WooCommerce-Produkt-Objekt.
	 */
	public function on_stock_status_changed( $product_id, $status, $product ) {
		if ( 'instock' !== $status ) {
			return;
		}
		$this->dispatch( absint( $product_id ) );
	}

	/**
	 * Reagiert auf direkte Lageraktualisierung (z.B. nach Bestelleingang).
	 *
	 * @param int $product_id Produkt-ID.
	 */
	public function on_stock_updated( $product_id ) {
		$product = wc_get_product( absint( $product_id ) );
		if ( ! $product ) {
			return;
		}
		if ( ! $product->is_in_stock() ) {
			return;
		}
		$this->dispatch( absint( $product_id ) );
	}

	/**
	 * Versendet Benachrichtigungen für ein Produkt (mit Duplikatschutz).
	 *
	 * @param int $product_id Produkt-ID.
	 */
	private function dispatch( $product_id ) {
		if ( $product_id <= 0 ) {
			return;
		}

		// Lock: verhindert Doppelversand wenn beide Hooks feuern.
		$lock_key = self::LOCK_TRANSIENT_PREFIX . $product_id;
		if ( get_transient( $lock_key ) ) {
			return;
		}
		set_transient( $lock_key, 1, 60 );

		$subscribers = Subscriptions::active_for_product( $product_id );

		if ( empty( $subscribers ) ) {
			return;
		}

		Emails::notify( $product_id, $subscribers );

		$ids = wp_list_pluck( $subscribers, 'id' );
		Subscriptions::mark_notified( array_map( 'absint', $ids ) );
	}
}
