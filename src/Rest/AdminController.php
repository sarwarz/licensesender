<?php

namespace LicenseSender\Rest;

use LicenseSender\Admin\AdminService;
use LicenseSender\Core\Api;
use LicenseSender\Licenses\LicenseEmailService;
use LicenseSender\Orders\OrderPush;
use LicenseSender\Admin\Pages\SetupPage;
use LicenseSender\Wholesale\Wholesale;

defined( 'ABSPATH' ) || exit;

class AdminController {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		$namespace = 'licensesender/v1';

		register_rest_route(
			$namespace,
			'/dashboard',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_dashboard' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_settings' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_settings' ),
					'permission_callback' => array( __CLASS__, 'can_manage_options' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/settings/ping',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'ping_api' ),
				'permission_callback' => array( __CLASS__, 'can_manage_options' ),
			)
		);

		register_rest_route(
			$namespace,
			'/settings/subscription',
			array(
				'methods'             => \WP_REST_Server::READABLE,
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
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'test_email' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/settings/chat-status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => static function () {
					return rest_ensure_response( \LicenseSender\Chat\Chat::status() );
				},
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/settings/email-app-status',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'email_app_status' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/licenses/stats',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'license_stats' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/licenses/products',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'license_products' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/licenses/export',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'export_licenses' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/licenses/bulk',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'bulk_licenses' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'action' => array(
						'required' => true,
						'type'     => 'string',
						'enum'     => array( 'resend_email', 'sync' ),
					),
					'ids'    => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/licenses',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_licenses' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/licenses/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_license' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_license' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/licenses/(?P<id>\d+)/report',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'report_license' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/licenses/sync-order',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
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
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'fetch_by_sku' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		foreach ( array( 'download-links' => 'ls_download_links', 'activation-guides' => 'ls_activation_guides' ) as $base => $table ) {
			register_rest_route(
				$namespace,
				'/' . $base . '/coverage',
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => static function () use ( $table ) {
						return rest_ensure_response( AdminService::coverage( $table ) );
					},
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				)
			);
			register_rest_route(
				$namespace,
				'/' . $base . '/bulk-delete',
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => static function ( \WP_REST_Request $request ) use ( $table ) {
						$deleted = AdminService::bulk_delete( $table, (array) $request->get_param( 'ids' ) );
						/* translators: %d: number of deleted items */
						return rest_ensure_response( array( 'deleted' => $deleted, 'message' => sprintf( _n( '%d item deleted.', '%d items deleted.', $deleted, 'licensesender' ), $deleted ) ) );
					},
					'permission_callback' => array( __CLASS__, 'can_manage' ),
					'args'                => array(
						'ids' => array(
							'required' => true,
							'type'     => 'array',
							'items'    => array( 'type' => 'integer' ),
						),
					),
				)
			);
		}

		register_rest_route(
			$namespace,
			'/download-links/check',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => static function ( \WP_REST_Request $request ) {
					return rest_ensure_response( array( 'items' => AdminService::check_download_links( (array) $request->get_param( 'ids' ) ) ) );
				},
				'permission_callback' => array( __CLASS__, 'can_manage' ),
				'args'                => array(
					'ids' => array(
						'required' => true,
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/download-links',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_download_links' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
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
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_download_link' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_download_link' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_download_link' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/activation-guides',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_activation_guides' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/activation-guides/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_activation_guide' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_activation_guide' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/activation-guides/save',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'save_activation_guide' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/products/search',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'search_products' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/wholesale/applications',
			array(
				'methods'             => \WP_REST_Server::READABLE,
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
				'methods'             => \WP_REST_Server::READABLE,
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
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'generate_wholesale_pages' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/support/generate-pages',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'generate_support_pages' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/wholesale/applications/bulk-delete',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'bulk_delete_wholesale_applications' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/wholesale/applications/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'delete_wholesale_application' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/wholesale/groups',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_wholesale_groups' ),
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
			'/wholesale/applications/(?P<id>\d+)/group',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'set_wholesale_application_group' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/wholesale/unmapped',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'list_unmapped_wholesale_skus' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'map_wholesale_sku' ),
					'permission_callback' => array( __CLASS__, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/wholesale/applications/(?P<id>\d+)/review',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'review_wholesale_application' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/setup',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_setup' ),
					'permission_callback' => array( __CLASS__, 'can_manage_options' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'save_setup' ),
					'permission_callback' => array( __CLASS__, 'can_manage_options' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/setup/complete',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'complete_setup' ),
				'permission_callback' => array( __CLASS__, 'can_manage_options' ),
			)
		);

		register_rest_route(
			$namespace,
			'/feature-requests',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
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
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_order_backfill_status' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);

		register_rest_route(
			$namespace,
			'/orders/backfill',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'start_order_backfill' ),
				'permission_callback' => array( __CLASS__, 'can_manage' ),
			)
		);
	}

	public static function can_manage() {
		return AdminService::can_manage();
	}

	public static function can_manage_options() {
		return current_user_can( 'manage_options' );
	}

	public static function get_settings( \WP_REST_Request $request ) {
		$tab = $request->get_param( 'tab' ) ?: 'general';

		// The api/advance tabs return credentials (SaaS API key,
		// webhook secret). Writing them already requires manage_options, so
		// reading them must too — shop managers must not see these secrets.
		if ( in_array( $tab, array( 'api', 'advance' ), true ) && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You are not allowed to view these settings.', 'licensesender' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return rest_ensure_response( AdminService::get_settings( $tab ) );
	}

	public static function update_settings( \WP_REST_Request $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$tab = $request->get_param( 'tab' ) ?: ( $data['tab'] ?? 'general' );
		unset( $data['tab'] );

		$result = AdminService::save_settings( $tab, $data );
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
		$result = AdminService::ping_api();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	public static function get_subscription( \WP_REST_Request $request ) {
		$force   = rest_sanitize_boolean( $request->get_param( 'refresh' ) );
		$result  = AdminService::get_subscription_details( $force );
		$success = ! empty( $result['success'] );

		if ( ! $success ) {
			return new \WP_Error(
				'subscription_fetch_failed',
				$result['message'] ?? __( 'Unable to load subscription details.', 'licensesender' ),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response( $result );
	}

	public static function test_email( \WP_REST_Request $request ) {
		$email = sanitize_email( $request->get_param( 'email' ) );
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', __( 'Invalid email address.', 'licensesender' ), array( 'status' => 400 ) );
		}

		$sent = LicenseEmailService::send_test_email( $email );
		if ( ! $sent ) {
			return new \WP_Error( 'send_failed', __( 'WordPress could not send the test email. Check your mail settings (an SMTP plugin is recommended).', 'licensesender' ), array( 'status' => 500 ) );
		}

		/* translators: %s: email address */
		return rest_ensure_response( array( 'message' => sprintf( __( 'Test email sent to %s.', 'licensesender' ), $email ) ) );
	}

	/**
	 * Whether the LicenseSender app can email customers for this store.
	 */
	public static function email_app_status() {
		$status = Api::mail_status();

		return rest_ensure_response(
			array(
				'reachable' => $status['success'],
				'ready'     => $status['ready'],
				'message'   => $status['success'] ? $status['message'] : __( 'Could not reach LicenseSender. Check the API key on the API tab.', 'licensesender' ),
				'shop'      => $status['shop'],
				'from'      => $status['from'],
			)
		);
	}

	public static function license_stats() {
		return rest_ensure_response( AdminService::get_license_stats() );
	}

	public static function get_dashboard() {
		return rest_ensure_response( AdminService::get_dashboard_data() );
	}

	public static function get_setup() {
		return rest_ensure_response( SetupPage::get_status() );
	}

	public static function save_setup( \WP_REST_Request $request ) {
		$data   = $request->get_json_params();
		$result = SetupPage::save_step( is_array( $data ) ? $data : array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	public static function complete_setup( \WP_REST_Request $request ) {
		$data = $request->get_json_params();
		if ( is_array( $data ) && ! empty( $data ) ) {
			SetupPage::save_step( $data );
		}

		SetupPage::mark_complete();

		return rest_ensure_response(
			array_merge(
				SetupPage::get_status(),
				array(
					'message' => __( 'Setup completed.', 'licensesender' ),
				)
			)
		);
	}

	public static function submit_feature_request( \WP_REST_Request $request ) {
		$title   = trim( (string) $request->get_param( 'title' ) );
		$message = trim( (string) $request->get_param( 'message' ) );

		if ( $title === '' || $message === '' ) {
			return new \WP_Error(
				'missing_fields',
				__( 'Please enter a title and description for your feature request.', 'licensesender' ),
				array( 'status' => 400 )
			);
		}

		$result = Api::submit_feature_request(
			array(
				'title'   => $title,
				'message' => $message,
			)
		);

		if ( empty( $result['success'] ) ) {
			return new \WP_Error(
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

	public static function get_order_backfill_status( \WP_REST_Request $request ) {
		if ( ! class_exists( OrderPush::class ) ) {
			return new \WP_Error(
				'unavailable',
				__( 'Order sync is not available.', 'licensesender' ),
				array( 'status' => 503 )
			);
		}

		return rest_ensure_response( OrderPush::get_backfill_status() );
	}

	public static function start_order_backfill( \WP_REST_Request $request ) {
		if ( ! class_exists( OrderPush::class ) ) {
			return new \WP_Error(
				'unavailable',
				__( 'Order sync is not available.', 'licensesender' ),
				array( 'status' => 503 )
			);
		}

		$result = OrderPush::start_backfill();

		if ( empty( $result['success'] ) ) {
			return new \WP_Error(
				'backfill_failed',
				(string) ( $result['message'] ?? __( 'Could not start order backfill.', 'licensesender' ) ),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * List/export filter params from the request.
	 */
	private static function license_filters( \WP_REST_Request $request ) {
		return array(
			'search'     => (string) $request->get_param( 'search' ),
			'status'     => sanitize_key( (string) $request->get_param( 'status' ) ),
			'product_id' => absint( $request->get_param( 'product_id' ) ),
			'date_from'  => (string) $request->get_param( 'date_from' ),
			'date_to'    => (string) $request->get_param( 'date_to' ),
		);
	}

	public static function list_licenses( \WP_REST_Request $request ) {
		return rest_ensure_response(
			AdminService::list_licenses(
				array_merge(
					self::license_filters( $request ),
					array(
						'page'     => $request->get_param( 'page' ),
						'per_page' => $request->get_param( 'per_page' ),
						'orderby'  => $request->get_param( 'orderby' ),
						'order'    => $request->get_param( 'order' ),
					)
				)
			)
		);
	}

	public static function export_licenses( \WP_REST_Request $request ) {
		return rest_ensure_response( array( 'items' => AdminService::export_licenses( self::license_filters( $request ) ) ) );
	}

	public static function license_products() {
		return rest_ensure_response( array( 'items' => AdminService::license_products() ) );
	}

	public static function bulk_licenses( \WP_REST_Request $request ) {
		$action = (string) $request->get_param( 'action' );
		$result = AdminService::bulk_license_action( $action, (array) $request->get_param( 'ids' ) );

		if ( $action === 'sync' ) {
			/* translators: %d: number of orders */
			$message = sprintf( _n( '%d order synced.', '%d orders synced.', $result['done'], 'licensesender' ), $result['done'] );
		} else {
			/* translators: %d: number of orders */
			$message = sprintf( _n( 'License email resent for %d order.', 'License emails resent for %d orders.', $result['done'], 'licensesender' ), $result['done'] );
		}

		return rest_ensure_response( array_merge( $result, array( 'message' => $message ) ) );
	}

	public static function get_license( \WP_REST_Request $request ) {
		$id  = (int) $request['id'];
		$row = AdminService::get_license( $id );
		if ( ! $row ) {
			return new \WP_Error( 'not_found', __( 'License not found.', 'licensesender' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $row );
	}

	public static function update_license( \WP_REST_Request $request ) {
		$id   = (int) $request['id'];
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		if ( ! AdminService::update_license( $id, $data ) ) {
			return new \WP_Error( 'update_failed', __( 'Failed to update license.', 'licensesender' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'message' => __( 'License updated successfully.', 'licensesender' ),
				'license' => AdminService::get_license( $id ),
			)
		);
	}

	public static function report_license( \WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$params = $request->get_json_params();
		$result = AdminService::report_license( $id, is_array( $params ) ? $params : array() );

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

	public static function sync_order_licenses( \WP_REST_Request $request ) {
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
			return new \WP_Error( 'missing_order', __( 'Missing order ID.', 'licensesender' ), array( 'status' => 400 ) );
		}

		$result = AdminService::sync_order_licenses( $order_id, $force );
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

	public static function fetch_by_sku( \WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$sku    = is_array( $params ) ? ( $params['sku'] ?? '' ) : '';
		$result = AdminService::fetch_license_by_sku( $sku, $params ?: array() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	public static function list_download_links() {
		return rest_ensure_response( array( 'items' => AdminService::list_download_links() ) );
	}

	public static function get_download_link( \WP_REST_Request $request ) {
		$row = AdminService::get_download_link( (int) $request['id'] );
		if ( ! $row ) {
			return new \WP_Error( 'not_found', __( 'Record not found.', 'licensesender' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $row );
	}

	public static function create_download_link( \WP_REST_Request $request ) {
		$data   = $request->get_json_params();
		$result = AdminService::save_download_link( is_array( $data ) ? $data : array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'message' => __( 'Added successfully.', 'licensesender' ), 'item' => $result ) );
	}

	public static function update_download_link( \WP_REST_Request $request ) {
		$data         = $request->get_json_params();
		$data['id']   = (int) $request['id'];
		$result       = AdminService::save_download_link( is_array( $data ) ? $data : array() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'message' => __( 'Updated successfully.', 'licensesender' ), 'item' => $result ) );
	}

	public static function delete_download_link( \WP_REST_Request $request ) {
		if ( ! AdminService::delete_download_link( (int) $request['id'] ) ) {
			return new \WP_Error( 'delete_failed', __( 'Failed to delete record.', 'licensesender' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'message' => __( 'Deleted successfully.', 'licensesender' ) ) );
	}

	public static function list_activation_guides() {
		return rest_ensure_response( array( 'items' => AdminService::list_activation_guides() ) );
	}

	public static function get_activation_guide( \WP_REST_Request $request ) {
		$row = AdminService::get_activation_guide( (int) $request['id'] );
		if ( ! $row ) {
			return new \WP_Error( 'not_found', __( 'Activation guide not found.', 'licensesender' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $row );
	}

	public static function delete_activation_guide( \WP_REST_Request $request ) {
		if ( ! AdminService::delete_activation_guide( (int) $request['id'] ) ) {
			return new \WP_Error( 'delete_failed', __( 'Failed to delete activation guide.', 'licensesender' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'message' => __( 'Activation guide deleted successfully.', 'licensesender' ) ) );
	}

	public static function save_activation_guide( \WP_REST_Request $request ) {
		unset( $request );
		return new \WP_Error(
			'use_admin_ajax',
			__( 'Activation guide saves with file uploads must use the admin UI (admin-ajax).', 'licensesender' ),
			array( 'status' => 501 )
		);
	}

	public static function search_products( \WP_REST_Request $request ) {
		$q = $request->get_param( 'q' ) ?: '';
		if ( rest_sanitize_boolean( $request->get_param( 'variations' ) ) ) {
			return rest_ensure_response( array( 'items' => AdminService::search_products_with_variations( $q ) ) );
		}
		return rest_ensure_response( array( 'items' => AdminService::search_products( $q ) ) );
	}

	public static function list_wholesale_applications( \WP_REST_Request $request ) {
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		$rows   = Wholesale::list_applications( $status );
		$items  = array_map( array( __CLASS__, 'format_wholesale_application' ), $rows );

		return rest_ensure_response( array( 'items' => $items ) );
	}

	public static function generate_wholesale_pages( \WP_REST_Request $request ) {
		unset( $request );

		$result = AdminService::generate_wholesale_pages();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	public static function generate_support_pages( \WP_REST_Request $request ) {
		unset( $request );

		$result = AdminService::generate_support_pages();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	public static function get_wholesale_catalog_summary( \WP_REST_Request $request ) {
		$refresh = rest_sanitize_boolean( $request->get_param( 'refresh' ) );
		if ( $refresh ) {
			Wholesale::clear_catalog_cache();
		}

		$catalog = Wholesale::get_catalog( $refresh );

		return rest_ensure_response(
			array(
				'tier'    => ! empty( $catalog['tier'] ) ? (string) $catalog['tier'] : 'default',
				'count'   => isset( $catalog['count'] ) ? (int) $catalog['count'] : count( $catalog['products'] ?? array() ),
				'message' => (string) ( $catalog['message'] ?? '' ),
				'success' => ! empty( $catalog['success'] ),
			)
		);
	}

	public static function review_wholesale_application( \WP_REST_Request $request ) {
		$id     = (int) $request['id'];
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		$note   = sanitize_textarea_field( (string) $request->get_param( 'admin_note' ) );
		$group  = $request->has_param( 'group' ) ? sanitize_title( (string) $request->get_param( 'group' ) ) : null;

		$result = Wholesale::review_application( $id, $status, $note, 0, $group );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'message' => __( 'Application updated.', 'licensesender' ),
				'item'    => self::format_wholesale_application( Wholesale::get_application( $id ) ),
			)
		);
	}

	public static function list_wholesale_groups( \WP_REST_Request $request ) {
		$refresh = rest_sanitize_boolean( $request->get_param( 'refresh' ) );

		return rest_ensure_response(
			array(
				'items'         => Wholesale::get_groups( $refresh ),
				'default_group' => Wholesale::get_auto_approve_group(),
			)
		);
	}

	/**
	 * Move an approved buyer to another group.
	 */
	public static function set_wholesale_application_group( \WP_REST_Request $request ) {
		$application = Wholesale::get_application( (int) $request['id'] );
		if ( ! $application ) {
			return new \WP_Error( 'not_found', __( 'Application not found.', 'licensesender' ), array( 'status' => 404 ) );
		}

		Wholesale::set_user_group( (int) $application['user_id'], (string) $request->get_param( 'group' ) );

		return rest_ensure_response(
			array(
				'message' => __( 'Buyer group updated.', 'licensesender' ),
				'item'    => self::format_wholesale_application( $application ),
			)
		);
	}

	/**
	 * Live catalog products no WooCommerce product is mapped to (buyers can't add them to cart).
	 */
	public static function list_unmapped_wholesale_skus( \WP_REST_Request $request ) {
		unset( $request );

		$catalog = Wholesale::get_catalog( false, '' );
		if ( empty( $catalog['success'] ) ) {
			return new \WP_Error( 'catalog_unavailable', (string) ( $catalog['message'] ?? __( 'Catalog unavailable.', 'licensesender' ) ), array( 'status' => 502 ) );
		}

		$items = array();
		foreach ( (array) $catalog['products'] as $product ) {
			$sku = (string) ( $product['sku'] ?? '' );
			if ( $sku !== '' && ! \LicenseSender\Wholesale\Catalog::find_mapped_wc_product_by_sku( $sku ) ) {
				$items[] = array(
					'sku'             => $sku,
					'name'            => (string) ( $product['name'] ?? $sku ),
					'available_stock' => (int) ( $product['available_stock'] ?? 0 ),
				);
			}
		}

		return rest_ensure_response( array( 'items' => $items, 'total' => count( (array) $catalog['products'] ) ) );
	}

	/**
	 * Map a catalog SKU to a WooCommerce product (or variation) and enable LicenseSender on it.
	 */
	public static function map_wholesale_sku( \WP_REST_Request $request ) {
		$sku     = sanitize_text_field( (string) $request->get_param( 'sku' ) );
		$product = wc_get_product( absint( $request->get_param( 'product_id' ) ) );

		if ( $sku === '' || ! $product ) {
			return new \WP_Error( 'invalid', __( 'Choose a product to map this SKU to.', 'licensesender' ), array( 'status' => 400 ) );
		}
		if ( $product->is_type( 'variable' ) ) {
			return new \WP_Error( 'invalid', __( 'Choose one variation of this product, not the parent.', 'licensesender' ), array( 'status' => 400 ) );
		}

		update_post_meta( $product->get_id(), '_ls_mapped_product', $sku );
		update_post_meta( $product->get_id(), '_ls_enabled', 'yes' );

		return rest_ensure_response(
			array(
				/* translators: 1: SKU, 2: product name */
				'message' => sprintf( __( '%1$s is now sold as %2$s.', 'licensesender' ), $sku, $product->get_name() ),
			)
		);
	}

	public static function delete_wholesale_application( \WP_REST_Request $request ) {
		$result = Wholesale::delete_application( (int) $request['id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'message' => __( 'Application deleted.', 'licensesender' ),
			)
		);
	}

	public static function bulk_delete_wholesale_applications( \WP_REST_Request $request ) {
		$ids = $request->get_param( 'ids' );
		if ( ! is_array( $ids ) ) {
			return new \WP_Error( 'invalid_ids', __( 'No applications selected.', 'licensesender' ), array( 'status' => 400 ) );
		}

		$result = Wholesale::delete_applications( $ids );
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
			'group'           => $user ? Wholesale::get_user_group( $user->ID ) : '',
		);
	}
}
