<?php
/**
 * WordPress-Admin-UI: Abonnementsliste, Einstellungsseite und POST-Handler.
 *
 * @package Kipphard\WiederVerfuegbar
 */

namespace Kipphard\WiederVerfuegbar;

defined( 'ABSPATH' ) || exit;

/**
 * Registriert Admin-Menüs und verarbeitet Formularabsendungen.
 */
class Admin {

	/** Abonnements pro Seite in der Listenansicht. */
	const PER_PAGE = 50;

	/**
	 * Hooks registrieren.
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_wvb_save_settings', array( $this, 'handle_save_settings' ) );
	}

	/**
	 * Untermenüs unter WooCommerce registrieren.
	 */
	public function register_menus() {
		add_submenu_page(
			'woocommerce',
			__( 'Wieder verfügbar – Abonnements', 'wieder-verfuegbar' ),
			__( 'Wieder verfügbar', 'wieder-verfuegbar' ),
			Helpers::CAP,
			WVB_SLUG,
			array( $this, 'render_subscriptions' )
		);
		add_submenu_page(
			'woocommerce',
			__( 'Wieder verfügbar – Einstellungen', 'wieder-verfuegbar' ),
			__( 'WVB Einstellungen', 'wieder-verfuegbar' ),
			Helpers::CAP,
			WVB_SLUG . '-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Assets nur auf den Plugin-Seiten einbinden.
	 *
	 * @param string $hook Aktueller Admin-Seiten-Hook.
	 */
	public function enqueue_assets( $hook ) {
		$pages = array(
			'woocommerce_page_' . WVB_SLUG,
			'woocommerce_page_' . WVB_SLUG . '-settings',
		);
		if ( ! in_array( $hook, $pages, true ) ) {
			return;
		}
		wp_enqueue_style(
			'wvb-admin',
			WVB_URL . 'assets/admin.css',
			array(),
			WVB_VERSION
		);
		wp_enqueue_script(
			'wvb-admin',
			WVB_URL . 'assets/admin.js',
			array(),
			WVB_VERSION,
			true
		);
	}

	// -------------------------------------------------------------------------
	// POST-Handler
	// -------------------------------------------------------------------------

	/**
	 * Einstellungen speichern.
	 */
	public function handle_save_settings() {
		Helpers::guard_post( 'wvb_save_settings' );

		$clean = Helpers::sanitize_settings( $_POST );
		update_option( Helpers::OPT_SETTINGS, $clean );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'   => WVB_SLUG . '-settings',
					'notice' => 'saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// -------------------------------------------------------------------------
	// Seiten-Renderer
	// -------------------------------------------------------------------------

	/**
	 * Abonnementsliste rendern (paginiert, aus DB).
	 */
	public function render_subscriptions() {
		if ( ! current_user_can( Helpers::CAP ) ) {
			return;
		}

		global $wpdb;
		$table = Helpers::table();

		$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$current_page = max( 1, $current_page );
		$offset       = ( $current_page - 1 ) * self::PER_PAGE;

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, product_id, email, status, created FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d",
				self::PER_PAGE,
				$offset
			),
			ARRAY_A
		);

		$total_pages = $total > 0 ? ceil( $total / self::PER_PAGE ) : 1;
		?>
		<div class="wrap wvb-wrap">
			<h1><?php esc_html_e( 'Wieder verfügbar – Abonnements', 'wieder-verfuegbar' ); ?></h1>

			<p>
				<?php
				printf(
					/* translators: %d: Anzahl aktiver Abonnements */
					esc_html__( 'Aktive Abonnements gesamt: %d', 'wieder-verfuegbar' ),
					Subscriptions::count_active()
				);
				?>
			</p>

			<?php if ( empty( $rows ) ) : ?>
				<p><?php esc_html_e( 'Noch keine Abonnements vorhanden.', 'wieder-verfuegbar' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped wvb-subscriptions-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ID', 'wieder-verfuegbar' ); ?></th>
							<th><?php esc_html_e( 'Produkt', 'wieder-verfuegbar' ); ?></th>
							<th><?php esc_html_e( 'E-Mail', 'wieder-verfuegbar' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wieder-verfuegbar' ); ?></th>
							<th><?php esc_html_e( 'Datum', 'wieder-verfuegbar' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$product_id   = absint( $row['product_id'] );
							$product      = wc_get_product( $product_id );
							$product_name = $product ? $product->get_name() : sprintf( '#%d', $product_id );
							$product_url  = $product ? get_edit_post_link( $product_id ) : '';
							?>
							<tr>
								<td><?php echo esc_html( $row['id'] ); ?></td>
								<td>
									<?php if ( $product_url ) : ?>
										<a href="<?php echo esc_url( $product_url ); ?>"><?php echo esc_html( $product_name ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $product_name ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $row['email'] ); ?></td>
								<td><?php echo esc_html( $row['status'] ); ?></td>
								<td><?php echo esc_html( $row['created'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $total_pages > 1 ) : ?>
					<div class="tablenav bottom">
						<div class="tablenav-pages">
							<?php
							echo paginate_links(
								array(
									'base'      => add_query_arg( 'paged', '%#%' ),
									'format'    => '',
									'prev_text' => '&laquo;',
									'next_text' => '&raquo;',
									'total'     => $total_pages,
									'current'   => $current_page,
								)
							);
							?>
						</div>
					</div>
				<?php endif; ?>

			<?php endif; ?>

			<?php if ( Helpers::is_pro() ) : ?>

				<hr>
				<div class="card wvb-pro-actions" style="max-width:680px;padding:20px 24px;margin-top:20px;">
					<h2><?php esc_html_e( 'Pro-Aktionen', 'wieder-verfuegbar' ); ?></h2>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="wvb_export_csv">
						<?php wp_nonce_field( 'wvb_export_csv' ); ?>
						<button type="submit" class="button button-secondary">
							<?php esc_html_e( 'CSV exportieren', 'wieder-verfuegbar' ); ?>
						</button>
					</form>
					<p style="margin-top:12px;">
						<?php
						printf(
							/* translators: %d: Gesamtanzahl der Abonnements */
							esc_html__( 'Abonnements gesamt: %d', 'wieder-verfuegbar' ),
							(int) $total
						);
						?>
					</p>
				</div>

			<?php endif; ?>

		</div>
		<?php
	}

	/**
	 * Einstellungsseite rendern.
	 */
	public function render_settings() {
		if ( ! current_user_can( Helpers::CAP ) ) {
			return;
		}

		$notice   = isset( $_GET['notice'] ) ? sanitize_key( $_GET['notice'] ) : '';
		$is_pro   = Helpers::is_pro();
		$settings = (array) get_option( Helpers::OPT_SETTINGS, array() );
		$defaults = Helpers::defaults();

		$heading       = isset( $settings['heading'] ) ? $settings['heading'] : $defaults['heading'];
		$button_label  = isset( $settings['button_label'] ) ? $settings['button_label'] : $defaults['button_label'];
		$consent_text  = isset( $settings['consent_text'] ) ? $settings['consent_text'] : $defaults['consent_text'];
		$email_subject = isset( $settings['email_subject'] ) ? $settings['email_subject'] : $defaults['email_subject'];
		$email_body    = isset( $settings['email_body'] ) ? $settings['email_body'] : $defaults['email_body'];
		$msg_success   = isset( $settings['msg_success'] ) ? $settings['msg_success'] : $defaults['msg_success'];
		$msg_error     = isset( $settings['msg_error'] ) ? $settings['msg_error'] : $defaults['msg_error'];
		?>
		<div class="wrap wvb-wrap">
			<h1><?php esc_html_e( 'Wieder verfügbar – Einstellungen', 'wieder-verfuegbar' ); ?></h1>

			<?php if ( 'saved' === $notice ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Einstellungen gespeichert.', 'wieder-verfuegbar' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wvb_save_settings">
				<?php wp_nonce_field( 'wvb_save_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="wvb-heading"><?php esc_html_e( 'Formular-Überschrift', 'wieder-verfuegbar' ); ?></label>
						</th>
						<td>
							<input type="text" id="wvb-heading" name="heading" class="regular-text"
								value="<?php echo esc_attr( $heading ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wvb-button-label"><?php esc_html_e( 'Button-Beschriftung', 'wieder-verfuegbar' ); ?></label>
						</th>
						<td>
							<input type="text" id="wvb-button-label" name="button_label" class="regular-text"
								value="<?php echo esc_attr( $button_label ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wvb-consent-text"><?php esc_html_e( 'Einwilligungstext (DSGVO)', 'wieder-verfuegbar' ); ?></label>
						</th>
						<td>
							<input type="text" id="wvb-consent-text" name="consent_text" class="large-text"
								value="<?php echo esc_attr( $consent_text ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wvb-email-subject"><?php esc_html_e( 'E-Mail-Betreff', 'wieder-verfuegbar' ); ?></label>
						</th>
						<td>
							<input type="text" id="wvb-email-subject" name="email_subject" class="large-text"
								value="<?php echo esc_attr( $email_subject ); ?>">
							<p class="description">
								<?php esc_html_e( 'Platzhalter: {product}', 'wieder-verfuegbar' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wvb-email-body"><?php esc_html_e( 'E-Mail-Text', 'wieder-verfuegbar' ); ?></label>
						</th>
						<td>
							<textarea id="wvb-email-body" name="email_body" rows="8" class="large-text"><?php echo esc_textarea( $email_body ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Platzhalter: {product}, {link}', 'wieder-verfuegbar' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wvb-msg-success"><?php esc_html_e( 'Erfolgsmeldung', 'wieder-verfuegbar' ); ?></label>
						</th>
						<td>
							<input type="text" id="wvb-msg-success" name="msg_success" class="large-text"
								value="<?php echo esc_attr( $msg_success ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wvb-msg-error"><?php esc_html_e( 'Fehlermeldung', 'wieder-verfuegbar' ); ?></label>
						</th>
						<td>
							<input type="text" id="wvb-msg-error" name="msg_error" class="large-text"
								value="<?php echo esc_attr( $msg_error ); ?>">
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Einstellungen speichern', 'wieder-verfuegbar' ) ); ?>
			</form>

			<?php if ( $is_pro ) : ?>

				<hr>
				<div class="card wvb-pro-settings" style="max-width:680px;padding:20px 24px;margin-top:20px;">
					<h2><?php esc_html_e( 'Pro-Funktionen', 'wieder-verfuegbar' ); ?></h2>
					<p><?php esc_html_e( 'Wieder verfügbar Pro ist aktiv.', 'wieder-verfuegbar' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Double-Opt-in: E-Mail-Bestätigung vor Aktivierung des Abonnements', 'wieder-verfuegbar' ); ?></li>
						<li><?php esc_html_e( 'Variationsebene: Benachrichtigung pro Produktvariante', 'wieder-verfuegbar' ); ?></li>
						<li><?php esc_html_e( 'HTML-E-Mails mit eigenem Template', 'wieder-verfuegbar' ); ?></li>
						<li><?php esc_html_e( 'CSV-Export aller Abonnements', 'wieder-verfuegbar' ); ?></li>
						<li><?php esc_html_e( 'Statistiken: Öffnungsraten, Konversionen', 'wieder-verfuegbar' ); ?></li>
					</ul>
				</div>

			<?php else : ?>

				<hr>
				<div class="card wvb-pro-teaser" style="max-width:680px;padding:20px 24px;margin-top:20px;background:#f6f7f7;border:1px dashed #a7aaad;">
					<h2><?php esc_html_e( 'Wieder verfügbar Pro', 'wieder-verfuegbar' ); ?></h2>
					<ul class="wvb-pro-features">
						<li>
							<span class="dashicons dashicons-email-alt"></span>
							<?php esc_html_e( 'Double-Opt-in mit E-Mail-Bestätigung (DSGVO-konform)', 'wieder-verfuegbar' ); ?>
						</li>
						<li>
							<span class="dashicons dashicons-networking"></span>
							<?php esc_html_e( 'Benachrichtigung auf Variationsebene', 'wieder-verfuegbar' ); ?>
						</li>
						<li>
							<span class="dashicons dashicons-format-image"></span>
							<?php esc_html_e( 'HTML-E-Mails mit eigenem Branding', 'wieder-verfuegbar' ); ?>
						</li>
						<li>
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e( 'Alle Abonnements als CSV exportieren', 'wieder-verfuegbar' ); ?>
						</li>
						<li>
							<span class="dashicons dashicons-chart-bar"></span>
							<?php esc_html_e( 'Statistiken und Konversionsberichte', 'wieder-verfuegbar' ); ?>
						</li>
					</ul>
					<p>
						<a href="https://products.kipphard.com/wieder-verfuegbar" target="_blank" rel="noopener noreferrer" class="button button-secondary">
							<?php esc_html_e( 'Jetzt upgraden', 'wieder-verfuegbar' ); ?>
						</a>
					</p>
				</div>

			<?php endif; ?>

		</div>
		<?php
	}
}
