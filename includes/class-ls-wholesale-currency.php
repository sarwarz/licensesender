<?php
/**
 * Multi-currency support for wholesale catalog pricing.
 *
 * @package Licensesender
 */

defined( 'ABSPATH' ) || exit;

class LS_Wholesale_Currency {

	/**
	 * WooCommerce shop base currency.
	 */
	public static function get_base_currency() {
		if ( class_exists( 'Woo_Wallet_Currency_Manager' ) ) {
			return Woo_Wallet_Currency_Manager::instance()->get_base_currency();
		}

		$currency = get_option( 'woocommerce_currency', 'USD' );
		return is_string( $currency ) && $currency !== '' ? strtoupper( $currency ) : 'USD';
	}

	/**
	 * Active storefront currency (selected or default).
	 */
	public static function get_active_currency() {
		if ( class_exists( 'Woo_Wallet_Currency_Manager' ) ) {
			return Woo_Wallet_Currency_Manager::instance()->get_active_currency();
		}

		$currency = self::detect_active_currency();
		if ( $currency !== '' ) {
			return $currency;
		}

		return self::get_base_currency();
	}

	/**
	 * Human-readable provider label when a multi-currency plugin is active.
	 */
	public static function get_provider_label() {
		if ( class_exists( 'Woo_Wallet_Currency_Manager' ) ) {
			$provider = Woo_Wallet_Currency_Manager::instance()->get_active_provider();
			if ( $provider && method_exists( $provider, 'get_label' ) ) {
				return (string) $provider->get_label();
			}
		}

		$provider_id = self::detect_provider_id();
		$labels      = array(
			'woocs'       => __( 'WOOCS / FOX Currency Switcher', 'licensesender' ),
			'curcy'       => __( 'CURCY Multi Currency', 'licensesender' ),
			'yaycurrency' => __( 'YayCurrency', 'licensesender' ),
			'aelia'       => __( 'Aelia Currency Switcher', 'licensesender' ),
			'wcml'        => __( 'WPML Multi-Currency', 'licensesender' ),
		);

		return $labels[ $provider_id ] ?? '';
	}

	/**
	 * Whether a dedicated multi-currency plugin is handling conversion.
	 */
	public static function has_multicurrency_plugin() {
		if ( class_exists( 'Woo_Wallet_Currency_Manager' ) ) {
			$provider_id = Woo_Wallet_Currency_Manager::instance()->get_active_provider_id();
			return $provider_id !== '' && $provider_id !== 'generic';
		}

		return self::detect_provider_id() !== '';
	}

	/**
	 * Currency details for catalog display.
	 *
	 * @return array{code:string,symbol:string,label:string,provider:string,is_multicurrency:bool}
	 */
	public static function get_display_context() {
		$code   = self::get_active_currency();
		$symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol( $code ) : $code;

		return array(
			'code'             => $code,
			'symbol'           => (string) $symbol,
			'label'            => $code,
			'provider'         => self::get_provider_label(),
			'is_multicurrency' => self::has_multicurrency_plugin(),
		);
	}

	/**
	 * Convert a base-currency amount to the active storefront currency.
	 *
	 * @param float $amount Amount in shop base currency.
	 */
	public static function convert_price( $amount ) {
		$amount = (float) $amount;

		// When a dedicated multicurrency plugin is active, keep wholesale amounts
		// in shop base currency so cart totals and catalog display stay aligned —
		// the provider handles display conversion via wc_price / its own hooks.
		if ( self::has_multicurrency_plugin() ) {
			return $amount;
		}

		$base   = self::get_base_currency();
		$active = self::get_active_currency();

		if ( $base === $active ) {
			return $amount;
		}

		$converted = self::convert_amount( $amount, $base, $active );
		return null === $converted ? $amount : (float) $converted;
	}

	/**
	 * Format a base-currency wholesale amount for display.
	 *
	 * @param float              $amount Base currency amount.
	 * @param array<string,mixed> $args  Optional wc_price args.
	 */
	public static function format_price( $amount, $args = array() ) {
		if ( ! function_exists( 'wc_price' ) ) {
			return (string) $amount;
		}

		$converted = self::convert_price( $amount );
		$currency  = self::get_active_currency();

		return wc_price(
			$converted,
			array_merge(
				array( 'currency' => $currency ),
				$args
			)
		);
	}

	/**
	 * Convert between two ISO currency codes.
	 *
	 * @param float  $amount Amount in source currency.
	 * @param string $from   Source ISO code.
	 * @param string $to     Target ISO code.
	 * @return float|null
	 */
	public static function convert_amount( $amount, $from, $to ) {
		$amount = (float) $amount;
		$from   = strtoupper( (string) $from );
		$to     = strtoupper( (string) $to );

		if ( $from === '' || $to === '' || $from === $to ) {
			return $amount;
		}

		if ( class_exists( 'Woo_Wallet_Currency_Manager' ) ) {
			return Woo_Wallet_Currency_Manager::instance()->convert( $amount, $from, $to );
		}

		$converted = self::convert_with_detected_provider( $amount, $from, $to );
		if ( null !== $converted ) {
			return (float) $converted;
		}

		/**
		 * Filter wholesale currency conversion when no built-in provider matched.
		 *
		 * @param float|null $converted Converted amount or null.
		 * @param float      $amount    Source amount.
		 * @param string     $from      Source currency.
		 * @param string     $to        Target currency.
		 */
		$filtered = apply_filters( 'ls_wholesale_convert_currency', null, $amount, $from, $to );
		if ( null !== $filtered && is_numeric( $filtered ) ) {
			return (float) $filtered;
		}

		if ( has_filter( 'wc_aelia_cs_convert' ) ) {
			$aelia = apply_filters( 'wc_aelia_cs_convert', $amount, $from, $to );
			if ( is_numeric( $aelia ) ) {
				return (float) $aelia;
			}
		}

		return null;
	}

	/**
	 * @return string
	 */
	private static function detect_provider_id() {
		if ( class_exists( 'WOOCS' ) && isset( $GLOBALS['WOOCS'] ) ) {
			return 'woocs';
		}

		if ( class_exists( 'WOOMULTI_CURRENCY_F_Data' ) ) {
			return 'curcy';
		}

		if ( class_exists( '\Yay_Currency\Initialize' ) ) {
			return 'yaycurrency';
		}

		if ( has_filter( 'wc_aelia_cs_convert' ) || has_filter( 'wc_aelia_cs_selected_currency' ) ) {
			return 'aelia';
		}

		if ( isset( $GLOBALS['woocommerce_wpml'] ) && is_object( $GLOBALS['woocommerce_wpml'] ) && method_exists( $GLOBALS['woocommerce_wpml'], 'get_multi_currency' ) ) {
			return 'wcml';
		}

		return '';
	}

	/**
	 * @return string
	 */
	private static function detect_active_currency() {
		$base = self::get_base_currency();

		if ( class_exists( 'WOOCS' ) && ! empty( $GLOBALS['WOOCS']->current_currency ) ) {
			return strtoupper( (string) $GLOBALS['WOOCS']->current_currency );
		}

		if ( class_exists( 'WOOMULTI_CURRENCY_F_Data' ) && method_exists( 'WOOMULTI_CURRENCY_F_Data', 'get_ins' ) ) {
			$data = WOOMULTI_CURRENCY_F_Data::get_ins();
			if ( is_object( $data ) && method_exists( $data, 'get_current_currency' ) ) {
				$code = $data->get_current_currency();
				if ( is_string( $code ) && $code !== '' ) {
					return strtoupper( $code );
				}
			}
		}

		if ( class_exists( '\Yay_Currency\Helpers\YayCurrencyHelper' ) && method_exists( '\Yay_Currency\Helpers\YayCurrencyHelper', 'detect_current_currency' ) ) {
			$detected = \Yay_Currency\Helpers\YayCurrencyHelper::detect_current_currency();
			if ( is_array( $detected ) && ! empty( $detected['currency'] ) ) {
				return strtoupper( (string) $detected['currency'] );
			}
		}

		if ( has_filter( 'wc_aelia_cs_selected_currency' ) ) {
			$code = apply_filters( 'wc_aelia_cs_selected_currency', $base );
			if ( is_string( $code ) && $code !== '' ) {
				return strtoupper( $code );
			}
		}

		if ( isset( $GLOBALS['woocommerce_wpml'] ) && is_object( $GLOBALS['woocommerce_wpml'] ) && method_exists( $GLOBALS['woocommerce_wpml'], 'get_multi_currency' ) ) {
			$mc = $GLOBALS['woocommerce_wpml']->get_multi_currency();
			if ( is_object( $mc ) && method_exists( $mc, 'get_client_currency' ) ) {
				$code = $mc->get_client_currency();
				if ( is_string( $code ) && $code !== '' ) {
					return strtoupper( $code );
				}
			}
		}

		return strtoupper( (string) apply_filters( 'woocommerce_currency', $base ) );
	}

	/**
	 * @return float|null
	 */
	private static function convert_with_detected_provider( $amount, $from, $to ) {
		$provider = self::detect_provider_id();

		switch ( $provider ) {
			case 'woocs':
				return self::convert_with_woocs( $amount, $from, $to );
			case 'curcy':
				return self::convert_with_curcy( $amount, $from, $to );
			case 'yaycurrency':
				return self::convert_with_yaycurrency( $amount, $from, $to );
			case 'wcml':
				return self::convert_with_wcml( $amount, $from, $to );
		}

		return null;
	}

	/**
	 * @return float|null
	 */
	private static function convert_with_woocs( $amount, $from, $to ) {
		if ( ! isset( $GLOBALS['WOOCS'] ) || ! is_object( $GLOBALS['WOOCS'] ) || ! method_exists( $GLOBALS['WOOCS'], 'get_currencies' ) ) {
			return null;
		}

		$currencies = $GLOBALS['WOOCS']->get_currencies();
		if ( ! is_array( $currencies ) || ! isset( $currencies[ $from ], $currencies[ $to ] ) ) {
			return null;
		}

		$from_rate = isset( $currencies[ $from ]['rate'] ) ? (float) $currencies[ $from ]['rate'] : 0;
		$to_rate   = isset( $currencies[ $to ]['rate'] ) ? (float) $currencies[ $to ]['rate'] : 0;
		if ( $from_rate <= 0 || $to_rate <= 0 ) {
			return null;
		}

		return (float) $amount * ( 1 / $from_rate ) * $to_rate;
	}

	/**
	 * @return float|null
	 */
	private static function convert_with_curcy( $amount, $from, $to ) {
		if ( ! class_exists( 'WOOMULTI_CURRENCY_F_Data' ) || ! method_exists( 'WOOMULTI_CURRENCY_F_Data', 'get_ins' ) ) {
			return null;
		}

		$data = WOOMULTI_CURRENCY_F_Data::get_ins();
		if ( ! is_object( $data ) || ! method_exists( $data, 'get_list_currencies' ) ) {
			return null;
		}

		$list = $data->get_list_currencies();
		if ( ! is_array( $list ) || ! isset( $list[ $from ]['rate'], $list[ $to ]['rate'] ) ) {
			return null;
		}

		$from_rate = (float) $list[ $from ]['rate'];
		$to_rate   = (float) $list[ $to ]['rate'];
		if ( $from_rate <= 0 || $to_rate <= 0 ) {
			return null;
		}

		return (float) $amount * ( 1 / $from_rate ) * $to_rate;
	}

	/**
	 * @return float|null
	 */
	private static function convert_with_yaycurrency( $amount, $from, $to ) {
		$base = self::get_base_currency();

		if ( $from === $base && $to !== $base && has_filter( 'yay_currency_convert_price' ) ) {
			$active = self::get_active_currency();
			if ( $to === $active ) {
				$converted = apply_filters( 'yay_currency_convert_price', (float) $amount, array() );
				return is_numeric( $converted ) ? (float) $converted : null;
			}
		}

		if ( $from !== $base && $to === $base && has_filter( 'yay_currency_revert_price' ) ) {
			$reverted = apply_filters( 'yay_currency_revert_price', (float) $amount, array() );
			return is_numeric( $reverted ) ? (float) $reverted : null;
		}

		return null;
	}

	/**
	 * @return float|null
	 */
	private static function convert_with_wcml( $amount, $from, $to ) {
		if ( ! isset( $GLOBALS['woocommerce_wpml'] ) || ! is_object( $GLOBALS['woocommerce_wpml'] ) || ! method_exists( $GLOBALS['woocommerce_wpml'], 'get_multi_currency' ) ) {
			return null;
		}

		$mc = $GLOBALS['woocommerce_wpml']->get_multi_currency();
		if ( ! is_object( $mc ) || ! method_exists( $mc, 'prices' ) || ! is_object( $mc->prices ) || ! method_exists( $mc->prices, 'convert_price_amount' ) ) {
			return null;
		}

		$converted = $mc->prices->convert_price_amount( (float) $amount, $to );
		return is_numeric( $converted ) ? (float) $converted : null;
	}
}
