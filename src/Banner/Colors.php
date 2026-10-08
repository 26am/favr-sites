<?php
/**
 * Banner colours.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Banner;

/**
 * Pure helpers: is this a plain hex colour, and which text colour reads best on it.
 */
final class Colors {

	public const LIGHT = '#ffffff';
	public const DARK  = '#1a1a1a';

	/**
	 * A plain hex colour (#RGB or #RRGGBB). Anything else (rgba(), 8-digit hex, variables) isn't.
	 *
	 * @param string $hex Colour.
	 */
	public static function valid( string $hex ): bool {
		return 1 === preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $hex );
	}

	/**
	 * White or near-black text, whichever has the higher WCAG contrast on this background.
	 *
	 * @param string $hex Background.
	 */
	public static function textOn( string $hex ): string {
		if ( ! self::valid( $hex ) ) {
			return self::LIGHT;
		}
		$background = self::luminance( $hex );
		$on_light   = 1.05 / ( $background + 0.05 );
		$on_dark    = ( $background + 0.05 ) / ( self::luminance( self::DARK ) + 0.05 );
		return $on_dark > $on_light ? self::DARK : self::LIGHT;
	}

	/**
	 * WCAG relative luminance of a valid hex colour.
	 *
	 * @param string $hex Colour.
	 */
	private static function luminance( string $hex ): float {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$total = 0.0;
		foreach ( array( 0.2126, 0.7152, 0.0722 ) as $i => $weight ) {
			$channel = hexdec( substr( $hex, $i * 2, 2 ) ) / 255;
			$total  += $weight * ( $channel <= 0.03928 ? $channel / 12.92 : ( ( $channel + 0.055 ) / 1.055 ) ** 2.4 );
		}
		return $total;
	}
}
