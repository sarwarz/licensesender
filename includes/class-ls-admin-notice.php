<?php
defined('ABSPATH') || exit;

class LS_Admin_Notice {

    /**
     * Hook into admin_notices on class load
     */
    public static function init() {
        add_action('admin_notices', [__CLASS__, 'display_notices']);
    }

    /**
     * Add a success notice
     */
    public static function success($message) {
        self::add_notice($message, 'success');
    }

    /**
     * Add an error notice
     */
    public static function error($message) {
        self::add_notice($message, 'error');
    }

    /**
     * Add a warning notice
     */
    public static function warning($message) {
        self::add_notice($message, 'warning');
    }

    /**
     * Add an info notice
     */
    public static function info($message) {
        self::add_notice($message, 'info');
    }

    /**
     * Store the notice in a transient
     */
    protected static function add_notice($message, $type) {
        $notices = get_transient('ls_admin_notices');
        if (!is_array($notices)) {
            $notices = [];
        }

        $notices[] = ['message' => $message, 'type' => $type];
        set_transient('ls_admin_notices', $notices, 30);
    }

    /**
     * Get pending notices without clearing them.
     *
     * @return array<int, array{message: string, type: string}>
     */
    public static function get_pending_notices() {
        $notices = get_transient('ls_admin_notices');
        return is_array($notices) ? $notices : array();
    }

    /**
     * Return pending notices and clear the queue.
     *
     * @return array<int, array{message: string, type: string}>
     */
    public static function consume_notices() {
        $notices = self::get_pending_notices();
        if (!empty($notices)) {
            delete_transient('ls_admin_notices');
        }
        return $notices;
    }

    /**
     * Render all stored notices (legacy admin screens only).
     */
    public static function display_notices() {
        if (class_exists('LS_Admin_Service') && LS_Admin_Service::is_react_admin_screen()) {
            return;
        }

        $notices = self::consume_notices();
        if (empty($notices)) {
            return;
        }

        foreach ($notices as $notice) {
            $class = 'notice notice-' . esc_attr($notice['type']) . ' is-dismissible';
            echo '<div class="' . $class . '"><p>' . esc_html($notice['message']) . '</p></div>';
        }
    }
}

// Self-initialize
LS_Admin_Notice::init();
