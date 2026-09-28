<?php
/**
 * Quick actions and cards contributed by Favr plugins.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Dashboard;

/**
 * Normalizes plain-array contributions: drops invalid rows and rows the user can't use,
 * dedupes by id (last wins), sorts by priority then label.
 */
final class Items {

	public const MAX_STATS = 3;
	public const MAX_ITEMS = 5;

	/**
	 * Quick actions.
	 *
	 * @param array<mixed> $raw Contributions.
	 * @param callable     $can callable( string $capability ): bool.
	 * @return list<array{id: string, label: string, url: string, icon: string, priority: int}>
	 */
	public static function actions( array $raw, callable $can ): array {
		$out = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) || ! self::visible( $row, $can ) ) {
				continue;
			}
			$id    = self::str( $row['id'] ?? '' );
			$label = self::str( $row['label'] ?? '' );
			$url   = self::str( $row['url'] ?? '' );
			if ( '' === $id || '' === $label || '' === $url ) {
				continue;
			}
			$out[ $id ] = array(
				'id'       => $id,
				'label'    => $label,
				'url'      => $url,
				'icon'     => self::str( $row['icon'] ?? '' ),
				'priority' => (int) ( $row['priority'] ?? 50 ),
			);
		}
		return self::sorted( $out, 'label' );
	}

	/**
	 * Cards. Entries may be arrays or callables (closures / [object, method]) returning an array;
	 * a callable that throws only loses its own card.
	 *
	 * @param array<mixed> $raw Contributions.
	 * @param callable     $can callable( string $capability ): bool.
	 * @return list<array<string, mixed>>
	 */
	public static function cards( array $raw, callable $can ): array {
		$out = array();
		foreach ( $raw as $row ) {
			if ( ! is_string( $row ) && is_callable( $row ) ) {
				try {
					$row = call_user_func( $row );
				} catch ( \Throwable $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'Favr Sites: dashboard card failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					}
					continue;
				}
			}
			if ( ! is_array( $row ) || ! self::visible( $row, $can ) ) {
				continue;
			}
			$id    = self::str( $row['id'] ?? '' );
			$title = self::str( $row['title'] ?? '' );
			if ( '' === $id || '' === $title ) {
				continue;
			}
			$out[ $id ] = array(
				'id'       => $id,
				'title'    => $title,
				'priority' => (int) ( $row['priority'] ?? 50 ),
				'stats'    => self::rows( $row['stats'] ?? array(), self::MAX_STATS, array( 'label', 'value' ), array( 'url' ) ),
				'items'    => self::rows( $row['items'] ?? array(), self::MAX_ITEMS, array( 'title' ), array( 'meta', 'url' ) ),
				'link'     => self::link( $row['link'] ?? null ),
				'empty'    => self::emptyState( $row['empty'] ?? null ),
			);
		}
		return self::sorted( $out, 'title' );
	}

	/**
	 * Visible to this user?
	 *
	 * @param array<string, mixed> $row Row.
	 * @param callable             $can Check.
	 */
	private static function visible( array $row, callable $can ): bool {
		$cap = self::str( $row['capability'] ?? '' );
		return '' === $cap || (bool) $can( $cap );
	}

	/**
	 * Normalize sub-rows.
	 *
	 * @param mixed        $rows     Raw rows.
	 * @param int          $max      Cap.
	 * @param list<string> $required Keys that must be non-empty.
	 * @param list<string> $optional Keys that default to ''.
	 * @return list<array<string, string>>
	 */
	private static function rows( $rows, int $max, array $required, array $optional ): array {
		$out = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean = array();
			foreach ( $required as $key ) {
				$clean[ $key ] = self::str( $row[ $key ] ?? '' );
				if ( '' === $clean[ $key ] ) {
					continue 2;
				}
			}
			foreach ( $optional as $key ) {
				$clean[ $key ] = self::str( $row[ $key ] ?? '' );
			}
			$out[] = $clean;
			if ( count( $out ) >= $max ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * A link needs both parts.
	 *
	 * @param mixed $link Raw.
	 * @return array{label: string, url: string}|null
	 */
	private static function link( $link ): ?array {
		if ( ! is_array( $link ) ) {
			return null;
		}
		$label = self::str( $link['label'] ?? '' );
		$url   = self::str( $link['url'] ?? '' );
		return '' !== $label && '' !== $url ? array(
			'label' => $label,
			'url'   => $url,
		) : null;
	}

	/**
	 * Empty state needs text; its call to action is optional.
	 *
	 * @param mixed $state Raw.
	 * @return array{text: string, label: string, url: string}|null
	 */
	private static function emptyState( $state ): ?array {
		if ( ! is_array( $state ) || '' === self::str( $state['text'] ?? '' ) ) {
			return null;
		}
		return array(
			'text'  => self::str( $state['text'] ),
			'label' => self::str( $state['label'] ?? '' ),
			'url'   => self::str( $state['url'] ?? '' ),
		);
	}

	/**
	 * Scalars to trimmed strings; anything else to ''.
	 *
	 * @param mixed $value Raw.
	 */
	private static function str( $value ): string {
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Sort by priority, then a label key.
	 *
	 * @param array<string, array<string, mixed>> $rows Rows keyed by id.
	 * @param string                              $by   Tie-break key.
	 * @return list<array<string, mixed>>
	 */
	private static function sorted( array $rows, string $by ): array {
		$rows = array_values( $rows );
		usort( $rows, static fn( array $a, array $b ): int => array( $a['priority'], strtolower( $a[ $by ] ) ) <=> array( $b['priority'], strtolower( $b[ $by ] ) ) );
		return $rows;
	}
}
