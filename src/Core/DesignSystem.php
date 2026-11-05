<?php

namespace LicenseSender\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Storefront design settings (Settings → Design).
 *
 * Three choices drive every customer-facing screen (My Keys, order / thank-you keys,
 * support, wholesale): brand colour, corner style and colour mode. Everything else
 * (hover shades, focus rings, readable button text, status colours) is derived.
 */
class DesignSystem {

	public const OPTION_BRAND      = 'ls_brand';
	public const OPTION_RADIUS     = 'ls_radius';
	public const OPTION_COLOR_MODE = 'ls_color_mode';

	public const DEFAULT_BRAND = '#4f46e5';

	/** Status colours (not configurable: they carry meaning, not branding). */
	private const SUCCESS = array( '#059669', '#10b981' );
	private const INFO    = array( '#2563eb', '#3b82f6' );
	private const WARN    = array( '#f59e0b', '#fbbf24' );
	private const CODE    = array(
		'bg'     => '#1e1e2e',
		'fg'     => '#cdd6f4',
		'border' => '#313244',
		'accent' => '#89b4fa',
	);

	/**
	 * Quick brand colours offered in the admin (any hex is allowed).
	 *
	 * @return array<int, array{name: string, hex: string}>
	 */
	public static function brand_swatches() {
		return array(
			array( 'name' => 'Indigo', 'hex' => '#4f46e5' ),
			array( 'name' => 'Blue', 'hex' => '#2563eb' ),
			array( 'name' => 'Teal', 'hex' => '#0f766e' ),
			array( 'name' => 'Green', 'hex' => '#15803d' ),
			array( 'name' => 'Slate', 'hex' => '#334155' ),
			array( 'name' => 'Rose', 'hex' => '#e11d48' ),
			array( 'name' => 'Orange', 'hex' => '#c2410c' ),
			array( 'name' => 'Violet', 'hex' => '#7c3aed' ),
		);
	}

	/**
	 * Corner styles: card / control radius.
	 *
	 * @return array<string, array{card: string, control: string}>
	 */
	public static function radius_styles() {
		return array(
			'sm' => array( 'card' => '8px', 'control' => '6px' ),
			'md' => array( 'card' => '14px', 'control' => '10px' ),
			'lg' => array( 'card' => '20px', 'control' => '14px' ),
		);
	}

	/**
	 * Settings payload for the Design admin tab.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings_payload() {
		return array(
			'ls_brand'            => self::get_brand(),
			'ls_radius'           => self::get_radius(),
			'ls_color_mode'       => self::color_mode(),
			'swatches'            => self::brand_swatches(),
		);
	}

	/**
	 * @param array<string, mixed> $data Request data.
	 */
	public static function save_settings( $data ) {
		$brand = sanitize_hex_color( wp_unslash( (string) ( $data['ls_brand'] ?? '' ) ) );
		update_option( self::OPTION_BRAND, $brand ? strtolower( $brand ) : self::DEFAULT_BRAND, false );

		$radius = sanitize_key( (string) ( $data['ls_radius'] ?? 'md' ) );
		update_option( self::OPTION_RADIUS, isset( self::radius_styles()[ $radius ] ) ? $radius : 'md', false );

		$mode = sanitize_key( (string) ( $data['ls_color_mode'] ?? 'auto' ) );
		update_option( self::OPTION_COLOR_MODE, in_array( $mode, array( 'auto', 'light', 'dark' ), true ) ? $mode : 'auto', false );

		// Older CSS reads these copies.
		$tokens = self::resolve_tokens();
		update_option( 'ls_brand_2', $tokens['brand_2'], false );
		update_option( 'ls_ring', $tokens['ring'], false );
	}

	/**
	 * Resolved design tokens for the storefront apps, older CSS and emails.
	 *
	 * @return array<string, string>
	 */
	public static function resolve_tokens() {
		$brand  = self::get_brand();
		$radius = self::radius_styles()[ self::get_radius() ];

		return array(
			'brand'       => $brand,
			'brand_2'     => self::mix_hex( $brand, '#ffffff', 0.22 ),
			'accent'      => $brand,
			'ring'        => $brand,
			'success'     => self::SUCCESS[0],
			'success_2'   => self::SUCCESS[1],
			'info'        => self::INFO[0],
			'info_2'      => self::INFO[1],
			'warn'        => self::WARN[0],
			'warn_2'      => self::WARN[1],
			'code_bg'     => self::CODE['bg'],
			'code_fg'     => self::CODE['fg'],
			'code_border' => self::CODE['border'],
			'code_accent' => self::CODE['accent'],
			'radius_card' => $radius['card'],
			'radius_ctl'  => $radius['control'],
			'radius_chip' => '999px',
			'pad'         => '14px',
			'gap'         => '14px',
			'color_mode'  => self::color_mode(),
		);
	}

	/**
	 * Inline CSS custom properties for the remaining non-React storefront markup
	 * (wholesale sign-in fallback, support notices).
	 *
	 * @return string
	 */
	public static function css_variables_block() {
		$t    = self::resolve_tokens();
		$ring = self::hex_to_rgba( $t['ring'], 0.55 );

		$css = ':root{'
			. '--ls-brand:' . $t['brand'] . ';'
			. '--ls-brand-1:' . $t['brand_2'] . ';'
			. '--ls-brand-2:' . $t['brand_2'] . ';'
			. '--ls-accent:' . $t['accent'] . ';'
			. '--ls-emerald-600:' . $t['success'] . ';'
			. '--ls-emerald-500:' . $t['success_2'] . ';'
			. '--ls-blue-600:' . $t['info'] . ';'
			. '--ls-blue-500:' . $t['info_2'] . ';'
			. '--ls-amber-500:' . $t['warn'] . ';'
			. '--ls-amber-400:' . $t['warn_2'] . ';'
			. '--ls-ring:' . $ring . ';'
			. '--ls-code-bg:' . $t['code_bg'] . ';'
			. '--ls-code-fg:' . $t['code_fg'] . ';'
			. '--ls-code-border:' . $t['code_border'] . ';'
			. '--ls-code-accent:' . $t['code_accent'] . ';'
			. '--ls-radius:' . $t['radius_ctl'] . ';'
			. '--ls-radius-lg:' . $t['radius_card'] . ';'
			. '--ls-radius-chip:' . $t['radius_chip'] . ';'
			. '--ls-pad:' . $t['pad'] . ';'
			. '--ls-gap:' . $t['gap'] . ';'
			. '}';

		$css .= '.ls-card,.ls-product-card,.ls-keys-card{border-radius:var(--ls-radius-lg)!important;}'
			. '.ls-btn,.ls-action-btn,.ls-key-copy,button.ls-btn{border-radius:var(--ls-radius)!important;}'
			. '.ls-chip{border-radius:var(--ls-radius-chip)!important;}'
			. '.ls-wholesale-wrap{--ls-w-brand:var(--ls-brand);--ls-w-brand-2:var(--ls-brand-1);--ls-w-radius:var(--ls-radius-lg);--ls-w-radius-ctl:var(--ls-radius);--ls-w-radius-chip:var(--ls-radius-chip);}'
			. '.ls-wholesale-wrap .ls-wholesale-btn,.ls-wholesale-wrap .ls-btn,.ls-wholesale-wrap .button{border-radius:var(--ls-radius)!important;}'
			. '.ls-wholesale-wrap .ls-wholesale-card{border-radius:var(--ls-radius-lg)!important;}'
			. '.ls-wholesale-wrap .ls-wholesale-btn-primary,.ls-wholesale-wrap .ls-btn-primary,.ls-wholesale-wrap .button.alt,'
			. '.ls-wholesale-wrap a.button.alt{background:linear-gradient(135deg,var(--ls-brand),var(--ls-brand-1))!important;border-color:transparent!important;color:#fff!important;}'
			. '.ls-wholesale-wrap .ls-stock-ok,.ls-wholesale-wrap .ls-badge-info{color:var(--ls-blue-600)!important;}'
			. '.ls-support-wrap{--ls-support-brand:var(--ls-brand);}';

		return $css;
	}

	public static function get_brand() {
		return strtolower( self::sanitize_hex( get_option( self::OPTION_BRAND, '' ), self::DEFAULT_BRAND ) );
	}

	public static function get_radius() {
		$v = sanitize_key( (string) get_option( self::OPTION_RADIUS, 'md' ) );
		return isset( self::radius_styles()[ $v ] ) ? $v : 'md';
	}

	/**
	 * Light / dark for the storefront apps: 'auto' follows the WordPress theme's background.
	 */
	public static function color_mode() {
		$v = sanitize_key( (string) get_option( self::OPTION_COLOR_MODE, 'auto' ) );
		return in_array( $v, array( 'auto', 'light', 'dark' ), true ) ? $v : 'auto';
	}

	/**
	 * @param mixed  $value   Raw option.
	 * @param string $default Fallback hex.
	 */
	protected static function sanitize_hex( $value, $default ) {
		$hex = sanitize_hex_color( (string) $value );
		return $hex ? $hex : $default;
	}

	/**
	 * Mix two hex colors. Weight 0 = $a, 1 = $b.
	 *
	 * @param string $a Hex.
	 * @param string $b Hex.
	 * @param float  $weight Mix amount toward $b.
	 */
	public static function mix_hex( $a, $b, $weight = 0.2 ) {
		$weight = max( 0, min( 1, (float) $weight ) );
		$aa     = self::hex_to_rgb( $a );
		$bb     = self::hex_to_rgb( $b );
		$r      = (int) round( $aa[0] * ( 1 - $weight ) + $bb[0] * $weight );
		$g      = (int) round( $aa[1] * ( 1 - $weight ) + $bb[1] * $weight );
		$bl     = (int) round( $aa[2] * ( 1 - $weight ) + $bb[2] * $weight );

		return sprintf( '#%02x%02x%02x', $r, $g, $bl );
	}

	/**
	 * @param string $hex Hex color.
	 * @return array{0:int,1:int,2:int}
	 */
	public static function hex_to_rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * @param string $hex   Hex.
	 * @param float  $alpha Alpha 0–1.
	 */
	public static function hex_to_rgba( $hex, $alpha = 1.0 ) {
		$rgb   = self::hex_to_rgb( $hex );
		$alpha = is_numeric( $alpha ) ? max( 0, min( 1, (float) $alpha ) ) : 1;

		return sprintf( 'rgba(%d,%d,%d,%s)', $rgb[0], $rgb[1], $rgb[2], rtrim( rtrim( number_format( $alpha, 2, '.', '' ), '0' ), '.' ) );
	}
}
