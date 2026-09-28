<?php
/**
 * Uninstall: the only stored data is the help-links option.
 *
 * @package FavrSites
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'favr_sites_help' );
