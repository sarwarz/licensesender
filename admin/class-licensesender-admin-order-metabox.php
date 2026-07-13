<?php
defined( 'ABSPATH' ) || exit;

class Licensesender_Admin_MetaBoxes {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_footer', array( $this, 'print_inline_js' ) );
	}

	public function enqueue_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}

		$allowed = array( 'shop_order', 'woocommerce_page_wc-orders' );
		if ( ! in_array( $screen->id, $allowed, true ) ) {
			return;
		}

		$css_path = plugin_dir_path( __FILE__ ) . 'css/ls-order-metabox.css';
		wp_enqueue_style(
			'ls-order-metabox',
			plugin_dir_url( __FILE__ ) . 'css/ls-order-metabox.css',
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0.0'
		);
	}

	public function register_metabox() {
		$screens = array( 'shop_order' );

		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
			&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}

		foreach ( array_unique( $screens ) as $screen ) {
			add_meta_box(
				'ls_order_license_keys',
				__( 'License Keys', 'licensesender' ),
				array( $this, 'render_metabox' ),
				$screen,
				'advanced',
				'high'
			);
		}
	}

	public function render_metabox( $post_or_order ) {
		$order = ( $post_or_order instanceof WC_Order )
			? $post_or_order
			: wc_get_order( $post_or_order->ID ?? 0 );

		if ( ! $order || $order->get_status() !== 'completed' ) {
			echo '<p>' . esc_html__( 'License keys are available only for completed orders.', 'licensesender' ) . '</p>';
			return;
		}

		if ( ! ls_order_has_licensable_products( $order ) ) {
			echo '<p class="ls-muted">' . esc_html__( 'This order has no licensesender products.', 'licensesender' ) . '</p>';
			return;
		}

		$order_id = $order->get_id();

		if ( class_exists( 'LS_License_Cache' ) && ls_count_fetched_license_keys( $order_id ) > 0 ) {
			// Webhooks keep cache in sync; only prune local duplicates on view.
			LS_License_Cache::prune_excess_keys_for_order( $order_id );
		}

		$expected_total  = ls_count_expected_license_keys( $order );
		$fetched_total   = ls_count_fetched_license_keys( $order_id );
		$all_complete    = $expected_total > 0 && $fetched_total >= $expected_total;
		$licenses_url    = add_query_arg(
			array(
				'page'      => 'ls-licensesender',
				'ls_search' => (string) $order_id,
			),
			admin_url( 'admin.php' )
		);
		$first_report = array(
			'id'           => 0,
			'key'          => '',
			'product_id'   => 0,
			'product_name' => '',
		);
		if ( $fetched_total > 0 ) {
			foreach ( $order->get_items() as $item ) {
				$product_id = (int) ( $item->get_variation_id() ?: $item->get_product_id() );
				if ( ! ls_is_licensesender_enabled( $product_id ) || ! ls_get_mapped_sku( $product_id ) ) {
					continue;
				}
				$cached = ls_get_cached_licenses_for_product( $order_id, $product_id );
				if ( ! empty( $cached[0]->id ) ) {
					$first_report = array(
						'id'           => (int) $cached[0]->id,
						'key'          => (string) ( $cached[0]->key_value ?? '' ),
						'product_id'   => $product_id,
						'product_name' => $item->get_name(),
					);
					break;
				}
			}
		}
		$nonce           = wp_create_nonce( 'ls_fetch_license_api' );
		$email_enabled   = LS_License_Email_Service::is_enabled();
		$can_resend      = $email_enabled && $all_complete;
		?>

		<div class="ls-order-license-panel" id="ls-order-license-panel" data-order-id="<?php echo esc_attr( $order_id ); ?>">
			<div class="ls-order-license-summary">
				<span class="ls-summary-stat">
					<strong><?php esc_html_e( 'Keys:', 'licensesender' ); ?></strong>
					<span id="ls-keys-progress-text"><?php echo esc_html( sprintf( '%d / %d', $fetched_total, $expected_total ) ); ?></span>
				</span>
				<span id="ls-email-status-wrap"><?php echo LS_Admin_Order_Actions::get_email_status_html( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>

				<div class="ls-order-license-toolbar">
					<a href="<?php echo esc_url( $licenses_url ); ?>" class="button button-small"><?php esc_html_e( 'All License Keys', 'licensesender' ); ?></a>
					<button type="button" class="button button-small" id="ls-admin-get-all-keys" <?php disabled( $all_complete ); ?>>
						<?php esc_html_e( 'Get All Keys', 'licensesender' ); ?>
					</button>
					<button
						type="button"
						class="button button-small"
						id="ls-admin-resend-email"
						data-order-id="<?php echo esc_attr( $order_id ); ?>"
						<?php disabled( ! $can_resend ); ?>
					>
						<?php esc_html_e( 'Resend License Email', 'licensesender' ); ?>
					</button>
					<?php if ( $first_report['id'] > 0 ) : ?>
					<button
						type="button"
						class="button button-small ls-report-key-btn"
						id="ls-admin-report-keys"
						data-license-id="<?php echo esc_attr( (string) $first_report['id'] ); ?>"
						data-license-key="<?php echo esc_attr( $first_report['key'] ); ?>"
						data-order-id="<?php echo esc_attr( (string) $order_id ); ?>"
						data-product-id="<?php echo esc_attr( (string) $first_report['product_id'] ); ?>"
						data-product-name="<?php echo esc_attr( $first_report['product_name'] ); ?>"
					>
						<?php esc_html_e( 'Report Key', 'licensesender' ); ?>
					</button>
					<?php endif; ?>
				</div>
			</div>

			<table class="widefat striped ls-order-license-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Product', 'licensesender' ); ?></th>
						<th style="width:60px;"><?php esc_html_e( 'Qty', 'licensesender' ); ?></th>
						<th style="width:90px;"><?php esc_html_e( 'Progress', 'licensesender' ); ?></th>
						<th><?php esc_html_e( 'License Keys', 'licensesender' ); ?></th>
						<th style="width:130px;"><?php esc_html_e( 'Action', 'licensesender' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				$rendered_products = array();

				foreach ( $order->get_items() as $item ) :
					$product_id = (int) ( $item->get_variation_id() ?: $item->get_product_id() );

					if ( ! ls_is_licensesender_enabled( $product_id ) || ! ls_get_mapped_sku( $product_id ) ) {
						continue;
					}

					// One combined row per product_id (duplicate line items share cache).
					if ( isset( $rendered_products[ $product_id ] ) ) {
						continue;
					}
					$rendered_products[ $product_id ] = true;

					$expected_qty = ls_count_expected_keys_for_product_in_order( $order, $product_id );
					$cached       = ls_get_cached_licenses_for_product( $order_id, $product_id );
					$fetched_qty  = count( $cached );
					$is_complete  = $fetched_qty >= $expected_qty;
					$progress_cls = $is_complete ? 'is-complete' : ( $fetched_qty > 0 ? 'is-partial' : 'is-empty' );
					?>
					<tr data-product-row="<?php echo esc_attr( $product_id ); ?>" data-expected="<?php echo esc_attr( $expected_qty ); ?>">
						<td><?php echo esc_html( $item->get_name() ); ?></td>
						<td><?php echo esc_html( (string) $expected_qty ); ?></td>
						<td>
							<span class="ls-license-progress <?php echo esc_attr( $progress_cls ); ?> ls-product-progress">
								<?php echo esc_html( sprintf( '%d/%d', $fetched_qty, $expected_qty ) ); ?>
							</span>
						</td>
						<td class="ls-license-cell">
							<?php echo ls_render_admin_order_license_keys_html( $cached, $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</td>
						<td class="ls-license-action-cell">
							<?php if ( ! $is_complete ) : ?>
								<button
									type="button"
									class="button button-primary button-small ls-get-license"
									data-order-id="<?php echo esc_attr( $order_id ); ?>"
									data-product-id="<?php echo esc_attr( $product_id ); ?>"
									data-email="<?php echo esc_attr( $order->get_billing_email() ); ?>"
									data-qnty="<?php echo esc_attr( $expected_qty ); ?>"
								>
									<?php esc_html_e( 'Get License', 'licensesender' ); ?>
								</button>
							<?php else : ?>
								<span class="ls-status ls-status-success" title="<?php esc_attr_e( 'All keys fetched', 'licensesender' ); ?>">✔</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div id="ls-report-key-modal" class="ls-report-modal" hidden>
			<div class="ls-report-modal-backdrop" data-ls-report-close></div>
			<div class="ls-report-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ls-report-modal-title">
				<div class="ls-report-modal-header">
					<h2 id="ls-report-modal-title"><?php esc_html_e( 'Report Key', 'licensesender' ); ?></h2>
					<button type="button" class="ls-report-modal-close" data-ls-report-close aria-label="<?php esc_attr_e( 'Close', 'licensesender' ); ?>">&times;</button>
				</div>
				<p class="ls-report-modal-lead"><?php esc_html_e( 'Report a dead or issue key to LicenseSender. Auto mode replaces immediately when stock allows.', 'licensesender' ); ?></p>
				<form id="ls-report-key-form" class="ls-report-modal-form">
					<input type="hidden" id="ls-report-license-id" name="license_id" value="" />
					<input type="hidden" id="ls-report-product-id" name="product_id" value="" />
					<div class="ls-report-field">
						<label for="ls-report-license-key"><?php esc_html_e( 'Current License', 'licensesender' ); ?></label>
						<input type="text" id="ls-report-license-key" readonly />
					</div>
					<div class="ls-report-field">
						<label for="ls-report-order"><?php esc_html_e( 'Order', 'licensesender' ); ?></label>
						<input type="text" id="ls-report-order" readonly />
					</div>
					<div class="ls-report-field">
						<label for="ls-report-product"><?php esc_html_e( 'Product', 'licensesender' ); ?></label>
						<input type="text" id="ls-report-product" readonly />
					</div>
					<div class="ls-report-field">
						<label for="ls-report-reason"><?php esc_html_e( 'Reason', 'licensesender' ); ?></label>
						<select id="ls-report-reason" name="reason">
							<option value="dead_key"><?php esc_html_e( 'Dead key', 'licensesender' ); ?></option>
							<option value="activation_failed"><?php esc_html_e( 'Activation failed', 'licensesender' ); ?></option>
							<option value="invalid_key"><?php esc_html_e( 'Invalid key', 'licensesender' ); ?></option>
							<option value="customer_request"><?php esc_html_e( 'Customer request', 'licensesender' ); ?></option>
							<option value="other"><?php esc_html_e( 'Other', 'licensesender' ); ?></option>
						</select>
					</div>
					<div class="ls-report-field">
						<label for="ls-report-mode"><?php esc_html_e( 'Mode', 'licensesender' ); ?></label>
						<select id="ls-report-mode" name="mode">
							<option value="auto"><?php esc_html_e( 'Auto (replace now if available)', 'licensesender' ); ?></option>
							<option value="manual"><?php esc_html_e( 'Manual (log issue for later)', 'licensesender' ); ?></option>
						</select>
					</div>
					<div class="ls-report-field">
						<label for="ls-report-notes"><?php esc_html_e( 'Notes (optional)', 'licensesender' ); ?></label>
						<textarea id="ls-report-notes" name="notes" rows="4" placeholder="<?php esc_attr_e( 'Customer reported activation failed', 'licensesender' ); ?>"></textarea>
					</div>
					<p class="ls-report-modal-message" aria-live="polite"></p>
					<div class="ls-report-modal-actions">
						<button type="button" class="button" data-ls-report-close><?php esc_html_e( 'Cancel', 'licensesender' ); ?></button>
						<button type="submit" class="button button-primary" id="ls-report-submit"><?php esc_html_e( 'Report Key', 'licensesender' ); ?></button>
					</div>
				</form>
			</div>
		</div>

		<input type="hidden" id="ls-order-license-nonce" value="<?php echo esc_attr( $nonce ); ?>" />
		<?php
	}

	public function print_inline_js() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return;
		}

		$allowed = array( 'shop_order', 'woocommerce_page_wc-orders' );
		if ( ! in_array( $screen->id, $allowed, true ) ) {
			return;
		}
		?>
		<script>
		(function ($) {
			const i18n = {
				fetching: <?php echo wp_json_encode( __( 'Fetching…', 'licensesender' ) ); ?>,
				getLicense: <?php echo wp_json_encode( __( 'Get License', 'licensesender' ) ); ?>,
				sending: <?php echo wp_json_encode( __( 'Sending…', 'licensesender' ) ); ?>,
				resend: <?php echo wp_json_encode( __( 'Resend License Email', 'licensesender' ) ); ?>,
				getAll: <?php echo wp_json_encode( __( 'Get All Keys', 'licensesender' ) ); ?>,
				processing: <?php echo wp_json_encode( __( 'Processing…', 'licensesender' ) ); ?>,
				report: <?php echo wp_json_encode( __( 'Report Key', 'licensesender' ) ); ?>,
				reporting: <?php echo wp_json_encode( __( 'Reporting…', 'licensesender' ) ); ?>
			};

			const $reportModal = $('#ls-report-key-modal');
			const $reportForm = $('#ls-report-key-form');
			const $reportMessage = $reportModal.find('.ls-report-modal-message');

			function openReportModal(data) {
				$('#ls-report-license-id').val(data.licenseId || '');
				$('#ls-report-product-id').val(data.productId || '');
				$('#ls-report-license-key').val(data.licenseKey || '');
				$('#ls-report-order').val(data.orderId ? '#' + data.orderId : '');
				$('#ls-report-product').val(data.productName || '—');
				$('#ls-report-reason').val('dead_key');
				$('#ls-report-mode').val('auto');
				$('#ls-report-notes').val('');
				$reportMessage.removeClass('is-error is-success').text('');
				$reportModal.removeAttr('hidden').addClass('is-open');
				$('body').addClass('ls-report-modal-open');
			}

			function closeReportModal() {
				$reportModal.attr('hidden', true).removeClass('is-open');
				$('body').removeClass('ls-report-modal-open');
			}

			$(document).on('click', '.ls-report-key-btn', function (event) {
				event.preventDefault();
				const $btn = $(this);
				openReportModal({
					licenseId: $btn.data('license-id'),
					licenseKey: $btn.data('license-key'),
					orderId: $btn.data('order-id'),
					productId: $btn.data('product-id'),
					productName: $btn.data('product-name')
				});
			});

			$(document).on('click', '[data-ls-report-close]', function () {
				closeReportModal();
			});

			$(document).on('keydown', function (event) {
				if (event.key === 'Escape' && $reportModal.hasClass('is-open')) {
					closeReportModal();
				}
			});

			$reportForm.on('submit', function (event) {
				event.preventDefault();
				const $submit = $('#ls-report-submit');
				const licenseId = $('#ls-report-license-id').val();
				const productId = $('#ls-report-product-id').val();
				const orderId = String($('#ls-report-order').val() || '').replace('#', '');

				$submit.prop('disabled', true).text(i18n.reporting);
				$reportMessage.removeClass('is-error is-success').text('');

				$.post(ajaxurl, {
					action: 'ls_admin_report_license',
					_ajax_nonce: getNonce(),
					license_id: licenseId,
					product_id: productId,
					order_id: orderId,
					reason: $('#ls-report-reason').val(),
					mode: $('#ls-report-mode').val(),
					notes: $('#ls-report-notes').val()
				})
				.done(function (res) {
					if (!res.success) {
						$reportMessage.addClass('is-error').text((res.data && res.data.message) || 'Failed to report key');
						return;
					}

					const replacement = res.data.report && res.data.report.replacement_key
						? res.data.report.replacement_key
						: '';
					const msg = replacement
						? ('Key replaced: ' + replacement)
						: (res.data.message || 'License reported successfully.');

					if (res.data.html && res.data.product_id) {
						const row = $('tr[data-product-row="' + res.data.product_id + '"]');
						row.find('.ls-license-cell').html(res.data.html);
						if (res.data.progress_html) {
							row.find('.ls-product-progress').replaceWith(res.data.progress_html);
						}
					}
					if (res.data.keys_progress) {
						$('#ls-keys-progress-text').text(res.data.keys_progress);
					}

					$reportMessage.addClass('is-success').text(msg);
					window.setTimeout(closeReportModal, 900);
				})
				.fail(function () {
					$reportMessage.addClass('is-error').text('AJAX request failed');
				})
				.always(function () {
					$submit.prop('disabled', false).text(i18n.report);
				});
			});

			function getNonce() {
				return $('#ls-order-license-nonce').val() || '<?php echo esc_js( wp_create_nonce( 'ls_fetch_license_api' ) ); ?>';
			}

			function updateSummary(data) {
				if (data.keys_progress) {
					$('#ls-keys-progress-text').text(data.keys_progress);
				}
				if (data.email_status) {
					$('#ls-email-status-wrap').html(data.email_status);
				}
				if (data.all_complete) {
					$('#ls-admin-get-all-keys').prop('disabled', true);
				}
				if (data.can_resend) {
					$('#ls-admin-resend-email').prop('disabled', false);
				}
			}

			function fetchLicense(btn) {
				const row = btn.closest('tr');
				const deferred = $.Deferred();

				btn.prop('disabled', true).text(i18n.fetching);

				$.post(ajaxurl, {
					action: 'ls_admin_fetch_license',
					_ajax_nonce: getNonce(),
					order_id: btn.data('order-id'),
					product_id: btn.data('product-id'),
					email: btn.data('email'),
					qnty: btn.data('qnty')
				})
				.done(function (res) {
					if (res.success) {
						if (res.data.html) {
							row.find('.ls-license-cell').html(res.data.html);
						}
						if (res.data.progress_html) {
							row.find('.ls-product-progress').replaceWith(res.data.progress_html);
						}
						if (res.data.action_html) {
							row.find('.ls-license-action-cell').html(res.data.action_html);
						}
						updateSummary(res.data);
						deferred.resolve(res);
					} else {
						alert(res.data && res.data.message ? res.data.message : 'Failed to fetch license');
						btn.prop('disabled', false).text(i18n.getLicense);
						deferred.reject(res);
					}
				})
				.fail(function () {
					alert('AJAX request failed');
					btn.prop('disabled', false).text(i18n.getLicense);
					deferred.reject();
				});

				return deferred.promise();
			}

			$(document).on('click', '.ls-get-license', function () {
				fetchLicense($(this));
			});

			$('#ls-admin-resend-email').on('click', function () {
				const btn = $(this);
				const orderId = btn.data('order-id');
				btn.prop('disabled', true).text(i18n.sending);

				$.post(ajaxurl, {
					action: 'ls_admin_resend_license_email',
					_ajax_nonce: getNonce(),
					order_id: orderId
				})
				.done(function (res) {
					if (res.success) {
						if (res.data.email_status) {
							$('#ls-email-status-wrap').html(res.data.email_status);
						}
						alert(res.data.message || 'Sent.');
					} else {
						alert(res.data && res.data.message ? res.data.message : 'Failed to send email');
					}
				})
				.fail(function () {
					alert('AJAX request failed');
				})
				.always(function () {
					btn.prop('disabled', false).text(i18n.resend);
				});
			});

			$('#ls-admin-get-all-keys').on('click', function () {
				const btn = $(this);
				const buttons = $('.ls-get-license:enabled');
				if (!buttons.length) return;

				btn.prop('disabled', true).text(i18n.processing);

				let chain = $.Deferred().resolve();
				buttons.each(function () {
					const fetchBtn = $(this);
					chain = chain.then(function () {
						return fetchLicense(fetchBtn);
					});
				});

				chain.always(function () {
					btn.text(i18n.getAll);
					if ($('.ls-get-license:enabled').length === 0) {
						btn.prop('disabled', true);
					} else {
						btn.prop('disabled', false);
					}
				});
			});
		})(jQuery);
		</script>
		<?php
	}
}

new Licensesender_Admin_MetaBoxes();
