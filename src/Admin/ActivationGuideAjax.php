<?php

namespace LicenseSender\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * admin-ajax handler for the React Activation Guides screen (guide save with PDF upload or generation).
 */
class ActivationGuideAjax {

	private static function verify_manage_ajax() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Unauthorized access.', 'licensesender' ) ),
				403
			);
		}
	}


	/**
	 * Drop <img> tags whose src Dompdf must not fetch server-side.
	 *
	 * Keeps data:image URIs and URLs passing the activation-guide host
	 * allowlist (same host, uploads CDN, licensesender.com; private IPs
	 * rejected). Everything else is removed before rendering.
	 */
	private static function sanitize_pdf_html_images( $html ) {
		return (string) preg_replace_callback(
			'/<img\b[^>]*>/i',
			static function ( $match ) {
				$tag = $match[0];

				if ( ! preg_match( '/\bsrc\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $tag, $src ) ) {
					return '';
				}

				$url = trim( $src[2] !== '' ? $src[2] : ( $src[3] ?? '' ) );
				if ( $url === '' ) {
					return '';
				}

				if ( stripos( $url, 'data:image/' ) === 0 ) {
					return $tag;
				}

				return ls_is_safe_activation_guide_url( $url ) ? $tag : '';
			},
			$html
		);
	}

	public static function init() {
		// The React Activation Guides screen posts here: file uploads and PDF generation don't fit the REST API.
		add_action( 'wp_ajax_ls_save_activation_guide', array( __CLASS__, 'ls_save_activation_guide' ) );
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

	            // isRemoteEnabled lets Dompdf fetch image URLs server-side, so
	            // strip any image whose host is not allowlisted (SSRF guard).
	            $html = self::sanitize_pdf_html_images($html);

	            $dompdf->loadHtml($html);
	            $dompdf->setPaper('A4', 'portrait');
	            $dompdf->render();

	            file_put_contents($pdf_path, $dompdf->output());
	            $pdf_url = esc_url_raw($pdf_link);
	        } catch (\Exception $e) {
	            error_log('Dompdf PDF generation failed: ' . $e->getMessage());
	            wp_send_json_error(__('PDF generation failed. Please check Dompdf setup.', 'licensesender'));
	        }
	    }

	    //  If type = PDF (uploaded file)
	    elseif ($type === 'pdf') {
	        if (isset($_FILES['pdf_file']) && !empty($_FILES['pdf_file']['name'])) {
	            require_once ABSPATH . 'wp-admin/includes/file.php';
	            $upload = wp_handle_upload($_FILES['pdf_file'], [
	                'test_form' => false,
	                'mimes'     => ['pdf' => 'application/pdf'],
	            ]);
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


















}
