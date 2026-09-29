<?php
/**
 * Help and support links.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Help;

/**
 * Resolution: wp-config constant → site option → fleet default. Empty values are not shown.
 */
final class Links {

	public const OPTION = 'favr_sites_help';

	/** Fleet defaults. The help centre URL is added once the Favr help site exists. */
	public const DEFAULTS = array(
		'help_url'      => '',
		'support_email' => 'care@favr.site',
		'support_phone' => '407-889-9987',
		'booking_url'   => '',
	);

	/**
	 * Resolved links.
	 *
	 * @return array{help_url: string, support_email: string, support_phone: string, booking_url: string}
	 */
	public static function all(): array {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$out   = array();
		foreach ( self::DEFAULTS as $key => $default ) {
			$constant = 'FAVR_SITES_' . strtoupper( $key );
			if ( defined( $constant ) ) {
				$value = (string) constant( $constant );
			} elseif ( isset( $saved[ $key ] ) && '' !== trim( (string) $saved[ $key ] ) ) {
				$value = (string) $saved[ $key ];
			} else {
				$value = $default;
			}
			$out[ $key ] = self::clean( $key, $value );
		}
		return $out;
	}

	/**
	 * Sanitize one value by key.
	 *
	 * @param string $key   Field key.
	 * @param string $value Raw value.
	 */
	public static function clean( string $key, string $value ): string {
		return match ( $key ) {
			'support_email' => (string) sanitize_email( $value ),
			'support_phone' => (string) sanitize_text_field( $value ),
			default         => (string) esc_url_raw( trim( $value ), array( 'http', 'https' ) ),
		};
	}
}
