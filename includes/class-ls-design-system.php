<?php
/**
 * Frontend design system: presets + brand tokens → CSS variables.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Design_System {

	public const OPTION_PRESET      = 'ls_theme_preset';
	public const OPTION_BRAND       = 'ls_brand';
	public const OPTION_ACCENT      = 'ls_accent';
	public const OPTION_RADIUS      = 'ls_radius';
	public const OPTION_DENSITY     = 'ls_density';
	public const OPTION_CODE_STYLE  = 'ls_code_style';
	public const OPTION_EMAIL_SYNC  = 'ls_email_sync_brand';

	/**
	 * Named theme packs. Brand/accent can be overridden by merchant settings.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function presets() {
		return array(
			'indigo' => array(
				'label'       => 'Indigo',
				'description' => 'Default LicenseSender look.',
				'brand'       => '#4f46e5',
				'accent'      => '#2563eb',
				'success'     => array( '#059669', '#10b981' ),
				'info'        => array( '#2563eb', '#3b82f6' ),
				'warn'        => array( '#f59e0b', '#fbbf24' ),
				'code'        => 'dark',
			),
			'ocean'  => array(
				'label'       => 'Ocean',
				'description' => 'Cool teal and sky accents.',
				'brand'       => '#0f766e',
				'accent'      => '#0284c7',
				'success'     => array( '#047857', '#10b981' ),
				'info'        => array( '#0369a1', '#0ea5e9' ),
				'warn'        => array( '#d97706', '#fbbf24' ),
				'code'        => 'dark',
			),
			'forest' => array(
				'label'       => 'Forest',
				'description' => 'Natural greens for catalogs.',
				'brand'       => '#166534',
				'accent'      => '#15803d',
				'success'     => array( '#15803d', '#22c55e' ),
				'info'        => array( '#1d4ed8', '#3b82f6' ),
				'warn'        => array( '#ca8a04', '#eab308' ),
				'code'        => 'dark',
			),
			'slate'  => array(
				'label'       => 'Slate',
				'description' => 'Neutral professional chrome.',
				'brand'       => '#334155',
				'accent'      => '#475569',
				'success'     => array( '#047857', '#10b981' ),
				'info'        => array( '#1e40af', '#3b82f6' ),
				'warn'        => array( '#b45309', '#f59e0b' ),
				'code'        => 'dark',
			),
			'rose'   => array(
				'label'       => 'Rose',
				'description' => 'Warm pink branding.',
				'brand'       => '#e11d48',
				'accent'      => '#db2777',
				'success'     => array( '#059669', '#10b981' ),
				'info'        => array( '#2563eb', '#3b82f6' ),
				'warn'        => array( '#ea580c', '#fb923c' ),
				'code'        => 'dark',
			),
			'ember'  => array(
				'label'       => 'Ember',
				'description' => 'Orange energy CTAs.',
				'brand'       => '#c2410c',
				'accent'      => '#ea580c',
				'success'     => array( '#15803d', '#22c55e' ),
				'info'        => array( '#1d4ed8', '#3b82f6' ),
				'warn'        => array( '#b45309', '#f59e0b' ),
				'code'        => 'dark',
			),
		);
	}

	/**
	 * @return array{dark: array<string,string>, light: array<string,string>}
	 */
	public static function code_themes() {
		return array(
			'dark'  => array(
				'bg'     => '#1e1e2e',
				'fg'     => '#cdd6f4',
				'border' => '#313244',
				'accent' => '#89b4fa',
			),
			'light' => array(
				'bg'     => '#f8fafc',
				'fg'     => '#0f172a',
				'border' => '#cbd5e1',
				'accent' => '#2563eb',
			),
		);
	}

	/**
	 * Settings payload for the Design admin tab.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings_payload() {
		$presets = array();
		foreach ( self::presets() as $id => $preset ) {
			$presets[] = array(
				'id'          => $id,
				'label'       => $preset['label'],
				'description' => $preset['description'],
				'brand'       => $preset['brand'],
				'accent'      => $preset['accent'],
				'swatches'    => array(
					$preset['brand'],
					$preset['accent'],
					$preset['success'][0],
					$preset['info'][0],
				),
			);
		}

		$resolved = self::resolve_tokens();

		return array(
			'ls_theme_preset'     => self::get_preset_id(),
			'ls_brand'            => (string) get_option( self::OPTION_BRAND, $resolved['brand'] ),
			'ls_accent'           => (string) get_option( self::OPTION_ACCENT, $resolved['accent'] ),
			'ls_radius'           => self::get_radius(),
			'ls_density'          => self::get_density(),
			'ls_code_style'       => self::get_code_style(),
			'ls_email_sync_brand' => get_option( self::OPTION_EMAIL_SYNC, 'yes' ) === 'yes' ? 'yes' : 'no',
			'lship_email_logo'    => (string) get_option( 'lship_email_logo', '' ),
			'presets'             => $presets,
			'preview'             => array(
				'brand'   => $resolved['brand'],
				'brand_2' => $resolved['brand_2'],
				'accent'  => $resolved['accent'],
				'success' => $resolved['success'],
				'info'    => $resolved['info'],
				'warn'    => $resolved['warn'],
			),
		);
	}

	/**
	 * @param array<string, mixed> $data Request data.
	 */
	public static function save_settings( $data ) {
		$presets = self::presets();
		$preset  = sanitize_key( (string) ( $data['ls_theme_preset'] ?? 'indigo' ) );
		if ( ! isset( $presets[ $preset ] ) ) {
			$preset = 'indigo';
		}
		update_option( self::OPTION_PRESET, $preset, false );

		$brand = sanitize_hex_color( wp_unslash( (string) ( $data['ls_brand'] ?? '' ) ) );
		update_option( self::OPTION_BRAND, $brand ? $brand : $presets[ $preset ]['brand'], false );

		$accent = sanitize_hex_color( wp_unslash( (string) ( $data['ls_accent'] ?? '' ) ) );
		update_option( self::OPTION_ACCENT, $accent ? $accent : $presets[ $preset ]['accent'], false );

		$radius = sanitize_key( (string) ( $data['ls_radius'] ?? 'md' ) );
		if ( ! in_array( $radius, array( 'sm', 'md', 'lg' ), true ) ) {
			$radius = 'md';
		}
		update_option( self::OPTION_RADIUS, $radius, false );

		$density = sanitize_key( (string) ( $data['ls_density'] ?? 'comfortable' ) );
		if ( ! in_array( $density, array( 'comfortable', 'compact' ), true ) ) {
			$density = 'comfortable';
		}
		update_option( self::OPTION_DENSITY, $density, false );

		$code = sanitize_key( (string) ( $data['ls_code_style'] ?? 'dark' ) );
		if ( ! in_array( $code, array( 'dark', 'light' ), true ) ) {
			$code = 'dark';
		}
		update_option( self::OPTION_CODE_STYLE, $code, false );

		$sync = ( $data['ls_email_sync_brand'] ?? 'yes' ) === 'yes' ? 'yes' : 'no';
		update_option( self::OPTION_EMAIL_SYNC, $sync, false );

		if ( isset( $data['lship_email_logo'] ) ) {
			update_option( 'lship_email_logo', esc_url_raw( wp_unslash( (string) $data['lship_email_logo'] ) ), false );
		}

		// Keep legacy option keys in sync so older CSS/email readers stay consistent.
		$tokens = self::resolve_tokens();
		update_option( 'ls_brand_2', $tokens['brand_2'], false );
		update_option( 'ls_ring', $tokens['ring'], false );
		update_option( 'ls_success', $tokens['success'], false );
		update_option( 'ls_success_2', $tokens['success_2'], false );
		update_option( 'ls_blue_600', $tokens['info'], false );
		update_option( 'ls_blue_500', $tokens['info_2'], false );
		update_option( 'ls_amber_500', $tokens['warn'], false );
		update_option( 'ls_amber_400', $tokens['warn_2'], false );
		update_option( 'ls_code_bg', $tokens['code_bg'], false );
		update_option( 'ls_code_fg', $tokens['code_fg'], false );
		update_option( 'ls_code_border', $tokens['code_border'], false );
		update_option( 'ls_code_accent', $tokens['code_accent'], false );

		if ( $sync === 'yes' ) {
			update_option( 'lship_brand_color', $tokens['brand'], false );
			update_option( 'lship_accent_color', $tokens['accent'], false );
		}
	}

	/**
	 * Resolved design tokens for CSS / email.
	 *
	 * @return array<string, string>
	 */
	public static function resolve_tokens() {
		$presets   = self::presets();
		$preset_id = self::get_preset_id();
		$preset    = $presets[ $preset_id ];

		$brand  = self::sanitize_hex( get_option( self::OPTION_BRAND, '' ), $preset['brand'] );
		$accent = self::sanitize_hex( get_option( self::OPTION_ACCENT, '' ), $preset['accent'] );

		$code_style = self::get_code_style();
		$code       = self::code_themes()[ $code_style ];

		$radius_map = array(
			'sm' => array( 'card' => '8px', 'control' => '6px', 'chip' => '999px' ),
			'md' => array( 'card' => '14px', 'control' => '10px', 'chip' => '999px' ),
			'lg' => array( 'card' => '18px', 'control' => '12px', 'chip' => '999px' ),
		);
		$radius_key = self::get_radius();
		$radius     = $radius_map[ $radius_key ];

		$density = self::get_density();
		$pad     = $density === 'compact' ? '10px' : '14px';
		$gap     = $density === 'compact' ? '10px' : '14px';

		return array(
			'brand'       => $brand,
			'brand_2'     => self::mix_hex( $brand, '#ffffff', 0.22 ),
			'accent'      => $accent,
			'ring'        => $brand,
			'success'     => $preset['success'][0],
			'success_2'   => $preset['success'][1],
			'info'        => $accent,
			'info_2'      => self::mix_hex( $accent, '#ffffff', 0.18 ),
			'warn'        => $preset['warn'][0],
			'warn_2'      => $preset['warn'][1],
			'code_bg'     => $code['bg'],
			'code_fg'     => $code['fg'],
			'code_border' => $code['border'],
			'code_accent' => $code['accent'],
			'radius_card' => $radius['card'],
			'radius_ctl'  => $radius['control'],
			'radius_chip' => $radius['chip'],
			'pad'         => $pad,
			'gap'         => $gap,
		);
	}

	/**
	 * Inline CSS custom properties for the storefront.
	 *
	 * @return string
	 */
	public static function css_variables_block() {
		$t = self::resolve_tokens();
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

		// Soft shape/density overrides for existing surfaces.
		$css .= '.ls-card,.ls-product-card,.ls-keys-card,.ls-my-keys-wrap .card{border-radius:var(--ls-radius-lg)!important;}'
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

	public static function get_preset_id() {
		$id = sanitize_key( (string) get_option( self::OPTION_PRESET, 'indigo' ) );
		return isset( self::presets()[ $id ] ) ? $id : 'indigo';
	}

	public static function get_radius() {
		$v = sanitize_key( (string) get_option( self::OPTION_RADIUS, 'md' ) );
		return in_array( $v, array( 'sm', 'md', 'lg' ), true ) ? $v : 'md';
	}

	public static function get_density() {
		$v = sanitize_key( (string) get_option( self::OPTION_DENSITY, 'comfortable' ) );
		return in_array( $v, array( 'comfortable', 'compact' ), true ) ? $v : 'comfortable';
	}

	public static function get_code_style() {
		$v = sanitize_key( (string) get_option( self::OPTION_CODE_STYLE, 'dark' ) );
		return in_array( $v, array( 'dark', 'light' ), true ) ? $v : 'dark';
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
