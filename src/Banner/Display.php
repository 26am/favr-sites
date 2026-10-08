<?php
/**
 * The alert banner on the public site.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Banner;

/**
 * Prints the banner above the header on `wp_body_open` (in the footer when a theme never fires
 * it; the script then moves it to the top). Pages may be cached, so the inline script re-checks
 * the show and hide times and the visitor's dismissal. Keep assets/banner/banner.js in step with
 * Banner::state().
 */
final class Display {

	public const FALLBACK = '#2E2230';
	public const URGENT   = '#B42318';

	/**
	 * Printed already (once per page).
	 *
	 * @var bool
	 */
	private bool $printed = false;

	/**
	 * Memoized banner for this request: null until looked up, false when there is nothing to show.
	 *
	 * @var array{banner: Banner, state: string}|false|null
	 */
	private $current = null;

	/**
	 * Background and text colours for a style.
	 *
	 * @param string $style Banner style.
	 * @param string $kit   The site's main colour, or '' when unknown.
	 * @return array{bg: string, fg: string}
	 */
	public static function colors( string $style, string $kit ): array {
		if ( 'urgent' === $style ) {
			return array(
				'bg' => self::URGENT,
				'fg' => Colors::LIGHT,
			);
		}
		$background = Colors::valid( $kit ) ? $kit : self::FALLBACK;
		return array(
			'bg' => $background,
			'fg' => Colors::textOn( $background ),
		);
	}

	/** The Elementor Site Kit's Primary colour, or '' when there isn't one. */
	public static function kitColor(): string {
		if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return '';
		}
		try {
			$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit_for_frontend();
			foreach ( (array) $kit->get_settings_for_display( 'system_colors' ) as $color ) {
				if ( is_array( $color ) && 'primary' === ( $color['_id'] ?? '' ) ) {
					return is_string( $color['color'] ?? null ) ? $color['color'] : '';
				}
			}
		} catch ( \Throwable $e ) {
			return '';
		}
		return '';
	}

	/**
	 * The banner's markup.
	 *
	 * @param Banner                        $banner  Banner.
	 * @param string                        $state   live|pending.
	 * @param array{bg: string, fg: string} $colors  Colours.
	 * @param bool                          $late    Printed in the footer.
	 * @param bool                          $preview Static preview for the admin screen.
	 */
	public static function markup( Banner $banner, string $state, array $colors, bool $late = false, bool $preview = false ): string {
		$window = $banner->window( wp_timezone() );
		ob_start();
		include FAVR_SITES_PATH . 'templates/banner.php';
		return (string) ob_get_clean();
	}

	/** Hooks. */
	public function hook(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_body_open', array( $this, 'open' ), 5 );
		add_action( 'wp_footer', array( $this, 'footer' ), 5 );
	}

	/** The stylesheet, in the head, only when there is a banner. */
	public function enqueue(): void {
		if ( $this->current() ) {
			wp_enqueue_style( 'favr-sites-banner', FAVR_SITES_URL . 'assets/banner/banner.css', array(), FAVR_SITES_VERSION );
		}
	}

	/** Above the header. */
	public function open(): void {
		$this->output( false );
	}

	/** Fallback for themes that never fire wp_body_open. */
	public function footer(): void {
		$this->output( true );
	}

	/**
	 * Print the banner and its script once.
	 *
	 * @param bool $late Printed in the footer.
	 */
	private function output( bool $late ): void {
		$current = $this->current();
		if ( $this->printed || ! $current ) {
			return;
		}
		$this->printed = true;
		echo self::markup( $current['banner'], $current['state'], self::colors( $current['banner']->style(), self::kitColor() ), $late ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the template escapes.
		wp_print_inline_script_tag( (string) file_get_contents( FAVR_SITES_PATH . 'assets/banner/banner.js' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local plugin file.
	}

	/**
	 * The banner to show on this request, if any. Never in wp-admin, feeds or the Elementor editor.
	 *
	 * @return array{banner: Banner, state: string}|false
	 */
	private function current() {
		if ( null === $this->current ) {
			$this->current = false;
			if ( ! is_admin() && ! is_feed() && ! isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
				$zone   = wp_timezone();
				$banner = Store::get();
				$state  = $banner->state( new \DateTimeImmutable( 'now', $zone ), $zone );
				if ( 'off' !== $state ) {
					$this->current = array(
						'banner' => $banner,
						'state'  => $state,
					);
				}
			}
		}
		return $this->current;
	}
}
