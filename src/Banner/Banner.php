<?php
/**
 * The alert banner.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Banner;

/**
 * An immutable value carrying every banner rule: cleaning a submitted form, whether the banner
 * is off, pending or live, the window the browser re-checks, and the dismissal version.
 * Times are local `Y-m-d H:i` strings in the site time zone.
 */
final class Banner {

	public const STYLES      = array( 'standard', 'urgent' );
	public const MESSAGE_MAX = 200;
	public const LABEL_MAX   = 40;

	/**
	 * Constructor.
	 *
	 * @param bool   $enabled     Switched on.
	 * @param string $message     Plain text.
	 * @param string $link_label   Link label.
	 * @param string $link_url     Link address.
	 * @param string $style       One of STYLES.
	 * @param string $starts      Show from (local), or ''.
	 * @param string $ends        Hide after (local), or ''.
	 * @param bool   $dismissible Visitors can close it.
	 * @param int    $updated     When it was last saved.
	 */
	private function __construct(
		private bool $enabled,
		private string $message,
		private string $link_label,
		private string $link_url,
		private string $style,
		private string $starts,
		private string $ends,
		private bool $dismissible,
		private int $updated
	) {}

	/**
	 * From the stored option. Anything unexpected counts as missing.
	 *
	 * @param mixed $data Stored value.
	 */
	public static function fromArray( $data ): self {
		$data    = is_array( $data ) ? $data : array();
		$style   = (string) self::scalar( $data, 'style' );
		$updated = self::scalar( $data, 'updated' );
		return new self(
			(bool) self::scalar( $data, 'enabled' ),
			(string) self::scalar( $data, 'message' ),
			(string) self::scalar( $data, 'link_label' ),
			(string) self::scalar( $data, 'link_url' ),
			in_array( $style, self::STYLES, true ) ? $style : self::STYLES[0],
			self::time( self::scalar( $data, 'starts' ) ),
			self::time( self::scalar( $data, 'ends' ) ),
			array_key_exists( 'dismissible', $data ) ? (bool) self::scalar( $data, 'dismissible' ) : true,
			is_numeric( $updated ) ? (int) $updated : 0
		);
	}

	/**
	 * A submitted form → a banner, plus anything that could not be saved.
	 *
	 * @param array<string, mixed>     $input     Submitted fields (a checkbox that is absent is off).
	 * @param callable(string): string $clean_url Returns '' for an address it rejects.
	 * @param int                      $now       Timestamp of this save.
	 * @return array{banner: self, warnings: list<string>}
	 */
	public static function clean( array $input, callable $clean_url, int $now ): array {
		$warnings = array();
		$message  = mb_substr( sanitize_text_field( (string) self::scalar( $input, 'message' ) ), 0, self::MESSAGE_MAX );
		$label    = mb_substr( sanitize_text_field( (string) self::scalar( $input, 'link_label' ) ), 0, self::LABEL_MAX );
		$raw_url  = trim( (string) self::scalar( $input, 'link_url' ) );
		$url      = '' === $raw_url ? '' : (string) $clean_url( $raw_url );
		if ( '' !== $raw_url && '' === $url ) {
			$warnings[] = __( 'The link address can’t be used, so the link was not saved.', 'favr-sites' );
		}
		if ( '' === $url ) {
			$label = '';
		} elseif ( '' === $label ) {
			$label = __( 'Learn more', 'favr-sites' );
		}

		$style  = strtolower( (string) self::scalar( $input, 'style' ) );
		$starts = self::time( self::scalar( $input, 'starts' ) );
		$ends   = self::time( self::scalar( $input, 'ends' ) );
		if ( '' !== $starts && '' !== $ends && $ends <= $starts ) {
			$ends       = '';
			$warnings[] = __( 'The hide time was not after the show time, so it was not saved.', 'favr-sites' );
		}

		return array(
			'banner'   => new self(
				! empty( $input['enabled'] ),
				$message,
				$label,
				$url,
				in_array( $style, self::STYLES, true ) ? $style : self::STYLES[0],
				$starts,
				$ends,
				! empty( $input['dismissible'] ),
				$now
			),
			'warnings' => $warnings,
		);
	}

	/**
	 * Off (not enabled, no message, or past the hide time), pending (before the show time) or live.
	 *
	 * @param \DateTimeImmutable $now  Now.
	 * @param \DateTimeZone      $zone Site time zone.
	 */
	public function state( \DateTimeImmutable $now, \DateTimeZone $zone ): string {
		if ( ! $this->enabled || '' === $this->message ) {
			return 'off';
		}
		$window = $this->window( $zone );
		$time   = $now->getTimestamp();
		if ( null !== $window['until'] && $time >= $window['until'] ) {
			return 'off';
		}
		return null !== $window['from'] && $time < $window['from'] ? 'pending' : 'live';
	}

	/**
	 * The show and hide times as UTC timestamps, for the browser.
	 *
	 * @param \DateTimeZone $zone Site time zone.
	 * @return array{from: ?int, until: ?int}
	 */
	public function window( \DateTimeZone $zone ): array {
		return array(
			'from'  => '' !== $this->starts ? ( new \DateTimeImmutable( $this->starts, $zone ) )->getTimestamp() : null,
			'until' => '' !== $this->ends ? ( new \DateTimeImmutable( $this->ends, $zone ) )->getTimestamp() : null,
		);
	}

	/** Changes with every save: the key a visitor's dismissal is remembered under. */
	public function version(): string {
		return substr( md5( implode( '|', array( $this->message, $this->link_label, $this->link_url, $this->style, $this->starts, $this->ends, $this->dismissible ? '1' : '0', (string) $this->updated ) ) ), 0, 10 );
	}

	/**
	 * The stored shape.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'enabled'     => $this->enabled,
			'message'     => $this->message,
			'link_label'  => $this->link_label,
			'link_url'    => $this->link_url,
			'style'       => $this->style,
			'starts'      => $this->starts,
			'ends'        => $this->ends,
			'dismissible' => $this->dismissible,
			'updated'     => $this->updated,
		);
	}

	/** Switched on. */
	public function enabled(): bool {
		return $this->enabled;
	}

	/** Message. */
	public function message(): string {
		return $this->message;
	}

	/** Link label. */
	public function linkLabel(): string {
		return $this->link_label;
	}

	/** Link address. */
	public function linkUrl(): string {
		return $this->link_url;
	}

	/** Style. */
	public function style(): string {
		return $this->style;
	}

	/** Show from (local). */
	public function starts(): string {
		return $this->starts;
	}

	/** Hide after (local). */
	public function ends(): string {
		return $this->ends;
	}

	/** Visitors can close it. */
	public function dismissible(): bool {
		return $this->dismissible;
	}

	/** Last saved. */
	public function updated(): int {
		return $this->updated;
	}

	/**
	 * A value only when it is scalar.
	 *
	 * @param array<mixed> $data Data.
	 * @param string       $key  Key.
	 * @return scalar|null
	 */
	private static function scalar( array $data, string $key ) {
		return isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ? $data[ $key ] : null;
	}

	/**
	 * A `datetime-local` or stored value → `Y-m-d H:i`, or '' when it isn't a real date and time.
	 *
	 * @param mixed $raw Raw value.
	 */
	private static function time( $raw ): string {
		if ( ! is_string( $raw ) || 1 !== preg_match( '/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(?::\d{2})?$/', trim( $raw ), $match ) ) {
			return '';
		}
		$value  = $match[1] . ' ' . $match[2];
		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $value );
		return $parsed && $parsed->format( 'Y-m-d H:i' ) === $value ? $value : '';
	}
}
