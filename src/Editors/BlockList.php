<?php
/**
 * A short, hard-to-break block list for News posts.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;

/**
 * Editors writing News get writing blocks only: no patterns, Openverse or code editor.
 */
final class BlockList {

	public const BLOCKS = array(
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/list-item',
		'core/quote',
		'core/image',
		'core/gallery',
		'core/embed',
		'core/buttons',
		'core/button',
		'core/separator',
		'core/table',
		'core/file',
	);

	/**
	 * Allowed blocks for a post type, or null for "leave alone".
	 *
	 * @param string $post_type Post type.
	 * @return list<string>|null
	 */
	public static function blocksFor( string $post_type ): ?array {
		return 'post' === $post_type ? self::BLOCKS : null;
	}

	/**
	 * Editor settings without patterns, Openverse or the code editor.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @return array<string, mixed>
	 */
	public static function trimSettings( array $settings ): array {
		$settings['codeEditingEnabled']                   = false;
		$settings['enableOpenverseMediaCategory']         = false;
		$settings['__experimentalBlockPatterns']          = array();
		$settings['__experimentalBlockPatternCategories'] = array();
		// WP 6.7+ reads these instead; the rest of the library comes over REST (see noPatterns()).
		$settings['__experimentalAdditionalBlockPatterns']          = array();
		$settings['__experimentalAdditionalBlockPatternCategories'] = array();
		return $settings;
	}

	/**
	 * Is this a REST route the editor uses to load patterns?
	 *
	 * @param string $route Route.
	 */
	public static function isPatternRoute( string $route ): bool {
		return str_starts_with( $route, '/wp/v2/block-patterns/' ) || str_starts_with( $route, '/wp/v2/pattern-directory/' );
	}

	/** Hooks. */
	public function hook(): void {
		add_filter( 'allowed_block_types_all', array( $this, 'allowed' ), PHP_INT_MAX, 2 );
		add_filter( 'block_editor_settings_all', array( $this, 'settings' ), PHP_INT_MAX, 2 );
		add_filter( 'should_load_remote_block_patterns', array( $this, 'remotePatterns' ) );
		add_filter( 'rest_pre_dispatch', array( $this, 'noPatterns' ), 10, 3 );
	}

	/**
	 * Editors get an empty pattern library (they only use the block editor for News).
	 *
	 * @param mixed            $result  Short-circuit result.
	 * @param \WP_REST_Server  $server  Server.
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function noPatterns( $result, $server, $request ) {
		if ( null !== $result || ! is_object( $request ) || ! method_exists( $request, 'get_route' ) || ! self::isPatternRoute( (string) $request->get_route() ) || ! Audience::current() ) {
			return $result;
		}
		return new \WP_REST_Response( array(), 200 );
	}

	/**
	 * Allowed blocks.
	 *
	 * @param bool|array<string> $allowed Allowed.
	 * @param mixed              $context \WP_Block_Editor_Context.
	 * @return bool|array<string>
	 */
	public function allowed( $allowed, $context ) {
		$type = self::postType( $context );
		return ( '' !== $type && Audience::current() ) ? ( self::blocksFor( $type ) ?? $allowed ) : $allowed;
	}

	/**
	 * Editor settings.
	 *
	 * @param array<string, mixed> $settings Settings.
	 * @param mixed                $context  \WP_Block_Editor_Context.
	 * @return array<string, mixed>
	 */
	public function settings( $settings, $context ) {
		$type = self::postType( $context );
		return ( null !== self::blocksFor( $type ) && Audience::current() ) ? self::trimSettings( (array) $settings ) : $settings;
	}

	/**
	 * No remote (wordpress.org) patterns for Editors.
	 *
	 * @param bool $load Load.
	 */
	public function remotePatterns( $load ): bool {
		return Audience::current() ? false : (bool) $load;
	}

	/**
	 * Post type from a block editor context.
	 *
	 * @param mixed $context Context.
	 */
	private static function postType( $context ): string {
		return is_object( $context ) && isset( $context->post ) && $context->post instanceof \WP_Post ? $context->post->post_type : '';
	}
}
