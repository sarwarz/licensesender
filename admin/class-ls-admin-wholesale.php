<?php
/**
 * Admin wholesale applications.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Admin_Wholesale {

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( LS_Admin_Service::uses_react_admin() ) {
			LS_Admin_Assets::render_shell( 'wholesale' );
			return;
		}

		$status_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$applications  = LS_Wholesale::list_applications( $status_filter );
		$catalog       = LS_Wholesale::get_catalog();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Wholesale Applications', 'licensesender' ); ?></h1>

			<div class="ls-wholesale-admin-stats" style="display:flex;gap:16px;margin:16px 0;">
				<div class="postbox" style="padding:12px 16px;min-width:220px;">
					<strong><?php esc_html_e( 'Catalog tier', 'licensesender' ); ?>:</strong>
					<?php echo esc_html( ! empty( $catalog['tier'] ) ? $catalog['tier'] : '—' ); ?>
				</div>
				<div class="postbox" style="padding:12px 16px;min-width:220px;">
					<strong><?php esc_html_e( 'Wholesale products', 'licensesender' ); ?>:</strong>
					<?php echo esc_html( ! empty( $catalog['count'] ) ? (string) $catalog['count'] : '0' ); ?>
				</div>
			</div>

			<ul class="subsubsub">
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=ls-wholesale-applications' ) ); ?>" <?php echo $status_filter === '' ? 'class="current"' : ''; ?>><?php esc_html_e( 'All', 'licensesender' ); ?></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=ls-wholesale-applications&status=pending' ) ); ?>" <?php echo $status_filter === 'pending' ? 'class="current"' : ''; ?>><?php esc_html_e( 'Pending', 'licensesender' ); ?></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=ls-wholesale-applications&status=approved' ) ); ?>" <?php echo $status_filter === 'approved' ? 'class="current"' : ''; ?>><?php esc_html_e( 'Approved', 'licensesender' ); ?></a> |</li>
				<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=ls-wholesale-applications&status=rejected' ) ); ?>" <?php echo $status_filter === 'rejected' ? 'class="current"' : ''; ?>><?php esc_html_e( 'Rejected', 'licensesender' ); ?></a></li>
			</ul>

			<table class="widefat striped" style="margin-top:12px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Applicant', 'licensesender' ); ?></th>
						<th><?php esc_html_e( 'Company', 'licensesender' ); ?></th>
						<th><?php esc_html_e( 'Email', 'licensesender' ); ?></th>
						<th><?php esc_html_e( 'Phone', 'licensesender' ); ?></th>
						<th><?php esc_html_e( 'Status', 'licensesender' ); ?></th>
						<th><?php esc_html_e( 'Submitted', 'licensesender' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'licensesender' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $applications ) ) : ?>
						<tr><td colspan="7"><?php esc_html_e( 'No applications found.', 'licensesender' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $applications as $row ) : ?>
							<?php $user = get_userdata( (int) $row['user_id'] ); ?>
							<tr>
								<td>
									<?php if ( $user ) : ?>
										<a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>"><?php echo esc_html( $user->display_name ); ?></a>
									<?php else : ?>
										<?php esc_html_e( '(Deleted user)', 'licensesender' ); ?>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $row['company_name'] ); ?></td>
								<td><a href="mailto:<?php echo esc_attr( $row['business_email'] ); ?>"><?php echo esc_html( $row['business_email'] ); ?></a></td>
								<td><?php echo esc_html( $row['phone'] ); ?></td>
								<td><span class="ls-wholesale-status ls-wholesale-status-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( ucfirst( $row['status'] ) ); ?></span></td>
								<td><?php echo esc_html( $row['created_at'] ); ?></td>
								<td>
									<?php if ( $row['status'] === 'pending' ) : ?>
										<p class="description"><?php esc_html_e( 'Use the React admin UI to review applications.', 'licensesender' ); ?></p>
									<?php else : ?>
										<?php echo $row['admin_note'] ? esc_html( $row['admin_note'] ) : '—'; ?>
									<?php endif; ?>
									<?php if ( ! empty( $row['message'] ) ) : ?>
										<details style="margin-top:6px;"><summary><?php esc_html_e( 'Message', 'licensesender' ); ?></summary><p><?php echo esc_html( $row['message'] ); ?></p></details>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<style>
			.ls-wholesale-status { padding:2px 8px;border-radius:999px;font-size:12px;font-weight:600; }
			.ls-wholesale-status-pending { background:#fef3c7;color:#92400e; }
			.ls-wholesale-status-approved { background:#d1fae5;color:#065f46; }
			.ls-wholesale-status-rejected { background:#fee2e2;color:#991b1b; }
		</style>
		<?php
	}
}
