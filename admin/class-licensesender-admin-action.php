<?php
defined( 'ABSPATH' ) || exit();

/**
 * Ls_Licensesender_Admin_Action Class
 */
class Ls_Licensesender_Admin_Action{

	private static function verify_manage_ajax() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized access.', 'licensesender' ) ),
				403
			);
		}
	}

	/**
	 * Capability + CSRF nonce for download-link / activation-guide AJAX.
	 */
	private static function verify_manage_ajax_nonce( $action = 'ls_admin_manage_nonce' ) {
		self::verify_manage_ajax();
		check_ajax_referer( $action, 'nonce' );
	}

	public static function init(){

		 add_action( 'admin_post_ls_save_settings', array( __CLASS__ , 'ls_save_settings') );
		 add_action( 'admin_post_ls_generate_wholesale_pages', array( __CLASS__, 'ls_generate_wholesale_pages' ) );
		 add_action( 'admin_post_ls_generate_support_pages', array( __CLASS__, 'ls_generate_support_pages' ) );
		 add_action( 'wp_ajax_ls_ping_api', array( __CLASS__ , 'ls_ping_api') );
		 add_action( 'wp_ajax_ls_send_test_email', array( __CLASS__ , 'ls_send_test_email_callback') );
		 add_action( 'wp_ajax_ls_get_download_links', array( __CLASS__ , 'ls_get_download_links') );
		 add_action( 'wp_ajax_ls_get_single_download_link', array( __CLASS__ , 'ls_get_single_download_link') );
		 add_action( 'wp_ajax_ls_save_download_link', array( __CLASS__ , 'ls_save_download_link') );
		 add_action( 'wp_ajax_ls_delete_download_link', array( __CLASS__ , 'ls_delete_download_link') );
		 add_action( 'wp_ajax_ls_get_activation_guides', array( __CLASS__ , 'ls_get_activation_guides') );
		 add_action( 'wp_ajax_ls_save_activation_guide', array( __CLASS__ , 'ls_save_activation_guide') );
		 add_action( 'wp_ajax_ls_get_single_activation_guide', array( __CLASS__ , 'ls_get_single_activation_guide') );
		 add_action( 'wp_ajax_ls_delete_activation_guide', array( __CLASS__ , 'ls_delete_activation_guide') );

	}


	public static function ls_save_settings(){

		if (!current_user_can('manage_options')) {
	        LS_Admin_Notice::error(__('You do not have permission to perform this action.', 'licensesender'));
	        wp_redirect(wp_get_referer());
	        exit;
	    }

	    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'ls_save_settings_nonce')) {
	        LS_Admin_Notice::error(__('Security check failed.', 'licensesender'));
	        wp_redirect(wp_get_referer());
	        exit;
	    }

	    $tab = sanitize_text_field($_POST['tab'] ?? '');


	    switch ($tab) {
	        case 'general':

				$autocomplete_order = isset($_POST['lship_autocomplete_order']) ? 'yes' : 'no';
				$send_email_after_redeem = isset($_POST['lship_send_email_after_redeem']) ? 'yes' : 'no';

				$lship_enable_variation_support =
					($_POST['lship_enable_variation_support'] ?? 'no') === 'yes' ? 'yes' : 'no';

				$lship_enable_manage_downloads =
					($_POST['lship_enable_manage_downloads'] ?? 'no') === 'yes' ? 'yes' : 'no';

				$lship_enable_manage_activation_guides =
					($_POST['lship_enable_manage_activation_guides'] ?? 'no') === 'yes' ? 'yes' : 'no';

				

				update_option('lship_autocomplete_order', $autocomplete_order);
				update_option('lship_send_email_after_redeem', $send_email_after_redeem);
				update_option('lship_enable_variation_support', $lship_enable_variation_support);
				update_option('lship_enable_manage_downloads', $lship_enable_manage_downloads);
				update_option('lship_enable_manage_activation_guides', $lship_enable_manage_activation_guides);
				

				break;


	        case 'api':
	            $api_key = sanitize_text_field($_POST['lship_api_key']);
	            update_option('lship_api_key', $api_key);
	            break;

	        case 'email':

			    // Save sender name
			    if (isset($_POST['lship_email_sender_name'])) {
			        update_option('lship_email_sender_name', sanitize_text_field($_POST['lship_email_sender_name']));
			    }

			    // Save sender email
			    if (isset($_POST['lship_email_sender_email']) && is_email($_POST['lship_email_sender_email'])) {
			        update_option('lship_email_sender_email', sanitize_email($_POST['lship_email_sender_email']));
			    } else {
			        delete_option('lship_email_sender_email'); // fallback to site default
			    }

			    // Save email subject
			    if (isset($_POST['lship_email_subject'])) {
			        update_option('lship_email_subject', sanitize_text_field($_POST['lship_email_subject']));
			    }

			    if (isset($_POST['lship_support_email'])) {
			        update_option('lship_support_email', sanitize_text_field($_POST['lship_support_email']));
			    }

			    $email_text_fields = [
			        'lship_email_subject',
			        'lship_email_subject_single',
			        'lship_email_subject_bulk',
			        'lship_email_preheader',
			    ];
			    foreach ($email_text_fields as $key) {
			        if (isset($_POST[$key])) {
			            update_option($key, sanitize_text_field($_POST[$key]));
			        }
			    }
			    foreach (['lship_email_intro_single', 'lship_email_intro_bulk'] as $key) {
			        if (isset($_POST[$key])) {
			            update_option($key, sanitize_textarea_field(wp_unslash($_POST[$key])));
			        }
			    }

			    break;

			case 'design':
		    if (class_exists('LS_Design_System')) {
		        LS_Design_System::save_settings(wp_unslash($_POST));
		        break;
		    }
		    $design_keys = [
		        'ls_brand', 'ls_brand_2', 'ls_ring', 'ls_success', 'ls_success_2',
		        'ls_blue_600', 'ls_blue_500', 'ls_amber_500', 'ls_amber_400',
		        'ls_code_bg', 'ls_code_fg', 'ls_code_border', 'ls_code_accent',
		        'lship_brand_color', 'lship_accent_color',
		    ];

		    foreach ($design_keys as $key) {
		        $raw = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
		        $hex = sanitize_hex_color($raw);
		        update_option($key, $hex ? $hex : '');
		    }
		    if (isset($_POST['lship_email_logo'])) {
		        update_option('lship_email_logo', esc_url_raw(wp_unslash($_POST['lship_email_logo'])));
		    }
		    break;


		    case 'popup':
			    $ui_keys = [
			      'ls_sw_confirm_title', 'ls_sw_confirm_text', 'ls_sw_confirm_btn', 'ls_sw_cancel_btn',
			      'ls_sw_bulk_title', 'ls_sw_bulk_text', 'ls_sw_bulk_confirm_btn', 'ls_sw_bulk_cancel_btn',
			      'ls_sw_bulk_done_title', 'ls_sw_bulk_done_text',
			      'ls_sw_view_title', 'ls_sw_view_title_many', 'ls_sw_view_copy_all', 'ls_sw_view_close',
			    ];
			    foreach ($ui_keys as $key) {
			      $val = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
			      update_option($key, in_array($key, ['ls_sw_confirm_text', 'ls_sw_bulk_text', 'ls_sw_bulk_done_text'], true)
			          ? sanitize_textarea_field($val)
			          : sanitize_text_field($val));
			    }
			    foreach (['ls_sw_confirm_color', 'ls_sw_cancel_color'] as $key) {
			      $raw = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
			      $hex = sanitize_hex_color($raw);
			      update_option($key, $hex ? $hex : '');
			    }
			    break;

			case 'wholesale':
				$result = LS_Admin_Service::save_settings( 'wholesale', wp_unslash( $_POST ) );
				if ( is_wp_error( $result ) ) {
					LS_Admin_Notice::error( $result->get_error_message() );
					wp_redirect( wp_get_referer() );
					exit;
				}
				break;

			case 'support':
				$result = LS_Admin_Service::save_settings( 'support', wp_unslash( $_POST ) );
				if ( is_wp_error( $result ) ) {
					LS_Admin_Notice::error( $result->get_error_message() );
					wp_redirect( wp_get_referer() );
					exit;
				}
				break;

			case 'advance':
			   
			   /** SSO */
				$lship_sso_enabled = ($_POST['lship_sso_enabled'] ?? 'no') === 'yes' ? 'yes' : 'no';

				update_option('lship_sso_enabled', $lship_sso_enabled);

				if (!empty($_POST['lship_sso_token'])) {
					update_option('lship_sso_token', sanitize_text_field($_POST['lship_sso_token']));
				}

				if (!empty($_POST['lship_sso_user_email'])) {
					update_option('lship_sso_user_email', sanitize_email($_POST['lship_sso_user_email']));
				}

			    break;


	        default:
	            LS_Admin_Notice::error(__('Invalid settings tab.', 'licensesender'));
	            wp_redirect(wp_get_referer());
	            exit;
	    }

	    LS_Admin_Notice::success(__('Settings saved successfully.', 'licensesender'));
	    wp_redirect(wp_get_referer());
	    exit;

	}

	public static function ls_generate_wholesale_pages() {
		if ( ! current_user_can( 'publish_pages' ) ) {
			LS_Admin_Notice::error( __( 'You do not have permission to perform this action.', 'licensesender' ) );
			wp_safe_redirect( wp_get_referer() ?: admin_url() );
			exit;
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ls_generate_wholesale_pages' ) ) {
			LS_Admin_Notice::error( __( 'Security check failed.', 'licensesender' ) );
			wp_safe_redirect( wp_get_referer() ?: admin_url() );
			exit;
		}

		$result = LS_Admin_Service::generate_wholesale_pages();
		if ( is_wp_error( $result ) ) {
			LS_Admin_Notice::error( $result->get_error_message() );
		} else {
			LS_Admin_Notice::success( $result['message'] ?? __( 'Wholesale pages generated successfully.', 'licensesender' ) );
		}

		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=ls-settings&tab=wholesale' ) );
		exit;
	}

	public static function ls_generate_support_pages() {
		if ( ! current_user_can( 'publish_pages' ) ) {
			LS_Admin_Notice::error( __( 'You do not have permission to perform this action.', 'licensesender' ) );
			wp_safe_redirect( wp_get_referer() ?: admin_url() );
			exit;
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ls_generate_support_pages' ) ) {
			LS_Admin_Notice::error( __( 'Security check failed.', 'licensesender' ) );
			wp_safe_redirect( wp_get_referer() ?: admin_url() );
			exit;
		}

		$result = LS_Admin_Service::generate_support_pages();
		if ( is_wp_error( $result ) ) {
			LS_Admin_Notice::error( $result->get_error_message() );
		} else {
			LS_Admin_Notice::success( $result['message'] ?? __( 'Support pages generated successfully.', 'licensesender' ) );
		}

		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=ls-settings&tab=support' ) );
		exit;
	}


	public static function ls_ping_api(){

		check_ajax_referer('ls_ping_api_nonce');

	    if (!current_user_can('manage_options')) {
	        wp_send_json_error([
	            'message' => __('Unauthorized access.', 'licensesender')
	        ], 403);
	    }

	    $result = Licensesender_Api::ping();

	    if ( ! empty( $result['success'] ) ) {
	        wp_send_json_success( $result );
	    }

	    wp_send_json_error(
	        array(
	            'message' => $result['message'] ?? __( 'Unknown error occurred.', 'licensesender' ),
	            'meta'    => $result['meta'] ?? array(),
	        )
	    );
	}

	public static function ls_send_test_email_callback() {
		check_ajax_referer( 'ls_test_email_nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'licensesender' ) ), 403 );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid email address.', 'licensesender' ) ), 400 );
		}

		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'bulk';
		if ( ! in_array( $mode, array( 'single', 'bulk' ), true ) ) {
			$mode = 'bulk';
		}

		$sent = LS_License_Email_Service::send_test_email( $email, $mode );
		if ( $sent ) {
			wp_send_json_success( array( 'message' => __( 'Test email sent successfully.', 'licensesender' ) ) );
		}

		wp_send_json_error( array( 'message' => __( 'Failed to send test email.', 'licensesender' ) ), 500 );
	}

	public static function ls_get_download_links(){
		self::verify_manage_ajax_nonce();

		global $wpdb;
	    $table = $wpdb->prefix . 'ls_download_links';

	    // Fetch all rows
	    $results = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A);

	    $data = [];

	    foreach ($results as $row) {

	        // Get product name safely
	        $product_name = get_the_title($row['product_id']);
	        if (empty($product_name)) {
	            $product_name = __('(Product not found)', 'licensesender');
	        }

	        $data[] = [
			    'id'           => (int) $row['id'],
			    'product_id'   => (int) $row['product_id'],
			    'product_name' => esc_html($product_name),
			    'link'         => esc_url($row['link']),
			    'actions'      => '
			        <a href="#" class="button button-small ls-edit-link" data-id="' . esc_attr($row['id']) . '" title="Edit">
			            <span class="dashicons dashicons-edit"></span>
			        </a>
			        <a href="#" class="button button-small ls-delete-link" data-id="' . esc_attr($row['id']) . '" title="Delete" 
			           style="margin-left:5px;background:#e3342f;border-color:#e3342f;color:#fff;">
			            <span class="dashicons dashicons-trash"></span>
			        </a>
			    '
			];

	    }

	    // Always return JSON with "data" key
	    wp_send_json([
	        'data' => $data
	    ]);
	}

	public static function ls_get_single_download_link() {
	    self::verify_manage_ajax_nonce();

	    global $wpdb;

	    $table = $wpdb->prefix . 'ls_download_links';
	    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

	    if (!$id) {
	        wp_send_json_error(__('Invalid ID provided.', 'licensesender'));
	    }

	    // Fetch record
	    $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);

	    if (!$record) {
	        wp_send_json_error(__('Record not found.', 'licensesender'));
	    }

	    // Get product details
	    $product_id   = (int) $record['product_id'];
	    $product_name = get_the_title($product_id);

	    if (empty($product_name)) {
	        $product_name = __('(Product not found)', 'licensesender');
	    }

	    // Build response
	    $response = [
	        'id'            => (int) $record['id'],
	        'product_id'    => $product_id,
	        'product_name'  => esc_html($product_name),
	        'link'          => esc_url($record['link']),
	        'created_at'    => isset($record['created_at']) ? esc_html($record['created_at']) : '',
	    ];

	    wp_send_json_success($response);
	}


	public static function ls_save_download_link() {
	    self::verify_manage_ajax_nonce();

	    global $wpdb;

	    $table = $wpdb->prefix . 'ls_download_links';

	    $id         = isset($_POST['id']) ? absint($_POST['id']) : 0;
	    $product_id = absint($_POST['product_id']);
	    $link       = esc_url_raw($_POST['link']);

	    if (!$product_id || empty($link)) {
	        wp_send_json_error(__('Please fill all fields.', 'licensesender'));
	    }

	    //  Check for existing entry with same product_id (ignore current ID when updating)
	    $duplicate = $wpdb->get_var(
	        $wpdb->prepare(
	            "SELECT COUNT(*) FROM {$table} WHERE product_id = %d AND id != %d",
	            $product_id,
	            $id
	        )
	    );

	    if ($duplicate > 0) {
	        wp_send_json_error(__('A download link already exists for this product.', 'licensesender'));
	    }

	    // Insert or update
	    if ($id > 0) {
	        $updated = $wpdb->update(
	            $table,
	            [
	                'product_id' => $product_id,
	                'link'       => $link
	            ],
	            [ 'id' => $id ],
	            [ '%d', '%s' ],
	            [ '%d' ]
	        );

	        if ($updated !== false) {
	            wp_send_json_success(__('Updated successfully.', 'licensesender'));
	        } else {
	            wp_send_json_error(__('Failed to update record.', 'licensesender'));
	        }

	    } else {
	        $inserted = $wpdb->insert(
	            $table,
	            [
	                'product_id' => $product_id,
	                'link'       => $link,
	                'created_at' => current_time('mysql')
	            ],
	            [ '%d', '%s', '%s' ]
	        );

	        if ($inserted) {
	            wp_send_json_success(__('Added successfully.', 'licensesender'));
	        } else {
	            wp_send_json_error(__('Failed to add record.', 'licensesender'));
	        }
	    }
	}


	public static function ls_delete_download_link(){
		self::verify_manage_ajax_nonce();

		global $wpdb;
	    $table = $wpdb->prefix . 'ls_download_links';
	    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

	    if (!$id) {
	        wp_send_json_error(__('Invalid ID.', 'licensesender'));
	    }

	    $deleted = $wpdb->delete($table, ['id' => $id], ['%d']);

	    if ($deleted !== false) {
	        wp_send_json_success(__('Deleted successfully.', 'licensesender'));
	    } else {
	        wp_send_json_error(__('Failed to delete record.', 'licensesender'));
	    }
	}

	/**
	 * Get all activation guides for DataTable
	 */
	public static function ls_get_activation_guides() {
	    self::verify_manage_ajax_nonce();

	    global $wpdb;

	    $table = $wpdb->prefix . 'ls_activation_guides';
	    $data  = [];

	    $results = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC", ARRAY_A);

	    if ($results) {
	        foreach ($results as $row) {
	            $product_name = __('(Product Deleted)', 'licensesender');
	            if (!empty($row['product_id'])) {
	                $product = wc_get_product($row['product_id']);
	                if ($product) {
	                    $product_name = $product->get_name();
	                }
	            }

	            $type_label = ucfirst($row['type'] ?? 'Text');

	            $actions = sprintf(
				    '<a href="%1$s" class="button button-small ls-edit-link" title="%3$s">
				        <span class="dashicons dashicons-edit"></span>
				    </a>
				    <a href="#" class="button button-small ls-delete-guide" data-id="%2$d" title="%4$s" 
				       style="margin-left:6px;background:#e3342f;border-color:#e3342f;color:#fff;">
				        <span class="dashicons dashicons-trash"></span>
				    </a>',
				    esc_url(admin_url('admin.php?page=ls-activation-guides&action=edit&id=' . intval($row['id']))),
				    intval($row['id']),
				    esc_attr__('Edit', 'licensesender'),
				    esc_attr__('Delete', 'licensesender')
				);


	            $data[] = [
	                'id'          => intval($row['id']),
	                'product_name'=> esc_html($product_name),
	                'type'        => esc_html($type_label),
	                'created_at'  => esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row['created_at']))),
	                'actions'     => $actions,
	            ];
	        }
	    }

	    //  Send clean JSON for DataTables
	    wp_send_json(['data' => $data]);
	}



	public static function ls_save_activation_guide() {
	    self::verify_manage_ajax();

	    global $wpdb;

	    $table = $wpdb->prefix . 'ls_activation_guides';

	    $has_legacy = isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ls_activation_guide_nonce' );
	    $has_manage = isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ls_admin_manage_nonce' );
	    if ( ! $has_legacy && ! $has_manage ) {
	        wp_send_json_error( __( 'Security verification failed.', 'licensesender' ) );
	    }

	    $id         = isset($_POST['id']) ? absint($_POST['id']) : 0;
	    $product_id = absint($_POST['product_id']);
	    $type       = sanitize_text_field($_POST['type']);
	    $content    = '';
	    $pdf_url    = '';

	    if (!$product_id || empty($type)) {
	        wp_send_json_error(__('Please fill in all required fields.', 'licensesender'));
	    }

	    // Ensure uploads directory exists
	    $upload_dir = wp_upload_dir();
	    $pdf_dir    = trailingslashit($upload_dir['basedir']) . 'activation-guides/';
	    $pdf_url_base = trailingslashit($upload_dir['baseurl']) . 'activation-guides/';
	    if (!file_exists($pdf_dir)) {
	        wp_mkdir_p($pdf_dir);
	    }

	    $file_name = 'guide-' . $product_id . '-' . time() . '.pdf';
	    $pdf_path  = $pdf_dir . $file_name;
	    $pdf_link  = $pdf_url_base . $file_name;

	    //  If plain text → convert to PDF using Dompdf
	    if ($type === 'text' || $type === 'plain_text') {
	        $content = wp_kses_post($_POST['content']);

	        try {
	            $html = '
	                <html>
	                    <head>
	                        <meta charset="utf-8">
	                        <style>
	                            body { font-family: DejaVu Sans, sans-serif; font-size: 13px; line-height: 1.6; color: #333; }
	                            h1 { font-size: 20px; margin-bottom: 20px; color: #222; }
	                            hr { border: none; border-top: 1px solid #ccc; margin: 10px 0; }
	                            .footer { margin-top: 40px; font-size: 12px; color: #888; text-align: center; }
	                        </style>
	                    </head>
	                    <body>
	                        <h1>Activation Guide</h1>
	                        <hr>
	                        <div>' . $content . '</div>
	                    </body>
	                </html>
	            ';

	            $options = new \Dompdf\Options();
	            $options->set('isRemoteEnabled', true); // allow https:// and data:image
	            $options->set('defaultFont', 'DejaVu Sans');
	            $options->set('isHtml5ParserEnabled', true);

	            $dompdf = new \Dompdf\Dompdf($options);

	            // Important: convert relative image URLs to absolute URLs
	            $site_url = site_url('/');
	            $html = str_replace('src="/', 'src="' . $site_url, $html);

	            $dompdf->loadHtml($html);
	            $dompdf->setPaper('A4', 'portrait');
	            $dompdf->render();

	            file_put_contents($pdf_path, $dompdf->output());
	            $pdf_url = esc_url_raw($pdf_link);
	        } catch (Exception $e) {
	            error_log('Dompdf PDF generation failed: ' . $e->getMessage());
	            wp_send_json_error(__('PDF generation failed. Please check Dompdf setup.', 'licensesender'));
	        }
	    }

	    //  If type = PDF (uploaded file)
	    elseif ($type === 'pdf') {
	        if (isset($_FILES['pdf_file']) && !empty($_FILES['pdf_file']['name'])) {
	            require_once ABSPATH . 'wp-admin/includes/file.php';
	            $upload = wp_handle_upload($_FILES['pdf_file'], ['test_form' => false]);
	            if (isset($upload['error'])) {
	                wp_send_json_error(__('File upload failed: ', 'licensesender') . $upload['error']);
	            }
	            $pdf_url = esc_url_raw($upload['url']);
	            $content = ''; // skip text
	        } else {
	            if ($id > 0) {
	                $stored = $wpdb->get_var($wpdb->prepare("SELECT content FROM $table WHERE id = %d", $id));
	                $stored_data = maybe_unserialize($stored);
	                $pdf_url = $stored_data['pdf'] ?? '';
	            } else {
	                wp_send_json_error(__('Please upload a PDF file.', 'licensesender'));
	            }
	        }
	    }

	    // Prevent duplicates
	    $exists = $wpdb->get_var($wpdb->prepare(
	        "SELECT COUNT(*) FROM $table WHERE product_id = %d AND id != %d",
	        $product_id, $id
	    ));

	    if ($exists > 0) {
	        wp_send_json_error(__('An activation guide already exists for this product.', 'licensesender'));
	    }

	    // Save serialized content
	    $serialized_content = maybe_serialize([
	        'html' => $content,
	        'pdf'  => $pdf_url,
	    ]);

	    // Insert / update
	    if ($id > 0) {
	        $wpdb->update(
	            $table,
	            [
	                'product_id' => $product_id,
	                'type'       => $type,
	                'content'    => $serialized_content,
	            ],
	            ['id' => $id],
	            ['%d', '%s', '%s'],
	            ['%d']
	        );
	        wp_send_json_success(__('Activation guide updated successfully.', 'licensesender'));
	    } else {
	        $wpdb->insert(
	            $table,
	            [
	                'product_id' => $product_id,
	                'type'       => $type,
	                'content'    => $serialized_content,
	                'created_at' => current_time('mysql'),
	            ],
	            ['%d', '%s', '%s', '%s']
	        );
	        wp_send_json_success(__('Activation guide added successfully.', 'licensesender'));
	    }
	}





	/**
	 * Get single activation guide record (for edit form)
	 */
	public static function ls_get_single_activation_guide() {
	    self::verify_manage_ajax_nonce();

	    global $wpdb;

	    $table = $wpdb->prefix . 'ls_activation_guides';
	    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

	    if (!$id) {
	        wp_send_json_error(__('Invalid record ID.', 'licensesender'));
	    }

	    $record = $wpdb->get_row(
	        $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
	        ARRAY_A
	    );

	    if (!$record) {
	        wp_send_json_error(__('Activation guide not found.', 'licensesender'));
	    }

	    // Unserialize content (newer structure)
	    $content_data = maybe_unserialize($record['content']);
	    $html_content = '';
	    $pdf_link     = '';

	    if (is_array($content_data)) {
	        $html_content = $content_data['html'] ?? '';
	        $pdf_link     = $content_data['pdf'] ?? '';
	    } else {
	        // Backward compatibility — old records only had raw content
	        $html_content = $record['content'];
	    }

	    // Format response for AJAX
	    $response = [
	        'id'           => intval($record['id']),
	        'product_id'   => intval($record['product_id']),
	        'type'         => sanitize_text_field($record['type']),
	        'html_content' => wp_kses_post($html_content),
	        'pdf_link'     => esc_url_raw($pdf_link),
	        'created_at'   => $record['created_at'],
	        'updated_at'   => $record['updated_at'] ?? '',
	    ];

	    wp_send_json_success($response);
	}


	/**
	 * Delete activation guide (AJAX)
	 */
	public static function ls_delete_activation_guide() {
	    self::verify_manage_ajax_nonce();

	    global $wpdb;

	    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
	    if ($id <= 0) {
	        wp_send_json_error(__('Invalid ID.', 'licensesender'));
	    }

	    $table = $wpdb->prefix . 'ls_activation_guides';

	    // Check existence before deletion
	    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE id = %d", $id));
	    if (!$exists) {
	        wp_send_json_error(__('Record not found.', 'licensesender'));
	    }

	    // Delete the record
	    $deleted = $wpdb->delete($table, ['id' => $id], ['%d']);

	    if ($deleted) {
	        wp_send_json_success(__('Activation guide deleted successfully.', 'licensesender'));
	    } else {
	        wp_send_json_error(__('Failed to delete activation guide.', 'licensesender'));
	    }
	}











}

Ls_Licensesender_Admin_Action::init();