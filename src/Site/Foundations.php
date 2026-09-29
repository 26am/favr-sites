<?php
/**
 * What every Favr site must have, and what to do about it (pure).
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Site;

/**
 * Home (front page), News (posts page), and the site-wide Header and Footer templates. From a
 * snapshot of the site, decides per role: adopt this id (0 = create), publish/restore it, reset its
 * conditions. Setup applies the decisions.
 */
final class Foundations {

	public const OPTION      = 'favr_sites_foundations';
	public const ROLES       = array( 'header', 'footer' );
	public const ENTIRE_SITE = 'include/general';

	/**
	 * Decisions for the site.
	 *
	 * @param array<string, mixed> $state Snapshot (see Setup::state()).
	 * @return array{home: array{id: int, publish: bool}, news: array{id: int, publish: bool}, header: ?array{id: int, restore: bool, conditions: bool}, footer: ?array{id: int, restore: bool, conditions: bool}}
	 */
	public static function plan( array $state ): array {
		$pages        = (array) $state['pages'];
		$static_front = 'page' === $state['show_on_front'];
		$home         = self::page( (int) $state['page_on_front'], $pages, 'home', 0, $static_front );
		$plan         = array(
			'home'   => $home,
			'news'   => self::page( (int) $state['page_for_posts'], $pages, 'news', $home['id'], $static_front ),
			'header' => null,
			'footer' => null,
		);
		if ( $state['pro'] ) {
			foreach ( self::ROLES as $role ) {
				$plan[ $role ] = self::template( $role, (int) ( $state['recorded'][ $role ] ?? 0 ), (array) $state['templates'] );
			}
		}
		return $plan;
	}

	/**
	 * Would applying the plan change nothing?
	 *
	 * @param array<string, mixed> $plan  From plan().
	 * @param array<string, mixed> $state Snapshot.
	 */
	public static function settled( array $plan, array $state ): bool {
		if ( 'page' !== $state['show_on_front'] || $plan['home']['id'] !== (int) $state['page_on_front'] || $plan['news']['id'] !== (int) $state['page_for_posts'] ) {
			return false;
		}
		if ( ! $plan['home']['id'] || ! $plan['news']['id'] || $plan['home']['publish'] || $plan['news']['publish'] ) {
			return false;
		}
		foreach ( self::ROLES as $role ) {
			$decision = $plan[ $role ];
			$recorded = (int) ( $state['recorded'][ $role ] ?? 0 );
			if ( null !== $decision && ( ! $decision['id'] || $decision['restore'] || $decision['conditions'] || $recorded !== $decision['id'] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * The current page if usable, else a published page named like the role, else 0 (create).
	 * Usable: published, or a draft/pending page while the site shows a static front page (it was
	 * live, so it's republished). Private or scheduled pages are never made public, and in
	 * latest-posts mode the old Reading ids are stale unless the page is already published.
	 *
	 * @param int                              $current Current Reading setting.
	 * @param array<int, array<string, mixed>> $pages   Known pages.
	 * @param string                           $name    "home" or "news".
	 * @param int                              $not     A page it can't be (Home, for News).
	 * @param bool                             $static_front  The site shows a static front page.
	 * @return array{id: int, publish: bool}
	 */
	private static function page( int $current, array $pages, string $name, int $not, bool $static_front ): array {
		$status = (string) ( $pages[ $current ]['status'] ?? '' );
		if ( $current && $current !== $not && ( 'publish' === $status || ( $static_front && in_array( $status, array( 'draft', 'pending' ), true ) ) ) ) {
			return array(
				'id'      => $current,
				'publish' => 'publish' !== $status,
			);
		}
		$best = 0;
		foreach ( $pages as $id => $page ) {
			$named = strtolower( trim( (string) $page['title'] ) ) === $name || $name === $page['slug'];
			// A designed Elementor "News" page would vanish behind the posts index; make a new one.
			$designed = 'news' === $name && ! empty( $page['elementor'] );
			if ( $named && ! $designed && 'publish' === $page['status'] && (int) $id !== $not && (int) $id > $best ) {
				$best = (int) $id;
			}
		}
		return array(
			'id'      => $best,
			'publish' => false,
		);
	}

	/**
	 * The recorded template if it's still one of this role, else the newest published Entire Site
	 * one, else 0 (create).
	 *
	 * @param string                           $role      header|footer.
	 * @param int                              $recorded  Recorded id.
	 * @param array<int, array<string, mixed>> $templates Theme templates.
	 * @return array{id: int, restore: bool, conditions: bool}
	 */
	private static function template( string $role, int $recorded, array $templates ): array {
		if ( $recorded && isset( $templates[ $recorded ] ) && $role === $templates[ $recorded ]['type'] ) {
			return self::repair( $recorded, $templates[ $recorded ] );
		}
		$best = 0;
		foreach ( $templates as $id => $template ) {
			if ( $role !== $template['type'] || 'publish' !== $template['status'] || ! in_array( self::ENTIRE_SITE, (array) $template['conditions'], true ) ) {
				continue;
			}
			if ( ! $best || array( $template['date'], (int) $id ) > array( $templates[ $best ]['date'], $best ) ) {
				$best = (int) $id;
			}
		}
		return $best ? self::repair( $best, $templates[ $best ] ) : array(
			'id'         => 0,
			'restore'    => false,
			'conditions' => false,
		);
	}

	/**
	 * What an adopted template needs.
	 *
	 * @param int                  $id       Id.
	 * @param array<string, mixed> $template Template.
	 * @return array{id: int, restore: bool, conditions: bool}
	 */
	private static function repair( int $id, array $template ): array {
		return array(
			'id'         => $id,
			'restore'    => 'publish' !== $template['status'],
			'conditions' => array( self::ENTIRE_SITE ) !== array_values( (array) $template['conditions'] ),
		);
	}
}
