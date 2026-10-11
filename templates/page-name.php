<?php
/**
 * "Add page" and "Rename page": the small screens Editors get for a page's name and address.
 *
 * @package FavrSites
 *
 * @var array<string, mixed> $view View model from FavrSites\Editors\Pages::screen().
 */

use FavrSites\Editors\Wayfinding;

defined( 'ABSPATH' ) || exit;

echo Wayfinding::html( (string) $view['back'], (string) $view['cancel'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in html().
?>
<div class="wrap favr-page-name">
	<h1><?php echo esc_html( (string) $view['heading'] ); ?></h1>
	<?php if ( '' !== $view['intro'] ) : ?>
		<p class="favr-page-name__intro"><?php echo esc_html( (string) $view['intro'] ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $view['problem'] ) : ?>
		<div class="notice notice-error inline" role="alert"><p><?php echo esc_html( (string) $view['problem'] ); ?></p></div>
	<?php endif; ?>

	<form class="card favr-page-name__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( (string) $view['action'] ); ?>">
		<?php wp_nonce_field( (string) $view['nonce'] ); ?>
		<?php if ( $view['post'] ) : ?>
			<input type="hidden" name="post" value="<?php echo esc_attr( (string) $view['post'] ); ?>">
		<?php endif; ?>

		<div class="favr-page-name__field">
			<label for="favr-page-name"><?php esc_html_e( 'Name', 'favr-sites' ); ?></label>
			<input type="text" id="favr-page-name" name="page_name" value="<?php echo esc_attr( (string) $view['name'] ); ?>" maxlength="<?php echo esc_attr( (string) FavrSites\Editors\Pages::NAME_MAX ); ?>" autocomplete="off" required autofocus aria-describedby="favr-page-name-help">
			<p class="description" id="favr-page-name-help"><?php echo esc_html( $view['post'] ? __( 'Shown at the top of the page and in the Pages list.', 'favr-sites' ) : __( 'Shown at the top of the page and in the Pages list. For example: About us.', 'favr-sites' ) ); ?></p>
		</div>

		<?php if ( null !== $view['address'] ) : ?>
			<div class="favr-page-name__field">
				<label for="favr-page-address"><?php esc_html_e( 'Address', 'favr-sites' ); ?></label>
				<div class="favr-page-name__address">
					<span class="favr-page-name__prefix"><?php echo esc_html( (string) $view['prefix'] ); ?></span>
					<input type="text" id="favr-page-address" name="page_address" value="<?php echo esc_attr( (string) $view['address'] ); ?>" autocomplete="off" autocapitalize="none" spellcheck="false" aria-describedby="favr-page-address-help">
				</div>
				<p class="description" id="favr-page-address-help"><?php esc_html_e( 'The last part of the page’s web address. Links to the old address stop working when you change it.', 'favr-sites' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="favr-page-name__actions">
			<button type="submit" class="button button-primary button-large"><?php echo esc_html( (string) $view['submit'] ); ?></button>
			<a class="favr-page-name__cancel" href="<?php echo esc_url( (string) $view['cancel'] ); ?>"><?php esc_html_e( 'Cancel', 'favr-sites' ); ?></a>
		</div>
	</form>
</div>
