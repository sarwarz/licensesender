<?php
/**
 * Class loading.
 *
 * Release builds ship Composer's optimised autoloader in vendor/. A plain git checkout
 * (or a GitHub source zip) has no vendor/, so a small PSR-4 loader takes over.
 */

defined( 'ABSPATH' ) || exit;

if ( is_readable( LICENSESENDER_PATH . 'vendor/autoload.php' ) ) {
	require_once LICENSESENDER_PATH . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( $class ) {
			$prefix = 'LicenseSender\\';
			if ( 0 !== strncmp( $class, $prefix, strlen( $prefix ) ) ) {
				return;
			}
			$file = __DIR__ . '/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
	require_once __DIR__ . '/functions.php';
}

// Old class names (Licensesender_Api, LS_Wholesale, ...) resolve to the new classes on first use.
spl_autoload_register(
	static function ( $class ) {
		static $aliases = null;
		if ( null === $aliases ) {
			$aliases = array_change_key_case( require __DIR__ . '/aliases.php', CASE_LOWER );
		}
		$key = strtolower( $class );
		if ( isset( $aliases[ $key ] ) ) {
			class_alias( $aliases[ $key ], $class );
		}
	}
);
