<?php
/**
 * REST API for React admin UI.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Admin_REST {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		$namespace = 'licensesender/v1';

		register_rest_route(
			$namespace,
			'/dashboard',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_dashboard' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_settings' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_settings' ),
					'permission_callback' => array( __CLASS__, 'can_manage_options' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/settings/ping',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'ping_api' ),
				'permission_callback' => array( __CLASS__, 'can_manage_options' ),
			)
		);

		register_rest_route(
			$namespace,
			'/settings/subscription',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_subscription' ),
				'permission_callback' => array( __CLASS__, 'can_manage_options' ),
				'args'                => array(
					'refresh' => array(
						'type'              => 'boolean',
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/settings/test-email',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'test_email' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/licenses/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'license_stats' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/licenses',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_licenses' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/licenses/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_license' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_license' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/licenses/(?P<id>\d+)/report',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'report_license' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/licenses/sync-order',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'sync_order_licenses' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'order_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'force'    => array(
						'type'              => 'boolean',
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/licenses/fetch-by-sku',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'fetch_by_sku' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/download-links',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_download_links' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'create_download_link' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/download-links/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_download_link' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_download_link' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_download_link' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/activation-guides',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_activation_guides' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/activation-guides/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_activation_guide' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_activation_guide' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/activation-guides/save',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'save_activation_guide' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/products/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'search_products' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/wholesale/applications',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_wholesale_applications' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'status' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/wholesale/catalog-summary',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_wholesale_catalog_summary' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'refresh' => array(
						'type'              => 'boolean',
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/wholesale/generate-pages',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'generate_wholesale_pages' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/support/generate-pages',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'generate_support_pages' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/wholesale/applications/bulk-delete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'bulk_delete_wholesale_applications' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/wholesale/applications/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_wholesale_application' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/wholesale/applications/(?P<id>\d+)/review',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'review_wholesale_application' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/setup',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_setup' ),
					'permission_callback' => array( __CLASS__, 'can_manage_options' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save_setup' ),
					'permission_callback' => array( __CLASS__, 'can_manage_options' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/setup/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'complete_setup' ),
				'permission_callback' => array( __CLASS__, 'can_manage_options' ),
			)
		);

		register_rest_route(
			$namespace,
			'/feature-requests',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'submit_feature_request' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'title'   => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'message' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/orders/backfill',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_order_backfill_status' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/orders/backfill',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'start_order_backfill' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);
	}

	public static function can_manage() {
		return LS_Admin_Service::can_manage();
	}

	public static function can_manage_options() {
		return current_user_can( 'manage_options' );
	}

	public static function get_settings( WP_REST_Request $request ) {
		$tab = $request->get_param( 'tab' ) ?: 'general';
		return rest_ensure_response( LS_Admin_Service::get_settings( $tab ) );
	}

	public static function update_settings( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$tab = $request->get_param( 'tab' ) ?: ( $data['tab'] ?? 'general' );
		unset( $data['tab'] );

		$result = LS_Admin_Service::save_settings( $tab, $data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'message'  => __( 'Settings saved successfully.', 'licensesender' ),
				'settings' => $result,
			)
		);
	}

	public static function ping_api() {
		$result = LS_Admin_Service::ping_api();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	public static function get_subscription( WP_REST_Request $request ) {
		$force   = rest_sanitize_boolean( $request->get_param( 'refresh' ) );
		$result  = LS_Admin_Service::get_subscription_details( $force );
		$success = ! empty( $result['success'] );

		if ( ! $success ) {
			return new WP_Error(
				'subscription_fetch_failed',
				$result['message'] ?? __( 'Unable to load subscription details.', 'licensesender' ),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response( $result );
	}

	public static function test_email( WP_REST_Request $request ) {
		$email = sanitize_email( $request->get_param( 'email' ) );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Invalid email address.', 'licensesender' ), array( 'status' => 400 ) );
		}

		$mode = sanitize_text_field( $request->get_param( 'mode' ) ?: 'bulk' );
		if ( ! in_array( $mode, array( 'single', 'bulk' ), true ) ) {
			$mode = 'bulk';
		}

		$sent = LS_License_Email_Service::send_test_email( $email, $mode );
		if ( ! $sent ) {
			return new WP_Error( 'send_failed', __( 'Failed to send test email.', 'licensesender' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'message' => __( 'Test email sent successfully.', 'licensesender' ) ) );
	}

	public static function license_stats() {
		return rest_ensure_response( LS_Admin_Service::get_license_stats() );
	}

	public static function get_dashboard() {
		return rest_ensure_response( LS_Admin_Service::get_dashboard_data() );
	}

	public static function get_setup() {
		return rest_ensure_response( LS_Admin_Setup::get_status() );
	}

	public static function save_setup( WP_REST_Request $request ) {
		$data   = $request->get_json_params();
		$result = LS_Admin_Setup::save_step( is_array( $data ) ? $data : array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	public static function complete_setup( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		if ( is_array( $data ) && ! empty( $data ) ) {
			LS_Admin_Setup::save_step( $data );
		}

		LS_Admin_Setup::mark_complete();

		return rest_ensure_response(
			array_merge(
				LS_Admin_Setup::get_status(),
				array(
					'message' => __( 'Setup completed.', 'licensesender' ),
				)
			)
		);
	}

	public static function submit_feature_request( WP_REST_Request $request ) {
		$title   = trim( (string) $request->get_param( 'title' ) );
		$message = trim( (string) $request->get_param( 'message' ) );

		if ( $title === '' || $message === '' ) {
			return new WP_Error(
				'missing_fields',
				__( 'Please enter a title and description for your feature request.', 'licensesender' ),
				array( 'status' => 400 )
			);
		}

		$result = Licensesender_API::submit_feature_request(
			array(
				'title'   => $title,
				'message' => $message,
			)
		);

		if ( empty( $result['success'] ) ) {
			return new WP_Error(
				'feature_request_failed',
				(string) ( $result['message'] ?? __( 'Could not submit feature request.', 'licensesender' ) ),
				array( 'status' => ! empty( $result['http_code'] ) ? (int) $result['http_code'] : 502 )
			);
		}

		return rest_ensure_response(
			array(
				'message' => __( 'Feature request submitted. Thank you!', 'licensesender' ),
				'data'    => $result['data'] ?? array(),
			)
		);
	}

	public static function get_order_backfill_status( WP_REST_Request $request ) {
		if ( ! class_exists( 'LS_Order_Push' ) ) {
			return new WP_Error(
				'unavailable',
				__( 'Order sync is not available.', 'licensesender' ),
				array( 'status' => 503 )
			);
		}

		return rest_ensure_response( LS_Order_Push::get_backfill_status() );
	}

	public static function start_order_backfill( WP_REST_Request $request ) {
		if ( ! class_exists( 'LS_Order_Push' ) ) {
			return new WP_Error(
				'unavailable',
				__( 'Order sync is not available.', 'licensesender' ),
				array( 'status' => 503 )
			);
		}

		$result = LS_Order_Push::start_backfill();

		if ( empty( $result['success'] ) ) {
			return new WP_Error(
				'backfill_failed',
				(string) ( $result['message'] ?? __( 'Could not start order backfill.', 'licensesender' ) ),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response( $result );
	}

	public static function list_licenses( WP_REST_Request $request ) {
		return rest_ensure_response(
			LS_Admin_Service::list_licenses(
				array(
					'page'     => $request->get_param( 'page' ),
					'per_page' => $request->get_param( 'per_page' ),
					'search'   => $request->get_param( 'search' ),
					'orderby'  => $request->get_param( 'orderby' ),
					'order'    => $request->get_param( 'order' ),
				)
			)
		);
	}

	public static function get_license( WP_REST_Request $request ) {
		$id  = (int) $request['id'];
		$row = LS_Admin_Service::get_license( $id );
		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'License not found.', 'licensesender' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $row );
	}

	public static function update_license( WP_REST_Request $request ) {
		$id   = (int) $request['id'];
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( ! LS_Admin_Service::update_license( $id, $data ) ) {
			return new WP_Error( 'update_failed', __( 'Failed to update license.', 'licensesender' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'message' => __( 'License updated successfully.', 'licensesender' ),
				'license' => LS_Admin_Service::get_license( $id ),
			)
		);
	}

	public static function report_license( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$params = $request->get_json_params();
		$result = LS_Admin_Service::report_license( $id, is_array( $params ) ? $params : array() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'message' => (string) ( $result['message'] ?? __( 'License reported successfully.', 'licensesender' ) ),
				'license' => $result['license'] ?? null,
				'report'  => $result['report'] ?? array(),
			)
		);
	}

	public static function sync_order_licenses( WP_REST_Request $request ) {
		$params   = $request->get_json_params();
		$order_id = 0;
		$force    = false;

		if ( is_array( $params ) ) {
			$order_id = (int) ( $params['order_id'] ?? 0 );
			$force    = ! empty( $params['force'] );
		}

		if ( ! $order_id ) {
			$order_id = (int) $request->get_param( 'order_id' );
		}
		if ( ! $force ) {
			$force = rest_sanitize_boolean( $request->get_param( 'force' ) );
		}

		if ( ! $order_id ) {
			return new WP_Error( 'missing_order', __( 'Missing order ID.', 'licensesender' ), array( 'status' => 400 ) );
		}

		$result = LS_Admin_Service::sync_order_licenses( $order_id, $force );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'message'  => (string) ( $result['message'] ?? __( 'Licenses synced.', 'licensesender' ) ),
				'updated'  => (int) ( $result['updated'] ?? 0 ),
				'inserted' => (int) ( $result['inserted'] ?? 0 ),
				'skipped'  => ! empty( $result['skipped'] ),
			)
		);
	}

	public static function fetch_by_sku( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$sku    = is_array( $params ) ? ( $params['sku'] ?? '' ) : '';
		$result = LS_Admin_Service::fetch_license_by_sku( $sku, $params ?: array() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	public static function list_download_links() {
		return rest_ensure_response( array( 'items' => LS_Admin_Service::list_download_links() ) );
	}

	public static function get_download_link( WP_REST_Request $request ) {
		$row = LS_Admin_Service::get_download_link( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'Record not found.', 'licensesender' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $row );
	}

	public static function create_download_link( WP_REST_Request $request ) {
		$data   = $request->get_json_params();
		$result = LS_Admin_Service::save_download_link( is_array( $data ) ? $data : array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'message' => __( 'Added successfully.', 'licensesender' ), 'item' => $result ) );
	}

	public static function update_download_link( WP_REST_Request $request ) {
		$data         = $request->get_json_params();
		$data['id']   = (int) $request['id'];
		$result       = LS_Admin_Service::save_download_link( is_array( $data ) ? $data : array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'message' => __( 'Updated successfully.', 'licensesender' ), 'item' => $result ) );
	}

	public static function delete_download_link( WP_REST_Request $request ) {
		if ( ! LS_Admin_Service::delete_download_link( (int) $request['id'] ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete record.', 'licensesender' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'message' => __( 'Deleted successfully.', 'licensesender' ) ) );
	}

	public static function list_activation_guides() {
		return rest_ensure_response( array( 'items' => LS_Admin_Service::list_activation_guides() ) );
	}

	public static function get_activation_guide( WP_REST_Request $request ) {
		$row = LS_Admin_Service::get_activation_guide( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'Activation guide not found.', 'licensesender' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $row );
	}

	public static function delete_activation_guide( WP_REST_Request $request ) {
		if ( ! LS_Admin_Service::delete_activation_guide( (int) $request['id'] ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete activation guide.', 'licensesender' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'message' => __( 'Activation guide deleted successfully.', 'licensesender' ) ) );
	}

	public static function save_activation_guide( WP_REST_Request $request ) {
		unset( $request );
		return new WP_Error(
			'use_admin_ajax',
			__( 'Activation guide saves with file uploads must use the admin UI (admin-ajax).', 'licensesender' ),
			array( 'status' => 501 )
		);
	}

	public static function search_products( WP_REST_Request $request ) {
		$q = $request->get_param( 'q' ) ?: '';
		return rest_ensure_response( array( 'items' => LS_Admin_Service::search_products( $q ) ) );
	}

	public static function list_wholesale_applications( WP_REST_Request $request ) {
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		$rows   = LS_Wholesale::list_applications( $status );
		$items  = array_map( array( __CLASS__, 'format_wholesale_application' ), $rows );

		return rest_ensure_response( array( 'items' => $items ) );
	}

	public static function generate_wholesale_pages( WP_REST_Request $request ) {
		unset( $request );

		$result = LS_Admin_Service::generate_wholesale_pages();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	public static function generate_support_pages( WP_REST_Request $request ) {
		unset( $request );

		$result = LS_Admin_Service::generate_support_pages();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	public static function get_wholesale_catalog_summary( WP_REST_Request $request ) {
		$refresh = rest_sanitize_boolean( $request->get_param( 'refresh' ) );
		if ( $refresh ) {
			LS_Wholesale::clear_catalog_cache();
		}

		$catalog = LS_Wholesale::get_catalog( $refresh );

		return rest_ensure_response(
			array(
				'tier'    => ! empty( $catalog['tier'] ) ? (string) $catalog['tier'] : 'default',
				'count'   => isset( $catalog['count'] ) ? (int) $catalog['count'] : count( $catalog['products'] ?? array() ),
				'message' => (string) ( $catalog['message'] ?? '' ),
				'success' => ! empty( $catalog['success'] ),
			)
		);
	}

	public static function review_wholesale_application( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		$note   = sanitize_textarea_field( (string) $request->get_param( 'admin_note' ) );

		$result = LS_Wholesale::review_application( $id, $status, $note );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'message' => __( 'Application updated.', 'licensesender' ),
				'item'    => self::format_wholesale_application( LS_Wholesale::get_application( $id ) ),
			)
		);
	}

	public static function delete_wholesale_application( WP_REST_Request $request ) {
		$result = LS_Wholesale::delete_application( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'message' => __( 'Application deleted.', 'licensesender' ),
			)
		);
	}

	public static function bulk_delete_wholesale_applications( WP_REST_Request $request ) {
		$ids = $request->get_param( 'ids' );
		if ( ! is_array( $ids ) ) {
			return new WP_Error( 'invalid_ids', __( 'No applications selected.', 'licensesender' ), array( 'status' => 400 ) );
		}

		$result = LS_Wholesale::delete_applications( $ids );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'message' => sprintf(
					/* translators: %d: number of deleted applications */
					_n( '%d application deleted.', '%d applications deleted.', (int) $result, 'licensesender' ),
					(int) $result
				),
				'deleted' => (int) $result,
			)
		);
	}

	private static function format_wholesale_application( $row ) {
		if ( ! is_array( $row ) ) {
			return array();
		}

		$user = get_userdata( (int) $row['user_id'] );

		return array(
			'id'              => (int) $row['id'],
			'user_id'         => (int) $row['user_id'],
			'applicant_name'  => $user ? $user->display_name : '',
			'user_edit_url'   => $user ? get_edit_user_link( $user->ID, '' ) : '',
			'company_name'    => (string) $row['company_name'],
			'business_email'  => (string) $row['business_email'],
			'phone'           => (string) ( $row['phone'] ?? '' ),
			'messenger_link'  => (string) ( $row['messenger_link'] ?? '' ),
			'website'         => (string) ( $row['website'] ?? '' ),
			'tax_id'          => (string) ( $row['tax_id'] ?? '' ),
			'message'         => (string) ( $row['message'] ?? '' ),
			'status'          => (string) $row['status'],
			'admin_note'      => (string) ( $row['admin_note'] ?? '' ),
			'created_at'      => (string) $row['created_at'],
			'reviewed_at'     => (string) ( $row['reviewed_at'] ?? '' ),
		);
	}
}

LS_Admin_REST::init();
