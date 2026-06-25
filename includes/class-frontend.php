<?php
/**
 * Frontend-Hooks: Formular auf ausverkauften Produktseiten + AJAX-Handler.
 *
 * @package Kipphard\WiederVerfuegbar
 */

namespace Kipphard\WiederVerfuegbar;

defined( 'ABSPATH' ) || exit;

/**
 * Rendert das „Benachrichtige mich"-Formular und verarbeitet AJAX-Subscribes.
 */
class Frontend {

	/**
	 * Hooks registrieren.
	 */
	public function hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'render_form' ), 35 );
		add_action( 'wp_ajax_wvb_subscribe', array( $this, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_nopriv_wvb_subscribe', array( $this, 'ajax_subscribe' ) );
	}

	/**
	 * Assets einbinden (nur auf Produktseiten nötig).
	 */
	public function enqueue_assets() {
		if ( ! is_product() ) {
			return;
		}
		wp_enqueue_style(
			'wvb-frontend',
			WVB_URL . 'assets/frontend.css',
			array(),
			WVB_VERSION
		);
		wp_enqueue_script(
			'wvb-frontend',
			WVB_URL . 'assets/frontend.js',
			array(),
			WVB_VERSION,
			true
		);
		wp_localize_script(
			'wvb-frontend',
			'wvbData',
			array(
				'ajaxUrl' => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
				'nonce'   => wp_create_nonce( 'wvb_subscribe' ),
				'i18n'    => array(
					'success' => Helpers::get( 'msg_success' ),
					'error'   => Helpers::get( 'msg_error' ),
				),
			)
		);
	}

	/**
	 * Rendert das „Benachrichtige mich"-Formular auf der Produktseite.
	 * Nur wenn das Produkt ausverkauft ist.
	 */
	public function render_form() {
		global $product;
		if ( ! $product instanceof \WC_Product ) {
			return;
		}
		if ( $product->is_in_stock() ) {
			return;
		}

		$heading      = esc_html( Helpers::get( 'heading' ) );
		$button_label = esc_html( Helpers::get( 'button_label' ) );
		$consent_text = esc_html( Helpers::get( 'consent_text' ) );
		$product_id   = absint( $product->get_id() );
		?>
		<div class="wvb-notify-wrap" id="wvb-notify-wrap">
			<h3 class="wvb-heading"><?php echo $heading; ?></h3>

			<div class="wvb-message" aria-live="polite" style="display:none;"></div>

			<form class="wvb-form" id="wvb-form" novalidate>
				<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">

				<div class="wvb-field">
					<label for="wvb-email">
						<?php esc_html_e( 'E-Mail-Adresse', 'wieder-verfuegbar' ); ?>
						<span class="wvb-required" aria-hidden="true">*</span>
					</label>
					<input
						type="email"
						id="wvb-email"
						name="email"
						required
						autocomplete="email"
						placeholder="<?php esc_attr_e( 'ihre@email.de', 'wieder-verfuegbar' ); ?>"
					>
				</div>

				<div class="wvb-field wvb-consent-field">
					<label class="wvb-consent-label">
						<input type="checkbox" name="consent" value="1" required>
						<?php echo $consent_text; ?>
						<span class="wvb-required" aria-hidden="true">*</span>
					</label>
				</div>

				<button type="submit" class="button wvb-submit">
					<?php echo $button_label; ?>
				</button>
			</form>
		</div>
		<?php
	}

	/**
	 * AJAX: E-Mail für Benachrichtigung eintragen.
	 * Öffentlich → Nonce + Produktvalidierung + Ausverkauft-Prüfung zwingend.
	 */
	public function ajax_subscribe() {
		check_ajax_referer( 'wvb_subscribe', 'nonce' );

		$product_id = absint( isset( $_POST['product_id'] ) ? $_POST['product_id'] : 0 );
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$consent    = ! empty( $_POST['consent'] );

		// E-Mail validieren.
		if ( ! is_email( $email ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Bitte geben Sie eine gültige E-Mail-Adresse an.', 'wieder-verfuegbar' ) ),
				400
			);
		}

		// DSGVO-Einwilligung erforderlich.
		if ( ! $consent ) {
			wp_send_json_error(
				array( 'message' => __( 'Bitte stimmen Sie der Benachrichtigung zu.', 'wieder-verfuegbar' ) ),
				400
			);
		}

		// Produkt muss existieren und veröffentlicht sein.
		if ( $product_id <= 0 ) {
			wp_send_json_error(
				array( 'message' => __( 'Ungültiges Produkt.', 'wieder-verfuegbar' ) ),
				400
			);
		}

		$post = get_post( $product_id );
		if ( ! $post || 'product' !== $post->post_type || 'publish' !== $post->post_status ) {
			wp_send_json_error(
				array( 'message' => __( 'Ungültiges Produkt.', 'wieder-verfuegbar' ) ),
				400
			);
		}

		// Produkt muss tatsächlich ausverkauft sein.
		$product = wc_get_product( $product_id );
		if ( ! $product || $product->is_in_stock() ) {
			wp_send_json_error(
				array( 'message' => __( 'Dieses Produkt ist bereits auf Lager.', 'wieder-verfuegbar' ) ),
				400
			);
		}

		// Double-Opt-in (Pro): wenn aktiv, an Double_Optin-Klasse delegieren.
		if ( Helpers::is_pro() && class_exists( __NAMESPACE__ . '\\Double_Optin' ) && Double_Optin::is_enabled() ) {
			Double_Optin::initiate( $product_id, $email );
			wp_send_json_success(
				array( 'message' => __( 'Bitte bestätigen Sie Ihre E-Mail-Adresse. Wir haben Ihnen eine E-Mail gesendet.', 'wieder-verfuegbar' ) )
			);
		}

		$added = Subscriptions::add( $product_id, $email );

		if ( false === $added ) {
			// Bereits eingetragen – trotzdem positives Feedback geben (kein Datenleck).
			wp_send_json_success(
				array( 'message' => esc_html( Helpers::get( 'msg_success' ) ) )
			);
		}

		wp_send_json_success(
			array( 'message' => esc_html( Helpers::get( 'msg_success' ) ) )
		);
	}
}
