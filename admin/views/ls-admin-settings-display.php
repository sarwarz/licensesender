<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       http://licensshipper.com
 * @since      1.0.0
 *
 * @package    Ls_Admin_Display
 * @subpackage Ls_Admin_Display/admin/view
 */

defined( 'ABSPATH' ) || exit();


class Ls_Admin_Settings_Display {

    public static function output() {
        if ( LS_Admin_Service::uses_react_admin() ) {
            LS_Admin_Assets::render_shell( 'settings' );
            return;
        }

        $current_tab = $_GET['tab'] ?? 'general';

        $tabs = [
            'general'                 => __('General Settings', 'license-shipper'),
            'api'                     => __('API Settings', 'license-shipper'),
            'email'                   => __('E-Mail Settings', 'license-shipper'),
            'design'                  => __('Design', 'license-shipper'),
            'popup'                   => __('PopUp Settings', 'license-shipper'),
            'advance'                 => __('Advance Settings', 'license-shipper'),
        ];

        ?>
        <div class="wrap">
            <hr class="wp-header-end">
            <h2 class="nav-tab-wrapper">
                <?php foreach ($tabs as $slug => $label): ?>
                    <?php
                        $url = add_query_arg([
                            'page' => $_GET['page'],
                            'tab'  => $slug
                        ], admin_url('admin.php'));

                        $active = ($slug === $current_tab) ? 'nav-tab-active' : '';
                    ?>
                    <a href="<?php echo esc_url($url); ?>" class="nav-tab <?php echo esc_attr($active); ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <div class="ls-tab-content" style="margin-top: 20px;">
                <?php
                switch ($current_tab) {

                    case 'api':
                        include plugin_dir_path(dirname(__FILE__)) . '/partials/api-settings.php';
                        break;


                    case 'email':
                        include plugin_dir_path(dirname(__FILE__)) . '/partials/email-settings.php';
                        break;

                    case 'design':
                        include plugin_dir_path(dirname(__FILE__)) . '/partials/design-settings.php';
                        break;

                    case 'popup':
                        include plugin_dir_path(dirname(__FILE__)) . '/partials/popup-settings.php';
                        break;

                    case 'advance':
                        include plugin_dir_path(dirname(__FILE__)) . '/partials/advance-settings.php';
                        break;


                    case 'general':
                    default:
                        include plugin_dir_path(dirname(__FILE__)) . '/partials/general-settings.php';
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
}
