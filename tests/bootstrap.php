<?php
/**
 * Unit test bootstrap: pure logic only; WordPress is stubbed with Brain Monkey.
 *
 * @package FavrSites
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! class_exists( 'WP_User' ) ) {
	/** Minimal stand-in for tests. */
	class WP_User {
		/** @var int */
		public $ID;
		/** @var list<string> */
		public $roles;
		public function __construct( int $id = 0, array $roles = array() ) {
			$this->ID    = $id;
			$this->roles = $roles;
		}
		public function exists(): bool {
			return $this->ID > 0;
		}
	}
}
