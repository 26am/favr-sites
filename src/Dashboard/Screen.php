<?php
/**
 * The Favr dashboard view.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Dashboard;

use FavrSites\Editors\Routing;
use FavrSites\Help\Links;
use FavrSites\Site\Protect;
use FavrSites\Site\Setup;

/**
 * Builds the view model and renders templates/dashboard.php.
 */
final class Screen {

	/**
	 * Greeting for an hour of the day (site timezone).
	 *
	 * @param int $hour 0–23.
	 */
	public static function greeting( int $hour ): string {
		if ( $hour >= 5 && $hour < 12 ) {
			return __( 'Good morning', 'favr-sites' );
		}
		if ( $hour >= 12 && $hour < 17 ) {
			return __( 'Good afternoon', 'favr-sites' );
		}
		return __( 'Good evening', 'favr-sites' );
	}

	/** Render. */
	public function render(): void {
		$user    = wp_get_current_user();
		$name    = $user->first_name ?: $user->display_name;
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		$view    = array(
			'site'      => get_bloginfo( 'name' ),
			'site_url'  => home_url( '/' ),
			'logo'      => $logo_id ? (string) wp_get_attachment_image(
				$logo_id,
				'medium',
				false,
				array(
					'class' => 'favr-dash__logo',
					'alt'   => '',
				)
			) : '',
			'icon'      => $logo_id ? '' : (string) get_site_icon_url( 96 ),
			'greeting'  => self::greeting( (int) wp_date( 'G' ) ),
			'name'      => (string) $name,
			'attention' => Attention::current(),
			'review'    => Attention::url(),
			'actions'   => Items::actions( (array) apply_filters( 'favr_sites_quick_actions', self::coreActions() ), 'current_user_can' ),
			'cards'     => Items::cards( (array) apply_filters( 'favr_sites_dashboard_cards', array() ), 'current_user_can' ),
			'activity'  => Activity::recent(),
			'help'      => Links::all(),
		);
		include FAVR_SITES_PATH . 'templates/dashboard.php';
	}

	/**
	 * Actions every site has.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function coreActions(): array {
		$actions   = array(
			array(
				'id'         => 'add-post',
				'label'      => __( 'Add news post', 'favr-sites' ),
				'url'        => admin_url( 'post-new.php' ),
				'capability' => 'edit_posts',
				'icon'       => 'news',
				'priority'   => 10,
			),
			array(
				'id'         => 'edit-pages',
				'label'      => __( 'Edit pages', 'favr-sites' ),
				'url'        => admin_url( 'edit.php?post_type=page' ),
				'capability' => 'edit_pages',
				'icon'       => 'page',
				'priority'   => 90,
			),
		);
		$templates = Setup::pro() ? Protect::ids() : array();
		foreach ( array(
			'header' => __( 'Edit header', 'favr-sites' ),
			'footer' => __( 'Edit footer', 'favr-sites' ),
		) as $role => $label ) {
			if ( isset( $templates[ $role ] ) ) {
				$actions[] = array(
					'id'         => 'edit-' . $role,
					'label'      => $label,
					'url'        => Routing::elementorUrl( $templates[ $role ] ),
					'capability' => 'edit_pages',
					'icon'       => 'page',
					'priority'   => 'header' === $role ? 20 : 21,
				);
			}
		}
		return $actions;
	}
}
