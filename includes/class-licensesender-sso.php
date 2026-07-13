<?php
defined('ABSPATH') || exit;

class Licensesender_SSO_Admin_Bar {

    /**
     * Licensesender SSO endpoint
     */
    const SSO_ENDPOINT = 'https://app.licensesender.com/sso/login';

    public static function init() {
        add_action('admin_bar_menu', [__CLASS__, 'add_admin_bar_menu'], 100);
    }

    public static function add_admin_bar_menu($wp_admin_bar) {

        // Only show for admins
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return;
        }

        // Check if SSO enabled
        if (get_option('lship_sso_enabled', 'no') !== 'yes') {
            return;
        }

        $sso_url = self::generate_sso_url();

        // If URL generation failed, don't show menu
        if (!$sso_url) {
            return;
        }

        $wp_admin_bar->add_node([
            'id'    => 'licensesender_sso',
            'title' => '<span class="ab-icon dashicons dashicons-shield"></span>
                        <span class="ab-label">Licensesender</span>',
            'href'  => esc_url($sso_url),
            'meta'  => [
                'target'   => '_blank',
                'title'    => 'Login to Licensesender',
                'position' => 95,
            ],
        ]);
    }

    private static function generate_sso_url() {

        $email = get_option('lship_sso_user_email');
        $token = get_option('lship_sso_token');

        // Fail silently if not configured
        if (empty($email) || empty($token)) {
            return false;
        }

        $expires = time() + 300; // 5 minutes
        $nonce   = wp_generate_uuid4();

        $payload = implode('|', [
            $email,
            $expires,
            $nonce,
        ]);

        $signature = hash_hmac(
            'sha256',
            $payload,
            $token
        );

        return add_query_arg([
            'email'     => rawurlencode($email),
            'expires'   => $expires,
            'nonce'     => $nonce,
            'signature' => $signature,
        ], self::SSO_ENDPOINT);
    }
}

Licensesender_SSO_Admin_Bar::init();
