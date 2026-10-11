<?php
/**
 * A Favr page instead of WordPress's bare error page, for Editors.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;

/**
 * For Editors, inside wp-admin: a dead or forbidden link ("You attempted to edit an item that does
 * not exist.", "Sorry, you are not allowed to access this page.", "The link you followed has
 * expired.") ends on a Favr card with the message and a link back to the dashboard, instead of
 * WordPress's grey page with no way out. Only the handler for whole pages is replaced
 * (`wp_die_handler`): what WordPress answers to Ajax, JSON and XML requests is left alone, and so
 * is a message printed inside a screen that already has its frame.
 *
 * Also: the browser tab reads "Pages ‹ Site name", without "— WordPress".
 */
final class ErrorPage {

	/** Hooks. */
	public function hook(): void {
		add_filter( 'wp_die_handler', array( $this, 'handler' ), PHP_INT_MAX );
		add_filter( 'admin_title', array( $this, 'title' ), PHP_INT_MAX );
	}

	/**
	 * Who prints the error page: this class for an Editor in wp-admin, before the screen's frame
	 * has been printed; otherwise whoever would have.
	 *
	 * @param mixed $handler The handler so far.
	 * @return mixed
	 */
	public function handler( $handler ) {
		if ( ! is_admin() || did_action( 'admin_head' ) || ! function_exists( 'wp_get_current_user' ) || ! Audience::current() ) {
			return $handler;
		}
		return array( $this, 'render' );
	}

	/**
	 * The browser tab's title, for Editors.
	 *
	 * @param mixed $title WordPress's title.
	 * @return mixed
	 */
	public function title( $title ) {
		return is_string( $title ) && Audience::current() ? self::tabTitle( $title ) : $title;
	}

	/**
	 * A tab title without WordPress's ending: "Pages ‹ Site name — WordPress" reads "Pages ‹ Site name".
	 *
	 * @param string $title WordPress's title (the dash may be an entity).
	 */
	public static function tabTitle( string $title ): string {
		return (string) preg_replace( '/\s*(?:&#8212;|&mdash;|—)\s*WordPress\s*$/u', '', $title );
	}

	/**
	 * Print the card and stop, as WordPress's own handler would (the `wp_die_handler` contract).
	 *
	 * @param mixed $message Message, or a WP_Error.
	 * @param mixed $title   Title.
	 * @param mixed $args    Arguments of wp_die().
	 */
	public function render( $message, $title = '', $args = array() ): void {
		if ( ! function_exists( '_wp_die_process_input' ) ) {
			_default_wp_die_handler( $message, $title, $args );
			return;
		}
		list( $message, , $parsed ) = _wp_die_process_input( $message, $title, $args );

		$errors = array();
		foreach ( (array) ( $parsed['additional_errors'] ?? array() ) as $error ) {
			if ( is_array( $error ) && isset( $error['message'] ) ) {
				$errors[] = (string) $error['message'];
			}
		}
		$charset = (string) ( $parsed['charset'] ?? 'utf-8' );
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=' . $charset );
			status_header( (int) ( $parsed['response'] ?? 500 ) );
			nocache_headers();
		}
		$tokens = 'assets/dashboard/tokens.css';
		$html   = self::page(
			is_string( $message ) ? $message : '',
			array(
				'title'      => __( 'We couldn’t open that', 'favr-sites' ),
				'site'       => (string) get_bloginfo( 'name' ),
				'home'       => admin_url(),
				'stylesheet' => FAVR_SITES_URL . $tokens . '?ver=' . rawurlencode( FAVR_SITES_VERSION . '.' . (int) filemtime( FAVR_SITES_PATH . $tokens ) ),
				'errors'     => $errors,
				'link_url'   => (string) ( $parsed['link_url'] ?? '' ),
				'link_text'  => (string) ( $parsed['link_text'] ?? '' ),
				'back'       => ! empty( $parsed['back_link'] ),
				'rtl'        => function_exists( 'is_rtl' ) && is_rtl(),
				'charset'    => $charset,
			)
		);
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- page() escapes every part.
		if ( ! isset( $parsed['exit'] ) || $parsed['exit'] ) {
			die();
		}
	}

	/**
	 * The whole page: a card with what went wrong and the ways out.
	 *
	 * @param string               $message What WordPress says (may carry paragraphs and links).
	 * @param array<string, mixed> $page    title, site, home, stylesheet, errors (list of further
	 *                                      messages), link_url, link_text, back, rtl, charset.
	 */
	public static function page( string $message, array $page ): string {
		$body = wp_kses_post( $message );
		// WordPress sometimes opens its message with a heading of its own: the card already has one.
		$body = (string) preg_replace( '#<h[1-6][^>]*>(.*?)</h[1-6]>#is', '<p><strong>$1</strong></p>', $body );
		if ( '' !== $body && ! preg_match( '/<(p|ul|ol|div|h[1-6])[\s>]/i', $body ) ) {
			$body = '<p>' . $body . '</p>';
		}
		if ( ! empty( $page['errors'] ) ) {
			$body .= '<ul>';
			foreach ( (array) $page['errors'] as $error ) {
				$body .= '<li>' . wp_kses_post( wp_strip_all_tags( (string) $error ) ) . '</li>';
			}
			$body .= '</ul>';
		}
		$links = '<a class="favr-error__home" href="' . esc_url( (string) $page['home'] ) . '"><span aria-hidden="true">&larr;</span> ' . esc_html__( 'Dashboard', 'favr-sites' ) . '</a>';
		if ( ! empty( $page['back'] ) ) {
			$links .= '<a href="javascript:history.back()">' . esc_html__( 'Go back', 'favr-sites' ) . '</a>';
		}
		if ( '' !== (string) $page['link_url'] && '' !== (string) $page['link_text'] ) {
			$links .= '<a href="' . esc_url( (string) $page['link_url'] ) . '">' . esc_html( (string) $page['link_text'] ) . '</a>';
		}
		$title = esc_html( (string) $page['title'] );
		$tab   = '' === (string) $page['site'] ? $title : $title . ' ‹ ' . esc_html( (string) $page['site'] );

		return '<!DOCTYPE html>' . "\n"
			. '<html' . ( empty( $page['rtl'] ) ? '' : ' dir="rtl"' ) . '>' . "\n"
			. '<head>' . "\n"
			. '<meta http-equiv="Content-Type" content="text/html; charset=' . esc_attr( (string) $page['charset'] ) . '" />' . "\n"
			. '<meta name="viewport" content="width=device-width, initial-scale=1" />' . "\n"
			. '<meta name="robots" content="noindex,nofollow" />' . "\n"
			. '<title>' . $tab . '</title>' . "\n"
			// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- a page of its own: nothing prints the enqueued styles here.
			. '<link rel="stylesheet" href="' . esc_url( (string) $page['stylesheet'] ) . '" />' . "\n"
			. '<style>' . self::css() . '</style>' . "\n"
			. '</head>' . "\n"
			. '<body class="favr-skin favr-error">' . "\n"
			. '<main class="favr-error__card">' . "\n"
			. '<h1>' . $title . '</h1>' . "\n"
			. '<div class="favr-error__message">' . $body . '</div>' . "\n"
			. '<p class="favr-error__links">' . $links . '</p>' . "\n"
			. '</main>' . "\n"
			. '</body>' . "\n"
			. '</html>' . "\n";
	}

	/**
	 * The card's look, from the brand tokens (the greys are for a page whose tokens did not load).
	 */
	private static function css(): string {
		return 'html{background:var(--favr-paper,#fbf6ef)}'
			. 'body.favr-error{margin:0;padding:12vh 16px 48px;background:var(--favr-paper,#fbf6ef);color:var(--favr-ink,#2e2230);font-family:var(--favr-font-sans,-apple-system,"Segoe UI",sans-serif);font-size:15px;line-height:1.55}'
			. '.favr-error__card{box-sizing:border-box;max-width:520px;margin:0 auto;padding:32px 36px;border:1px solid var(--favr-line,#eadfcf);border-radius:var(--favr-radius,14px);background:var(--favr-surface,#fffdf9);box-shadow:var(--favr-shadow,0 6px 20px rgba(46,34,48,.05))}'
			. '.favr-error__card h1{margin:0 0 12px;font-family:var(--favr-font-display,georgia,serif);font-size:28px;font-weight:500;line-height:1.2}'
			. '.favr-error__message{color:var(--favr-muted,#5b4b57);overflow-wrap:anywhere}'
			. '.favr-error__message p,.favr-error__message ul{margin:0 0 10px}'
			. '.favr-error__message ul{padding-left:20px}'
			. '.favr-error__message a{color:var(--favr-accent,#a9472a)}'
			. '.favr-error__links{display:flex;flex-wrap:wrap;gap:8px 24px;margin:24px 0 0;font-size:14px;font-weight:600}'
			. '.favr-error__links a{color:var(--favr-muted,#5b4b57);text-decoration:none}'
			. '.favr-error__links a.favr-error__home{color:var(--favr-accent,#a9472a)}'
			. '.favr-error__links a:hover,.favr-error__links a:focus{color:var(--favr-accent-strong,#8e3a21);text-decoration:underline}'
			. '.favr-error__links a:focus-visible{outline:2px solid var(--favr-accent,#a9472a);outline-offset:3px;border-radius:4px}'
			. '@media (max-width:600px){body.favr-error{padding-top:6vh}.favr-error__card{padding:24px 20px}.favr-error__card h1{font-size:24px}}';
	}
}
