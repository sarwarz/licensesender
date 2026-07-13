<?php
/**
 * Admin dashboard page.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Admin_Dashboard {

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( LS_Admin_Service::uses_react_admin() ) {
			LS_Admin_Assets::render_shell( 'dashboard' );
			return;
		}

		$data = LS_Admin_Service::get_dashboard_data();
		$stats = $data['stats'] ?? array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Dashboard', 'licensesender' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Overview of license delivery and store activity.', 'licensesender' ); ?></p>

			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin:20px 0;">
				<div class="postbox" style="padding:16px;margin:0;">
					<strong><?php esc_html_e( 'Total Keys', 'licensesender' ); ?></strong>
					<p style="font-size:28px;margin:8px 0 0;"><?php echo esc_html( (string) ( $stats['total'] ?? 0 ) ); ?></p>
				</div>
				<div class="postbox" style="padding:16px;margin:0;">
					<strong><?php esc_html_e( 'Orders', 'licensesender' ); ?></strong>
					<p style="font-size:28px;margin:8px 0 0;"><?php echo esc_html( (string) ( $stats['orders'] ?? 0 ) ); ?></p>
				</div>
				<div class="postbox" style="padding:16px;margin:0;">
					<strong><?php esc_html_e( 'Today', 'licensesender' ); ?></strong>
					<p style="font-size:28px;margin:8px 0 0;"><?php echo esc_html( (string) ( $stats['today'] ?? 0 ) ); ?></p>
				</div>
				<div class="postbox" style="padding:16px;margin:0;">
					<strong><?php esc_html_e( 'Pending Wholesale', 'licensesender' ); ?></strong>
					<p style="font-size:28px;margin:8px 0 0;"><?php echo esc_html( (string) ( $stats['wholesale_pending'] ?? 0 ) ); ?></p>
				</div>
			</div>

			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=ls-licensesender' ) ); ?>">
					<?php esc_html_e( 'View License Keys', 'licensesender' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=ls-licensesender-settings' ) ); ?>">
					<?php esc_html_e( 'Settings', 'licensesender' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}
