<?php
/**
 * Minimal stand-in for WordPress's screen object, for the tests of the classes that ask which
 * screen they are on (Chrome, Wayfinding).
 *
 * @package FavrSites
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_Screen' ) ) {
	/** Minimal stand-in for tests. */
	class WP_Screen {
		/** @var string */
		public $id = '';
		/** @var string */
		public $base = '';
		/** @var string */
		public $post_type = '';
		/** @var string */
		public $taxonomy = '';
		/** @var bool */
		public $block_editor = false;
		/** @var int */
		public $help_tabs_removed = 0;
		public function __construct( array $props = array() ) {
			foreach ( $props as $key => $value ) {
				$this->$key = $value;
			}
		}
		public function is_block_editor(): bool {
			return (bool) $this->block_editor;
		}
		public function remove_help_tabs(): void {
			++$this->help_tabs_removed;
		}
	}
}
