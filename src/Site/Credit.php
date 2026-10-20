<?php
/**
 * "Website powered by Favr" at the bottom of every Favr site.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Site;

use FavrSites\Menus\Screen;

/**
 * One small line after the site's footer: right after Elementor Pro's footer when there is one,
 * otherwise from `wp_footer`. Staff turn it off on Header & footer. The page script gives the line
 * the background of the footer's bottom row and readable text on it; without the script it sits on
 * the page background. Only "Favr" is a link, a plain followed one (David's call, 2026-10-20).
 */
final class Credit {

	public const OPTION = 'favr_sites_credit'; // `on` | `off`; a site that never chose shows it.
	public const ACTION = 'favr_sites_save_credit';
	public const FIELD  = 'favr_credit';
	public const URL    = 'https://favr.site/';

	/**
	 * Printed already (once per page).
	 *
	 * @var bool
	 */
	private bool $printed = false;

	/** Hooks. */
	public function hook(): void {
		add_action( 'elementor/theme/after_do_footer', array( $this, 'afterFooter' ) );
		add_action( 'wp_footer', array( $this, 'fallback' ), 1 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'save' ) );
	}

	/**
	 * Pure: is the line on, given what is stored?
	 *
	 * @param mixed $stored The option (false when it was never saved).
	 */
	public static function shown( $stored ): bool {
		return 'off' !== $stored;
	}

	/**
	 * Pure: what the checkbox stores.
	 *
	 * @param bool $checked The box was ticked.
	 */
	public static function stored( bool $checked ): string {
		return $checked ? 'on' : 'off';
	}

	/**
	 * Pure: did the screen's script send the switch (it saves at once and wants an answer, not a page)?
	 *
	 * @param array<string, mixed> $post The posted fields.
	 */
	public static function wantsJson( array $post ): bool {
		return ! empty( $post['favr_js'] );
	}

	/** Is the line on for this site? */
	public static function on(): bool {
		return self::shown( get_option( self::OPTION, false ) );
	}

	/** The line itself. */
	public static function markup(): string {
		$link = '<a href="' . esc_url( self::URL ) . '">' . esc_html__( 'Favr', 'favr-sites' ) . '</a>';
		/* translators: %s: "Favr", linked to favr.site. */
		$text = sprintf( esc_html__( 'Website powered by %s', 'favr-sites' ), $link );
		return '<div class="favr-credit" data-favr-credit><p class="favr-credit__text">' . $text . '</p></div>';
	}

	/** Right after Elementor Pro's site footer. */
	public function afterFooter(): void {
		$this->out();
	}

	/** Any other footer: at the end of the page, unless it was printed after the footer already. */
	public function fallback(): void {
		$this->out();
	}

	/** Print the line, its style and its script, once. */
	private function out(): void {
		// Not in Elementor's editor: under the footer being designed it would be a strip nobody can select.
		if ( $this->printed || is_admin() || is_embed() || isset( $_GET['elementor-preview'] ) || ! self::on() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
			return;
		}
		$this->printed = true;
		echo self::markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped where it is built.
		if ( defined( 'FAVR_SITES_PATH' ) ) {
			echo '<style id="favr-credit-css">' . wp_strip_all_tags( (string) file_get_contents( FAVR_SITES_PATH . 'assets/credit/credit.css' ) ) . '</style>'; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.Security.EscapeOutput.OutputNotEscaped -- a local plugin file, tags stripped.
			wp_print_inline_script_tag( (string) file_get_contents( FAVR_SITES_PATH . 'assets/credit/credit.js' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local plugin file.
		}
	}

	/** The switch on Header & footer. */
	public function save(): void {
		check_admin_referer( self::ACTION );
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to change the site footer.', 'favr-sites' ), 403 );
		}
		update_option( self::OPTION, self::stored( ! empty( $_POST[ self::FIELD ] ) ), true );
		do_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache's purge hook; a no-op without it.

		/** Fires after the footer credit is turned on or off. Purge other page caches here. */
		do_action( 'favr_sites_credit_saved', self::on() );
		if ( self::wantsJson( $_POST ) ) {
			wp_send_json_success( array( 'on' => self::on() ) );
		}
		wp_safe_redirect( add_query_arg( 'credit', 'saved', admin_url( 'admin.php?page=' . Screen::PAGE ) ) . '#favr-credit' );
		exit;
	}
}
