<?php
/**
 * Wholesale front-end shortcodes.
 *
 * @package Licensesender
 *
 * Shortcodes:
 * - [ls_wholesale_apply]  Registration / application form
 * - [ls_wholesale_catalog] Product catalog (wholesale customers only)
 */

defined( 'ABSPATH' ) || exit;

class LS_Wholesale_Shortcodes {

	/**
	 * Redirect URL injected into WooCommerce auth forms on wholesale pages.
	 *
	 * @var string
	 */
	private static $auth_redirect_url = '';

	/**
	 * Whether wholesale auth templates are being rendered.
	 *
	 * @var bool
	 */
	private static $wholesale_auth_active = false;

	public static function init() {
		add_shortcode( 'ls_wholesale_apply', array( __CLASS__, 'render_apply' ) );
		add_shortcode( 'ls_wholesale_catalog', array( __CLASS__, 'render_catalog' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_apply_submit' ) );
		add_action( 'wp_ajax_ls_wholesale_submit_application', array( __CLASS__, 'ajax_submit_application' ) );
		add_action( 'woocommerce_login_form_start', array( __CLASS__, 'output_auth_redirect_field' ) );
		add_action( 'woocommerce_register_form_start', array( __CLASS__, 'output_auth_redirect_field' ) );
		add_filter( 'woocommerce_login_redirect', array( __CLASS__, 'filter_auth_redirect' ), 10, 2 );
		add_filter( 'woocommerce_registration_redirect', array( __CLASS__, 'filter_auth_redirect' ) );
		add_filter( 'option_woocommerce_enable_myaccount_registration', array( __CLASS__, 'enable_wholesale_registration' ) );
	}

	public static function handle_apply_submit() {
		if ( $_SERVER['REQUEST_METHOD'] !== 'POST' || empty( $_POST['ls_wholesale_apply'] ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ls_wholesale_apply' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'licensesender' ) );
		}

		$result       = self::process_application_submission( $_POST );
		$redirect     = self::get_apply_success_url();
		if ( is_wp_error( $result ) ) {
			$redirect = add_query_arg( 'ls_wholesale_error', rawurlencode( $result->get_error_message() ), $redirect );
		} else {
			$redirect = add_query_arg( 'ls_wholesale_applied', '1', $redirect );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	public static function ajax_submit_application() {
		check_ajax_referer( 'ls_wholesale_apply', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error(
				array( 'message' => __( 'You must be logged in to apply.', 'licensesender' ) ),
				401
			);
		}

		$result = self::process_application_submission( $_POST );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'title'        => __( 'Application submitted', 'licensesender' ),
				'message'      => __( 'Thank you. We will review your wholesale application and email you once approved.', 'licensesender' ),
				'redirect_url' => add_query_arg( 'ls_wholesale_applied', '1', self::get_apply_success_url() ),
			)
		);
	}

	private static function process_application_submission( $data ) {
		return LS_Wholesale::submit_application(
			get_current_user_id(),
			array(
				'company_name'   => wp_unslash( $data['company_name'] ?? '' ),
				'business_email' => wp_unslash( $data['business_email'] ?? '' ),
				'phone'          => wp_unslash( $data['phone'] ?? '' ),
				'messenger_link' => wp_unslash( $data['messenger_link'] ?? '' ),
				'website'        => wp_unslash( $data['website'] ?? '' ),
				'tax_id'         => wp_unslash( $data['tax_id'] ?? '' ),
				'message'        => wp_unslash( $data['message'] ?? '' ),
			)
		);
	}

	private static function get_apply_success_url() {
		$redirect = '';
		if ( ! empty( $_POST['apply_page_url'] ) ) {
			$redirect = wp_validate_redirect( wp_unslash( $_POST['apply_page_url'] ), '' );
		}

		if ( ! $redirect ) {
			$redirect = wp_get_referer();
		}

		if ( ! $redirect ) {
			$redirect = get_permalink();
		}

		return remove_query_arg( array( 'ls_wholesale_applied', 'ls_wholesale_error' ), $redirect );
	}

	public static function render_apply() {
		self::enqueue_wholesale_assets();
		self::localize_apply_script();

		ob_start();

		if ( ! LS_Wholesale::is_enabled() ) {
			self::render_notice(
				'warning',
				__( 'Wholesale unavailable', 'licensesender' ),
				__( 'Wholesale ordering is currently disabled. Please contact the store administrator.', 'licensesender' )
			);
			return ob_get_clean();
		}

		if ( ! is_user_logged_in() ) {
			self::render_wc_auth_section(
				__( 'Wholesale application', 'licensesender' ),
				__( 'Log in or create a WooCommerce account to apply for wholesale access.', 'licensesender' )
			);
			return ob_get_clean();
		}

		if ( ! empty( $_GET['ls_wholesale_error'] ) ) {
			self::render_notice( 'error', __( 'Application failed', 'licensesender' ), sanitize_text_field( wp_unslash( $_GET['ls_wholesale_error'] ) ) );
		}

		if ( ! empty( $_GET['ls_wholesale_applied'] ) ) {
			self::render_notice(
				'success',
				__( 'Application submitted', 'licensesender' ),
				__( 'Thank you. We will review your wholesale application and email you once approved.', 'licensesender' )
			);
			return ob_get_clean();
		}

		if ( LS_Wholesale::user_is_wholesale() ) {
			self::render_notice(
				'success',
				__( 'Wholesale access active', 'licensesender' ),
				__( 'Your account is approved for wholesale ordering.', 'licensesender' )
			);
			return ob_get_clean();
		}

		$application = LS_Wholesale::get_user_application( get_current_user_id() );
		if ( $application && $application['status'] === 'pending' ) {
			self::render_notice(
				'info',
				__( 'Application pending', 'licensesender' ),
				__( 'Your wholesale application is under review. We will notify you by email when it is approved.', 'licensesender' )
			);
			return ob_get_clean();
		}

		if ( $application && $application['status'] === 'rejected' ) {
			self::render_notice(
				'warning',
				__( 'Previous application declined', 'licensesender' ),
				$application['admin_note'] ? $application['admin_note'] : __( 'You may submit a new application below.', 'licensesender' )
			);
		}

		$user  = wp_get_current_user();
		$email = $user->user_email;
		?>
		<div class="ls-wholesale-wrap ls-wholesale-apply" id="ls-wholesale-apply-card">
			<div class="ls-wholesale-card ls-wholesale-apply-header">
				<div class="ls-wholesale-apply-intro">
					<p class="ls-wholesale-eyebrow"><?php esc_html_e( 'B2B onboarding', 'licensesender' ); ?></p>
					<h2><?php esc_html_e( 'Apply for wholesale access', 'licensesender' ); ?></h2>
					<p class="ls-wholesale-lead">
						<?php esc_html_e( 'Tell us about your business. Once approved, you can access wholesale pricing and place B2B orders.', 'licensesender' ); ?>
					</p>
					<ul class="ls-wholesale-catalog-highlights ls-wholesale-apply-highlights">
						<li><?php esc_html_e( 'Volume pricing matched to your account tier', 'licensesender' ); ?></li>
						<li><?php esc_html_e( 'Live stock and bulk digital license ordering', 'licensesender' ); ?></li>
						<li><?php esc_html_e( 'Typically reviewed within 1–2 business days', 'licensesender' ); ?></li>
					</ul>
				</div>
				<div class="ls-wholesale-catalog-meta ls-wholesale-apply-meta">
					<div class="ls-wholesale-catalog-meta-card is-tier">
						<span class="ls-wholesale-catalog-meta-label"><?php esc_html_e( 'Account', 'licensesender' ); ?></span>
						<strong class="ls-wholesale-catalog-meta-value"><?php echo esc_html( $user->display_name ? $user->display_name : $user->user_login ); ?></strong>
						<span class="ls-wholesale-catalog-meta-note"><?php echo esc_html( $email ); ?></span>
					</div>
					<div class="ls-wholesale-catalog-meta-card">
						<span class="ls-wholesale-catalog-meta-label"><?php esc_html_e( 'Status', 'licensesender' ); ?></span>
						<strong class="ls-wholesale-catalog-meta-value"><?php esc_html_e( 'Not enrolled', 'licensesender' ); ?></strong>
						<span class="ls-wholesale-catalog-meta-note"><?php esc_html_e( 'Submit to start review', 'licensesender' ); ?></span>
					</div>
				</div>
			</div>

			<div class="ls-wholesale-card ls-wholesale-apply-form-card">
				<div class="ls-wholesale-apply-form-head">
					<span class="ls-wholesale-apply-form-title"><?php esc_html_e( 'Business details', 'licensesender' ); ?></span>
					<span class="ls-wholesale-apply-form-note"><?php esc_html_e( 'Fields marked with * are required.', 'licensesender' ); ?></span>
				</div>
				<form method="post" class="ls-wholesale-form" id="ls-wholesale-apply-form">
					<?php wp_nonce_field( 'ls_wholesale_apply' ); ?>
					<input type="hidden" name="ls_wholesale_apply" value="1" />
					<input type="hidden" name="apply_page_url" value="<?php echo esc_url( get_permalink() ); ?>" />

					<div class="ls-wholesale-form-grid">
						<p class="ls-wholesale-form-field">
							<label for="ls_company_name"><?php esc_html_e( 'Company name', 'licensesender' ); ?> <span class="ls-wholesale-req" aria-hidden="true">*</span></label>
							<input type="text" id="ls_company_name" name="company_name" autocomplete="organization" required />
						</p>
						<p class="ls-wholesale-form-field">
							<label for="ls_business_email"><?php esc_html_e( 'Business email', 'licensesender' ); ?> <span class="ls-wholesale-req" aria-hidden="true">*</span></label>
							<input type="email" id="ls_business_email" name="business_email" value="<?php echo esc_attr( $email ); ?>" autocomplete="email" required />
						</p>
						<p class="ls-wholesale-form-field">
							<label for="ls_phone"><?php esc_html_e( 'Phone', 'licensesender' ); ?></label>
							<input type="tel" id="ls_phone" name="phone" autocomplete="tel" />
						</p>
						<p class="ls-wholesale-form-field">
							<label for="ls_messenger_link"><?php esc_html_e( 'Telegram or WhatsApp', 'licensesender' ); ?></label>
							<input type="url" id="ls_messenger_link" name="messenger_link" placeholder="<?php esc_attr_e( 'https://t.me/username or https://wa.me/…', 'licensesender' ); ?>" />
						</p>
						<p class="ls-wholesale-form-field">
							<label for="ls_website"><?php esc_html_e( 'Website', 'licensesender' ); ?></label>
							<input type="url" id="ls_website" name="website" placeholder="<?php esc_attr_e( 'https://example.com', 'licensesender' ); ?>" autocomplete="url" />
						</p>
						<p class="ls-wholesale-form-field">
							<label for="ls_tax_id"><?php esc_html_e( 'Tax / VAT ID', 'licensesender' ); ?></label>
							<input type="text" id="ls_tax_id" name="tax_id" autocomplete="off" />
						</p>
						<p class="ls-wholesale-form-field ls-wholesale-form-field-span">
							<label for="ls_message"><?php esc_html_e( 'About your business', 'licensesender' ); ?> <span class="ls-wholesale-req" aria-hidden="true">*</span></label>
							<textarea id="ls_message" name="message" rows="4" required placeholder="<?php esc_attr_e( 'Tell us about your store, region, and expected order volume…', 'licensesender' ); ?>"></textarea>
						</p>
					</div>

					<div class="ls-wholesale-apply-footer">
						<p class="ls-wholesale-apply-footer-note">
							<?php esc_html_e( 'We review every application manually. You will receive an email when your account is approved.', 'licensesender' ); ?>
						</p>
						<button type="submit" class="ls-wholesale-btn ls-wholesale-btn-primary ls-wholesale-apply-submit">
							<?php esc_html_e( 'Submit application', 'licensesender' ); ?>
						</button>
					</div>
				</form>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function render_catalog( $atts ) {
		self::enqueue_wholesale_assets( true );

		$atts = shortcode_atts(
			array(
				'apply_page' => '',
			),
			$atts,
			'ls_wholesale_catalog'
		);

		ob_start();

		if ( ! LS_Wholesale::is_enabled() ) {
			self::render_notice(
				'warning',
				__( 'Wholesale unavailable', 'licensesender' ),
				__( 'Wholesale ordering is currently disabled. Please contact the store administrator.', 'licensesender' )
			);
			return ob_get_clean();
		}

		if ( ! is_user_logged_in() ) {
			self::render_wc_auth_section(
				__( 'Wholesale catalog', 'licensesender' ),
				__( 'Log in or register with your WooCommerce account to view wholesale products and pricing.', 'licensesender' )
			);
			return ob_get_clean();
		}

		if ( ! LS_Wholesale::user_is_wholesale() ) {
			$apply_page_id = $atts['apply_page'] ? (int) $atts['apply_page'] : LS_Wholesale::get_apply_page_id();
			$apply_url     = $apply_page_id ? get_permalink( $apply_page_id ) : '';
			$links     = $apply_url
				? sprintf( '<a class="ls-wholesale-btn" href="%s">%s</a>', esc_url( $apply_url ), esc_html__( 'Apply for wholesale', 'licensesender' ) )
				: '';
			self::render_notice(
				'warning',
				__( 'Wholesale access required', 'licensesender' ),
				__( 'Your account does not have wholesale access yet. Apply and wait for admin approval.', 'licensesender' ),
				$links
			);
			return ob_get_clean();
		}

		$catalog = LS_Wholesale::get_catalog();
		if ( empty( $catalog['success'] ) ) {
			self::render_notice(
				'error',
				__( 'Catalog unavailable', 'licensesender' ),
				esc_html( $catalog['message'] ?? __( 'Could not load wholesale catalog.', 'licensesender' ) )
			);
			return ob_get_clean();
		}

		$products = $catalog['products'] ?? array();
		if ( empty( $products ) ) {
			$catalog  = LS_Wholesale::get_catalog( true );
			$products = $catalog['products'] ?? array();
		}

		$tier          = $catalog['tier'] ?? 'default';
		$product_count = count( $products );
		$min_order_qty = LS_Wholesale::get_min_order_quantity();
		$tier_columns  = LS_Wholesale::collect_catalog_tier_columns( $products );
		$tier_label    = ucwords( str_replace( array( '-', '_' ), ' ', (string) $tier ) );
		$volume_labels = array();

		foreach ( $tier_columns as $min_qty ) {
			$volume_labels[] = $min_qty . '+';
		}

		$volume_tiers_text = ! empty( $volume_labels ) ? implode( ', ', $volume_labels ) : '';
		$wallet_balance    = LS_Wholesale::get_user_wallet_balance();
		?>
		<div class="ls-wholesale-wrap ls-wholesale-catalog">
			<div class="ls-wholesale-card ls-wholesale-catalog-header">
				<div class="ls-wholesale-catalog-intro">
					<p class="ls-wholesale-eyebrow"><?php esc_html_e( 'B2B ordering', 'licensesender' ); ?></p>
					<h2><?php esc_html_e( 'Wholesale Product Catalog', 'licensesender' ); ?></h2>
					<p class="ls-wholesale-lead">
						<?php esc_html_e( 'Access your approved B2B rates, review live inventory, and place bulk digital license orders in one place.', 'licensesender' ); ?>
					</p>
					<ul class="ls-wholesale-catalog-highlights">
						<?php if ( $volume_tiers_text ) : ?>
							<li>
								<?php
								printf(
									/* translators: %s: comma-separated quantity tiers, e.g. 5+, 20+, 100+ */
									esc_html__( 'Volume pricing at %s quantities', 'licensesender' ),
									esc_html( $volume_tiers_text )
								);
								?>
							</li>
						<?php endif; ?>
						<li><?php esc_html_e( 'Real-time stock levels across every product', 'licensesender' ); ?></li>
						<li><?php esc_html_e( 'Tier price applied automatically based on order quantity', 'licensesender' ); ?></li>
						<?php if ( $min_order_qty > 0 ) : ?>
							<li>
								<?php
								printf(
									/* translators: %d: minimum wholesale order quantity */
									esc_html__( 'Minimum order quantity: %d units', 'licensesender' ),
									(int) $min_order_qty
								);
								?>
							</li>
						<?php endif; ?>
					</ul>
				</div>
				<div class="ls-wholesale-catalog-meta">
					<div class="ls-wholesale-catalog-meta-card is-tier">
						<span class="ls-wholesale-catalog-meta-label"><?php esc_html_e( 'Your pricing tier', 'licensesender' ); ?></span>
						<strong class="ls-wholesale-catalog-meta-value"><?php echo esc_html( $tier_label ); ?></strong>
					</div>
					<div class="ls-wholesale-catalog-meta-card">
						<span class="ls-wholesale-catalog-meta-label"><?php esc_html_e( 'Products in catalog', 'licensesender' ); ?></span>
						<strong class="ls-wholesale-catalog-meta-value"><?php echo esc_html( number_format_i18n( $product_count ) ); ?></strong>
					</div>
					<?php if ( is_array( $wallet_balance ) ) : ?>
						<div class="ls-wholesale-catalog-meta-card is-wallet">
							<span class="ls-wholesale-catalog-meta-label"><?php esc_html_e( 'Wallet balance', 'licensesender' ); ?></span>
							<strong class="ls-wholesale-catalog-meta-value"><?php echo wp_kses_post( $wallet_balance['formatted'] ); ?></strong>
						</div>
					<?php endif; ?>
					<?php if ( $min_order_qty > 0 ) : ?>
						<div class="ls-wholesale-catalog-meta-card">
							<span class="ls-wholesale-catalog-meta-label"><?php esc_html_e( 'Minimum order', 'licensesender' ); ?></span>
							<strong class="ls-wholesale-catalog-meta-value"><?php echo esc_html( number_format_i18n( $min_order_qty ) ); ?></strong>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( empty( $products ) ) : ?>
				<div class="ls-wholesale-card">
					<p><?php esc_html_e( 'No wholesale products are available right now.', 'licensesender' ); ?></p>
					<p class="ls-wholesale-lead">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: wholesale tier name */
								__( 'Your catalog tier is "%s". Enable wholesale on at least one product, set a wholesale price, and assign it to this tier. Then refresh this page.', 'licensesender' ),
								$tier ?: 'default'
							)
						);
						?>
					</p>
					<?php if ( current_user_can( 'manage_options' ) ) : ?>
						<p class="ls-wholesale-lead">
							<a class="ls-wholesale-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=ls-wholesale-applications' ) ); ?>">
								<?php esc_html_e( 'Open wholesale admin', 'licensesender' ); ?>
							</a>
						</p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<?php
				$cart_wholesale_qty    = LS_Wholesale_Catalog::get_cart_wholesale_quantity();
				$default_qty           = ! empty( $tier_columns ) ? (int) $tier_columns[0] : 1;
				$remaining_for_minimum = 1;

				if ( $min_order_qty > 0 && $cart_wholesale_qty < $min_order_qty ) {
					$remaining_for_minimum = $min_order_qty - $cart_wholesale_qty;
				}

				$default_qty = max( $default_qty, $remaining_for_minimum );
				$qty_min     = max( 1, $remaining_for_minimum );
				?>
				<div class="ls-wholesale-card ls-wholesale-catalog-toolbar">
					<div class="ls-wholesale-catalog-toolbar-copy">
						<span class="ls-wholesale-catalog-toolbar-title"><?php esc_html_e( 'Browse inventory', 'licensesender' ); ?></span>
						<span class="ls-wholesale-catalog-toolbar-note"><?php esc_html_e( 'Search by product name, SKU, or category tag.', 'licensesender' ); ?></span>
					</div>
					<label class="ls-wholesale-catalog-search">
						<span class="screen-reader-text"><?php esc_html_e( 'Search products', 'licensesender' ); ?></span>
						<span class="ls-wholesale-catalog-search-icon" aria-hidden="true">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
								<circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/>
								<path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
							</svg>
						</span>
						<input
							type="search"
							class="ls-wholesale-catalog-search-input"
							placeholder="<?php esc_attr_e( 'Search products…', 'licensesender' ); ?>"
							autocomplete="off"
						/>
					</label>
				</div>
				<div class="ls-wholesale-card ls-wholesale-table-wrap">
					<table class="ls-wholesale-table ls-wholesale-catalog-table">
						<thead>
							<tr>
								<th class="ls-wholesale-col-product"><?php esc_html_e( 'Product', 'licensesender' ); ?></th>
								<th class="ls-wholesale-col-stock"><?php esc_html_e( 'Stock', 'licensesender' ); ?></th>
								<?php foreach ( $tier_columns as $min_qty ) : ?>
									<th class="ls-wholesale-col-tier"><?php echo esc_html( $min_qty . '+' ); ?></th>
								<?php endforeach; ?>
								<?php if ( empty( $tier_columns ) ) : ?>
									<th class="ls-wholesale-col-tier"><?php esc_html_e( 'Price', 'licensesender' ); ?></th>
								<?php endif; ?>
								<th class="ls-wholesale-col-order"><?php esc_html_e( 'Order', 'licensesender' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $products as $product ) : ?>
								<?php
								$tier_map   = LS_Wholesale::get_tier_price_map( $product );
								$wc_id      = LS_Wholesale_Catalog::find_wc_product_by_sku( $product['sku'] );
								$tag        = LS_Wholesale::get_product_display_tag( $product );
								$stock           = (int) $product['available_stock'];
								$out_of_stock    = $stock === 0;
								$allow_backorder = LS_Wholesale::allows_backorders();
								$can_order       = ! $out_of_stock || $allow_backorder;
								$low_stock_limit = LS_Wholesale::get_low_stock_threshold();
								$low_stock       = $stock > 0 && $stock < $low_stock_limit;
								$stock_class     = $out_of_stock ? ' is-out-of-stock' : ( $low_stock ? ' is-low-stock' : '' );
								if ( $out_of_stock && $allow_backorder ) {
									$stock_class .= ' is-backorder';
								}
								$search_terms = strtolower(
									trim(
										( $product['name'] ?? '' ) . ' ' . $tag . ' ' . ( $product['sku'] ?? '' )
									)
								);
								?>
								<tr class="ls-wholesale-table-row" data-sku="<?php echo esc_attr( $product['sku'] ); ?>" data-search="<?php echo esc_attr( $search_terms ); ?>">
									<td class="ls-wholesale-table-product">
										<div class="ls-wholesale-product-cell">
											<div class="ls-wholesale-product-thumb-wrap">
												<?php if ( $wc_id && has_post_thumbnail( $wc_id ) ) : ?>
													<?php echo get_the_post_thumbnail( $wc_id, array( 56, 56 ), array( 'class' => 'ls-wholesale-product-thumb' ) ); ?>
												<?php else : ?>
													<img
														src="<?php echo esc_url( LS_Wholesale_Catalog::get_placeholder_image_url() ); ?>"
														alt=""
														class="ls-wholesale-product-thumb ls-wholesale-placeholder-image"
														width="56"
														height="56"
														loading="lazy"
														decoding="async"
													/>
												<?php endif; ?>
											</div>
											<div class="ls-wholesale-product-meta">
												<div class="ls-wholesale-product-title"><?php echo esc_html( $product['name'] ); ?></div>
												<?php if ( $tag ) : ?>
													<span class="ls-wholesale-product-tag"><?php echo esc_html( $tag ); ?></span>
												<?php endif; ?>
											</div>
										</div>
									</td>
									<td class="ls-wholesale-table-stock">
										<span class="ls-wholesale-stock-badge<?php echo esc_attr( $stock_class ); ?>">
											<span class="ls-wholesale-stock-dot" aria-hidden="true"></span>
											<?php
											if ( $out_of_stock && $allow_backorder ) {
												esc_html_e( 'Backorder', 'licensesender' );
											} else {
												echo esc_html( (string) $stock );
											}
											?>
										</span>
									</td>
									<?php if ( ! empty( $tier_columns ) ) : ?>
										<?php foreach ( $tier_columns as $min_qty ) : ?>
											<td class="ls-wholesale-table-tier" data-label="<?php echo esc_attr( $min_qty . '+' ); ?>">
												<?php if ( isset( $tier_map[ $min_qty ] ) ) : ?>
													<span class="ls-wholesale-tier-price"><?php echo wp_kses_post( LS_Wholesale_Currency::format_price( $tier_map[ $min_qty ] ) ); ?></span>
												<?php else : ?>
													<span class="ls-wholesale-tier-empty">—</span>
												<?php endif; ?>
											</td>
										<?php endforeach; ?>
									<?php else : ?>
										<td class="ls-wholesale-table-tier">
											<span class="ls-wholesale-tier-price"><?php echo wp_kses_post( LS_Wholesale_Currency::format_price( $product['wholesale_price'] ) ); ?></span>
										</td>
									<?php endif; ?>
									<td class="ls-wholesale-table-order">
										<div class="ls-wholesale-order-controls">
											<label class="screen-reader-text" for="qty-<?php echo esc_attr( $product['sku'] ); ?>"><?php esc_html_e( 'Quantity', 'licensesender' ); ?></label>
											<input
												type="number"
												id="qty-<?php echo esc_attr( $product['sku'] ); ?>"
												class="ls-wholesale-qty"
												min="<?php echo esc_attr( (string) $qty_min ); ?>"
												<?php echo ( $stock > 0 && ! $allow_backorder ) ? 'max="' . esc_attr( (string) $stock ) . '"' : ''; ?>
												value="<?php echo esc_attr( (string) ( $can_order ? $default_qty : 1 ) ); ?>"
												<?php disabled( ! $can_order ); ?>
											/>
											<button type="button" class="ls-wholesale-btn ls-wholesale-btn-primary ls-wholesale-add-to-cart" data-sku="<?php echo esc_attr( $product['sku'] ); ?>" <?php disabled( ! $can_order ); ?>>
												<?php esc_html_e( 'Add', 'licensesender' ); ?>
											</button>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="ls-wholesale-catalog-no-results" hidden><?php esc_html_e( 'No products match your search.', 'licensesender' ); ?></p>
					<div class="ls-wholesale-catalog-pagination" hidden>
						<p class="ls-wholesale-catalog-pagination-info" aria-live="polite"></p>
						<div class="ls-wholesale-catalog-pagination-controls">
							<button type="button" class="ls-wholesale-btn ls-wholesale-catalog-page-prev"><?php esc_html_e( 'Previous', 'licensesender' ); ?></button>
							<span class="ls-wholesale-catalog-page-status"></span>
							<button type="button" class="ls-wholesale-btn ls-wholesale-catalog-page-next"><?php esc_html_e( 'Next', 'licensesender' ); ?></button>
						</div>
					</div>
				</div>
				<div class="ls-wholesale-card ls-wholesale-cart-bar">
					<div class="ls-wholesale-cart-bar-copy">
						<span class="ls-wholesale-cart-bar-title"><?php esc_html_e( 'Ready to order?', 'licensesender' ); ?></span>
						<span class="ls-wholesale-cart-bar-note"><?php esc_html_e( 'Review your cart or continue to checkout.', 'licensesender' ); ?></span>
					</div>
					<p class="ls-wholesale-cart-link">
						<?php
						$cart_count = ( function_exists( 'WC' ) && WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 0;
						?>
						<a class="ls-wholesale-btn ls-wholesale-view-cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
							<?php esc_html_e( 'View cart', 'licensesender' ); ?>
							<span class="ls-wholesale-cart-count"<?php echo $cart_count > 0 ? '' : ' hidden'; ?>><?php echo $cart_count > 0 ? esc_html( '(' . number_format_i18n( $cart_count ) . ')' ) : ''; ?></span>
						</a>
						<a class="ls-wholesale-btn ls-wholesale-btn-primary" href="<?php echo esc_url( wc_get_checkout_url() ); ?>"><?php esc_html_e( 'Checkout', 'licensesender' ); ?></a>
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function localize_apply_script() {
		wp_localize_script(
			'ls-wholesale',
			'lsWholesaleApply',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'ls_wholesale_apply' ),
				'successUrl' => get_permalink() ?: home_url( '/' ),
				'i18n'       => array(
					'submit'        => __( 'Submit application', 'licensesender' ),
					'submitting'    => __( 'Submitting…', 'licensesender' ),
					'thanksTitle'   => __( 'Application submitted', 'licensesender' ),
					'thanksMessage' => __( 'Thank you. We will review your wholesale application and email you once approved.', 'licensesender' ),
					'error'         => __( 'Could not submit your application. Please try again.', 'licensesender' ),
				),
			)
		);
	}

	private static function enqueue_wholesale_assets( $include_cart_fragments = false ) {
		wp_enqueue_style( 'ls-wholesale' );
		wp_enqueue_script( 'ls-wholesale' );

		if ( $include_cart_fragments ) {
			wp_enqueue_script( 'wc-cart-fragments' );
		}

		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		wp_enqueue_style( 'woocommerce-general' );
		wp_enqueue_style( 'woocommerce-layout' );
		wp_enqueue_style( 'woocommerce-smallscreen' );

		if ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) || self::is_register_auth_view() ) {
			if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) {
				wp_enqueue_script( 'wc-password-strength-meter' );
			}
		}
	}

	private static function is_register_auth_view() {
		return isset( $_GET['ls_auth'] ) && sanitize_key( wp_unslash( $_GET['ls_auth'] ) ) === 'register';
	}

	private static function render_wc_auth_section( $title, $message ) {
		?>
		<div class="ls-wholesale-wrap">
			<div class="ls-wholesale-card ls-wholesale-auth-card">
				<h2><?php echo esc_html( $title ); ?></h2>
				<p class="ls-wholesale-lead"><?php echo esc_html( $message ); ?></p>
				<?php
				if ( function_exists( 'woocommerce_output_all_notices' ) ) {
					woocommerce_output_all_notices();
				}
				self::render_wc_login_register_forms();
				?>
			</div>
		</div>
		<?php
	}

	private static function render_wc_login_register_forms() {
		if ( ! function_exists( 'wc_get_template' ) ) {
			self::render_notice(
				'warning',
				__( 'Login unavailable', 'licensesender' ),
				__( 'WooCommerce is required for account login and registration.', 'licensesender' ),
				sprintf(
					'<a class="ls-wholesale-btn" href="%s">%s</a>',
					esc_url( wp_login_url( get_permalink() ) ),
					esc_html__( 'Log in', 'licensesender' )
				)
			);
			return;
		}

		$base_url              = get_permalink() ?: home_url( '/' );
		$redirect_url          = remove_query_arg( array( 'ls_auth', 'ls_wholesale_error', 'ls_wholesale_applied' ), $base_url );
		$is_register           = self::is_register_auth_view();
		self::$auth_redirect_url = $redirect_url;
		self::$wholesale_auth_active = true;

		echo '<div class="ls-wholesale-wc-auth">';

		if ( $is_register ) {
			self::load_wholesale_template( 'form-register.php' );
			echo '<p class="ls-wholesale-auth-switch">';
			esc_html_e( 'Already have an account?', 'licensesender' );
			echo ' <a href="' . esc_url( $redirect_url ) . '">' . esc_html__( 'Log in', 'licensesender' ) . '</a>';
			echo '</p>';
		} else {
			self::load_wholesale_template( 'form-login.php' );
			echo '<p class="ls-wholesale-auth-switch">';
			esc_html_e( "Don't have an account?", 'licensesender' );
			echo ' <a href="' . esc_url( add_query_arg( 'ls_auth', 'register', $redirect_url ) ) . '">' . esc_html__( 'Register', 'licensesender' ) . '</a>';
			echo '</p>';
		}

		echo '</div>';

		self::$auth_redirect_url     = '';
		self::$wholesale_auth_active = false;
	}

	private static function load_wholesale_template( $template ) {
		$path = dirname( dirname( __FILE__ ) ) . '/templates/wholesale/' . $template;
		if ( file_exists( $path ) ) {
			include $path;
		}
	}

	public static function enable_wholesale_registration( $value ) {
		if ( self::$wholesale_auth_active || self::is_register_auth_view() || self::is_wholesale_registration_request() ) {
			return 'yes';
		}

		return $value;
	}

	private static function is_wholesale_registration_request() {
		if ( empty( $_POST['register'] ) || ! is_singular() ) {
			return false;
		}

		$post = get_post();
		if ( ! $post ) {
			return false;
		}

		return has_shortcode( $post->post_content, 'ls_wholesale_apply' )
			|| has_shortcode( $post->post_content, 'ls_wholesale_catalog' );
	}

	public static function output_auth_redirect_field() {
		if ( self::$auth_redirect_url === '' ) {
			return;
		}

		echo '<input type="hidden" name="redirect" value="' . esc_url( self::$auth_redirect_url ) . '" />';
	}

	public static function filter_auth_redirect( $redirect, $user = null ) {
		unset( $user );

		if ( ! empty( $_POST['redirect'] ) ) {
			$target = wp_validate_redirect( wp_unslash( $_POST['redirect'] ), $redirect );
			if ( $target ) {
				return $target;
			}
		}

		return $redirect;
	}

	private static function render_notice( $type, $title, $message, $actions = '' ) {
		?>
		<div class="ls-wholesale-wrap">
			<div class="ls-wholesale-card ls-wholesale-notice ls-wholesale-notice-<?php echo esc_attr( $type ); ?>">
				<h2><?php echo esc_html( $title ); ?></h2>
				<p><?php echo esc_html( $message ); ?></p>
				<?php if ( $actions ) : ?>
					<div class="ls-wholesale-actions"><?php echo wp_kses_post( $actions ); ?></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}

LS_Wholesale_Shortcodes::init();
