<?php
/**
 * Keeps every Favr site's foundations in place.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Site;

use FavrSites\Menus\Slots;

/**
 * On an Administrator's wp-admin visit: snapshot the site, plan, and apply only what's missing or
 * broken (create Home/News/Header/Footer, set Reading, republish, reset conditions, record ids).
 */
final class Setup {

	/** Hooks. */
	public function hook(): void {
		add_action( 'admin_init', array( $this, 'maybeRun' ), 20 ); // After Slots::ensureDefaults (10).
	}

	/** Administrators only, never during AJAX; a failure is logged, never shown as a broken wp-admin. */
	public function maybeRun(): void {
		if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		try {
			self::run();
		} catch ( \Throwable $e ) {
			error_log( 'Favr Sites: site foundations check failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- must not break wp-admin.
		}
	}

	/** Plan and apply. */
	public static function run(): void {
		$state = self::state();
		$plan  = Foundations::plan( $state );
		if ( Foundations::settled( $plan, $state ) ) {
			return;
		}

		$home = $plan['home']['id'] ? $plan['home']['id'] : self::createHome();
		$news = $plan['news']['id'] ? $plan['news']['id'] : self::createPage( __( 'News', 'favr-sites' ) );
		foreach ( array(
			$home => $plan['home']['publish'],
			$news => $plan['news']['publish'],
		) as $id => $publish ) {
			if ( $id && $publish ) {
				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'publish',
					)
				);
			}
		}
		if ( $home && $news ) {
			self::setOption( 'show_on_front', 'page' );
			self::setOption( 'page_on_front', $home );
			self::setOption( 'page_for_posts', $news );
		}

		$recorded = (array) get_option( Foundations::OPTION, array() );
		foreach ( Foundations::ROLES as $role ) {
			$decision = $plan[ $role ];
			if ( null === $decision || ! self::pro() ) {
				continue;
			}
			$id = $decision['id'] ? $decision['id'] : self::createTemplate( $role );
			if ( ! $id ) {
				continue;
			}
			if ( $decision['restore'] ) {
				if ( 'trash' === get_post_status( $id ) ) {
					wp_untrash_post( $id );
				}
				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'publish',
					)
				);
			}
			if ( $decision['conditions'] || ! $decision['id'] ) {
				self::entireSite( $id );
			}
			$recorded[ $role ] = $id;
		}
		self::setOption( Foundations::OPTION, $recorded );
	}

	/**
	 * Snapshot for Foundations::plan().
	 *
	 * @return array<string, mixed>
	 */
	public static function state(): array {
		global $wpdb;
		$front = (int) get_option( 'page_on_front' );
		$posts = (int) get_option( 'page_for_posts' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- admin-only, two small queries.
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_name, post_status FROM {$wpdb->posts}
				WHERE post_type = 'page' AND post_status NOT IN ('auto-draft', 'inherit')
				AND ( ID IN (%d, %d) OR post_name IN ('home', 'news') OR LOWER(post_title) IN ('home', 'news') )",
				$front,
				$posts
			)
		);
		$pages = array();
		foreach ( $rows as $row ) {
			$pages[ (int) $row->ID ] = array(
				'status'    => (string) $row->post_status,
				'title'     => (string) $row->post_title,
				'slug'      => (string) $row->post_name,
				'elementor' => 'builder' === get_post_meta( (int) $row->ID, '_elementor_edit_mode', true ),
			);
		}

		$templates = array();
		if ( self::pro() ) {
			$rows = $wpdb->get_results(
				"SELECT p.ID, p.post_status, p.post_date, m.meta_value AS type FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_template_type' AND m.meta_value IN ('header', 'footer')
				WHERE p.post_type = 'elementor_library' AND p.post_status NOT IN ('auto-draft', 'inherit')"
			);
			foreach ( $rows as $row ) {
				$templates[ (int) $row->ID ] = array(
					'type'       => (string) $row->type,
					'status'     => (string) $row->post_status,
					'conditions' => array_values( (array) get_post_meta( (int) $row->ID, '_elementor_conditions', true ) ),
					'date'       => (string) $row->post_date,
				);
			}
		}
		// phpcs:enable

		return array(
			'show_on_front'  => (string) get_option( 'show_on_front' ),
			'page_on_front'  => $front,
			'page_for_posts' => $posts,
			'pages'          => $pages,
			'pro'            => self::pro(),
			'recorded'       => array_map( 'intval', (array) get_option( Foundations::OPTION, array() ) ),
			'templates'      => $templates,
		);
	}

	/** Elementor Pro's Theme Builder is available. */
	public static function pro(): bool {
		return did_action( 'elementor/loaded' ) && class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' );
	}

	/** A new Home: an Elementor page (full width) when Elementor runs, else a plain page. */
	private static function createHome(): int {
		if ( ! did_action( 'elementor/loaded' ) ) {
			return self::createPage( __( 'Home', 'favr-sites' ) );
		}
		$document = \Elementor\Plugin::$instance->documents->create(
			'wp-page',
			array(
				'post_title'  => __( 'Home', 'favr-sites' ),
				'post_status' => 'publish',
			),
			array( '_wp_page_template' => 'elementor_header_footer' )
		);
		if ( is_wp_error( $document ) ) {
			return 0;
		}
		$document->save( array( 'elements' => Starter::home( get_bloginfo( 'name' ), get_bloginfo( 'description' ) ) ) );
		return (int) $document->get_main_id();
	}

	/**
	 * A plain published page.
	 *
	 * @param string $title Title.
	 */
	private static function createPage( string $title ): int {
		$id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_title'  => $title,
				'post_status' => 'publish',
			),
			true
		);
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/**
	 * A starter Header or Footer template.
	 *
	 * @param string $role header|footer.
	 */
	private static function createTemplate( string $role ): int {
		$document = \Elementor\Plugin::$instance->documents->create(
			$role,
			array(
				'post_title'  => 'footer' === $role ? __( 'Footer', 'favr-sites' ) : __( 'Header', 'favr-sites' ),
				'post_status' => 'publish',
			)
		);
		if ( is_wp_error( $document ) ) {
			return 0;
		}
		$site     = get_bloginfo( 'name' );
		$elements = 'footer' === $role
			? Starter::footer( self::menuSlug( 'footer' ), $site, self::yearTag( $site ), (int) wp_date( 'Y' ) )
			: Starter::header( (bool) get_theme_mod( 'custom_logo' ), self::menuSlug( 'header' ), self::tag( 'site-title' ), self::tag( 'site-url' ) );
		$document->save( array( 'elements' => $elements ) );
		return (int) $document->get_main_id();
	}

	/**
	 * The slug of the menu connected to a slot ('' when none).
	 *
	 * @param string $slot header|footer.
	 */
	private static function menuSlug( string $slot ): string {
		$id   = Slots::current()[ $slot ] ?? null;
		$menu = $id ? wp_get_nav_menu_object( $id ) : false;
		return $menu instanceof \WP_Term ? (string) $menu->slug : '';
	}

	/**
	 * "© {year} {site}" as Elementor's current-date tag (so the year stays current).
	 *
	 * @param string $site Site name.
	 */
	private static function yearTag( string $site ): string {
		return self::tag(
			'current-date-time',
			array(
				'date_format'   => 'custom',
				'custom_format' => 'Y',
				'before'        => '© ',
				'after'         => ' ' . $site,
			)
		);
	}

	/**
	 * An Elementor dynamic tag as stored in a setting ('' when tags aren't available).
	 *
	 * @param string               $name     Tag name.
	 * @param array<string, mixed> $settings Tag settings.
	 */
	private static function tag( string $name, array $settings = array() ): string {
		$tags = \Elementor\Plugin::$instance->dynamic_tags ?? null;
		if ( ! $tags || ! method_exists( $tags, 'tag_data_to_tag_text' ) ) {
			return '';
		}
		return (string) $tags->tag_data_to_tag_text( substr( md5( 'favr-' . $name ), 0, 7 ), $name, $settings );
	}

	/**
	 * Display conditions: Entire Site only.
	 *
	 * @param int $id Template.
	 */
	private static function entireSite( int $id ): void {
		\ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->save_conditions(
			$id,
			array(
				array(
					'type' => 'include',
					'name' => 'general',
				),
			)
		);
	}

	/**
	 * Update an option only when it differs.
	 *
	 * @param string $key   Option.
	 * @param mixed  $value Value.
	 */
	private static function setOption( string $key, $value ): void {
		if ( get_option( $key ) != $value ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- options come back as strings.
			update_option( $key, $value );
		}
	}
}
