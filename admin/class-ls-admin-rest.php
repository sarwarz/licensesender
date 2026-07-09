<?php
/**
 * REST API for React admin UI.
 *
 * @package License_Shipper
 */

defined( 'ABSPATH' ) || exit;

class LS_Admin_REST {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		$namespace = 'license-shipper/v1';

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
			'/licenses/(?P<id>\d+)/change',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'change_license' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
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
				'message'  => __( 'Settings saved successfully.', 'license-shipper' ),
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

	public static function test_email( WP_REST_Request $request ) {
		$email = sanitize_email( $request->get_param( 'email' ) );
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Invalid email address.', 'license-shipper' ), array( 'status' => 400 ) );
		}

		$mode = sanitize_text_field( $request->get_param( 'mode' ) ?: 'bulk' );
		if ( ! in_array( $mode, array( 'single', 'bulk' ), true ) ) {
			$mode = 'bulk';
		}

		$sent = LS_License_Email_Service::send_test_email( $email, $mode );
		if ( ! $sent ) {
			return new WP_Error( 'send_failed', __( 'Failed to send test email.', 'license-shipper' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'message' => __( 'Test email sent successfully.', 'license-shipper' ) ) );
	}

	public static function license_stats() {
		return rest_ensure_response( LS_Admin_Service::get_license_stats() );
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
			return new WP_Error( 'not_found', __( 'License not found.', 'license-shipper' ), array( 'status' => 404 ) );
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
			return new WP_Error( 'update_failed', __( 'Failed to update license.', 'license-shipper' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'message' => __( 'License updated successfully.', 'license-shipper' ),
				'license' => LS_Admin_Service::get_license( $id ),
			)
		);
	}

	public static function change_license( WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$data   = $request->get_json_params();
		$result = LS_Admin_Service::change_license( $id, is_array( $data ) ? $data : array() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'message' => __( 'License changed successfully.', 'license-shipper' ),
				'license' => $result,
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
			return new WP_Error( 'not_found', __( 'Record not found.', 'license-shipper' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $row );
	}

	public static function create_download_link( WP_REST_Request $request ) {
		$data   = $request->get_json_params();
		$result = LS_Admin_Service::save_download_link( is_array( $data ) ? $data : array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'message' => __( 'Added successfully.', 'license-shipper' ), 'item' => $result ) );
	}

	public static function update_download_link( WP_REST_Request $request ) {
		$data         = $request->get_json_params();
		$data['id']   = (int) $request['id'];
		$result       = LS_Admin_Service::save_download_link( is_array( $data ) ? $data : array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'message' => __( 'Updated successfully.', 'license-shipper' ), 'item' => $result ) );
	}

	public static function delete_download_link( WP_REST_Request $request ) {
		if ( ! LS_Admin_Service::delete_download_link( (int) $request['id'] ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete record.', 'license-shipper' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'message' => __( 'Deleted successfully.', 'license-shipper' ) ) );
	}

	public static function list_activation_guides() {
		return rest_ensure_response( array( 'items' => LS_Admin_Service::list_activation_guides() ) );
	}

	public static function get_activation_guide( WP_REST_Request $request ) {
		$row = LS_Admin_Service::get_activation_guide( (int) $request['id'] );
		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'Activation guide not found.', 'license-shipper' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $row );
	}

	public static function delete_activation_guide( WP_REST_Request $request ) {
		if ( ! LS_Admin_Service::delete_activation_guide( (int) $request['id'] ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete activation guide.', 'license-shipper' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'message' => __( 'Activation guide deleted successfully.', 'license-shipper' ) ) );
	}

	public static function save_activation_guide( WP_REST_Request $request ) {
		unset( $request );
		return new WP_Error(
			'use_admin_ajax',
			__( 'Activation guide saves with file uploads must use the admin UI (admin-ajax).', 'license-shipper' ),
			array( 'status' => 501 )
		);
	}

	public static function search_products( WP_REST_Request $request ) {
		$q = $request->get_param( 'q' ) ?: '';
		return rest_ensure_response( array( 'items' => LS_Admin_Service::search_products( $q ) ) );
	}
}

LS_Admin_REST::init();
