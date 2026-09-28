<?php
/**
 * A small set of line icons for the Favr dashboard.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Dashboard;

/**
 * Static inline SVG; unknown names render nothing.
 */
final class Icons {

	private const PATHS = array(
		'news'     => '<path d="M4 5h13v14H6a2 2 0 0 1-2-2z"/><path d="M17 9h3v8a2 2 0 0 1-2 2"/><path d="M8 9h5M8 13h5"/>',
		'page'     => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/>',
		'calendar' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
		'users'    => '<circle cx="9" cy="9" r="3.5"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 5.5a3.5 3.5 0 0 1 0 7M21 20a6 6 0 0 0-4-5.6"/>',
		'store'    => '<path d="M4 9l1.5-5h13L20 9"/><path d="M4 9v11h16V9"/><path d="M4 9a2.7 2.7 0 0 0 5.3 0 2.7 2.7 0 0 0 5.4 0 2.7 2.7 0 0 0 5.3 0"/><path d="M10 20v-5h4v5"/>',
		'plus'     => '<path d="M12 5v14M5 12h14"/>',
		'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M18 14v5H5V6h5"/>',
		'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
		'phone'    => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1z"/>',
		'help'     => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14"/><path d="M12 17h.01"/>',
		'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
	);

	/**
	 * SVG markup.
	 *
	 * @param string $name Icon name.
	 * @param int    $size Pixels.
	 */
	public static function svg( string $name, int $size = 18 ): string {
		if ( ! isset( self::PATHS[ $name ] ) ) {
			return '';
		}
		return '<svg class="favr-dash__icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . self::PATHS[ $name ] . '</svg>';
	}
}
