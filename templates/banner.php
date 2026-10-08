<?php
/**
 * The alert banner as visitors see it (also the preview on the Alert banner screen).
 *
 * @package FavrSites
 *
 * @var FavrSites\Banner\Banner            $banner  Banner.
 * @var string                             $state   live|pending.
 * @var array{bg: string, fg: string}      $colors  Background and text colours.
 * @var array{from: ?int, until: ?int}     $window  Show and hide times (UTC timestamps).
 * @var bool                               $late    Printed in the footer; the script moves it to the top.
 * @var bool                               $preview Static preview: no script hooks, close button disabled.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="favr-banner favr-banner--<?php echo esc_attr( $banner->style() ); ?>" role="region" aria-label="<?php esc_attr_e( 'Announcement', 'favr-sites' ); ?>"
	<?php if ( ! $preview ) : ?>
		data-favr-banner data-version="<?php echo esc_attr( $banner->version() ); ?>" data-from="<?php echo esc_attr( (string) ( $window['from'] ?? '' ) ); ?>" data-until="<?php echo esc_attr( (string) ( $window['until'] ?? '' ) ); ?>"<?php echo $late ? ' data-late' : ''; ?><?php echo 'pending' === $state ? ' hidden' : ''; ?>
	<?php endif; ?>
	style="--favr-banner-bg:<?php echo esc_attr( $colors['bg'] ); ?>;--favr-banner-fg:<?php echo esc_attr( $colors['fg'] ); ?>">
	<div class="favr-banner__inner">
		<p class="favr-banner__text">
			<?php echo esc_html( $banner->message() ); ?>
			<?php if ( '' !== $banner->linkUrl() ) : ?>
				<a class="favr-banner__link" href="<?php echo esc_url( $banner->linkUrl() ); ?>"><?php echo esc_html( $banner->linkLabel() ); ?></a>
			<?php endif; ?>
		</p>
		<?php if ( $banner->dismissible() ) : ?>
			<button type="button" class="favr-banner__close" aria-label="<?php esc_attr_e( 'Close', 'favr-sites' ); ?>"<?php echo $preview ? ' disabled' : ' hidden'; ?>>&times;</button>
		<?php endif; ?>
	</div>
</div>
