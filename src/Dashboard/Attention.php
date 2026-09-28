<?php
/**
 * Needs your attention: approval queues with something waiting.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Dashboard;

/**
 * Reads the plugin-neutral `favr_approvals_providers` contract (see favr/core Approvals\Inbox).
 */
final class Attention {

	/**
	 * Queues the user can review that have items waiting.
	 *
	 * @param array<mixed> $providers Providers.
	 * @param callable     $can       callable( string $capability ): bool.
	 * @return list<array{id: string, label: string, count: int}>
	 */
	public static function queues( array $providers, callable $can ): array {
		$out = array();
		foreach ( $providers as $provider ) {
			if ( ! is_array( $provider ) || ! isset( $provider['id'], $provider['label'], $provider['items'] ) ) {
				continue;
			}
			if ( ! $can( (string) ( $provider['capability'] ?? 'manage_options' ) ) ) {
				continue;
			}
			try {
				$count = isset( $provider['count'] ) && is_callable( $provider['count'] )
					? (int) call_user_func( $provider['count'] )
					: count( (array) call_user_func( $provider['items'] ) );
			} catch ( \Throwable $e ) {
				continue;
			}
			if ( $count > 0 ) {
				$out[] = array(
					'id'    => (string) $provider['id'],
					'label' => (string) $provider['label'],
					'count' => $count,
				);
			}
		}
		return $out;
	}

	/**
	 * For the current user.
	 *
	 * @return list<array{id: string, label: string, count: int}>
	 */
	public static function current(): array {
		return self::queues( (array) apply_filters( 'favr_approvals_providers', array() ), 'current_user_can' );
	}

	/** Where the queues are reviewed. */
	public static function url(): string {
		return admin_url( 'admin.php?page=favr-approvals' );
	}
}
