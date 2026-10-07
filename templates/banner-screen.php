<?php
/**
 * Alert banner: the Editors' screen.
 *
 * @package FavrSites
 *
 * @var array<string, mixed> $view View model from FavrSites\Banner\Screen::render().
 */

defined( 'ABSPATH' ) || exit;

$favr_banner = $view['banner'];
?>
<div class="wrap favr-dash favr-banner-admin">
	<header class="favr-banner-admin__head">
		<h1 class="favr-banner-admin__title"><?php esc_html_e( 'Alert banner', 'favr-sites' ); ?></h1>
		<p class="favr-banner-admin__sub"><?php esc_html_e( 'One message across the top of every page. Switch it on when you need it.', 'favr-sites' ); ?></p>
	</header>

	<?php if ( $view['saved'] ) : ?>
		<div class="favr-banner-admin__notice is-success" role="status"><?php esc_html_e( 'Banner saved.', 'favr-sites' ); ?></div>
	<?php endif; ?>
	<?php if ( $view['warnings'] ) : ?>
		<div class="favr-banner-admin__notice is-error" role="alert">
			<?php foreach ( $view['warnings'] as $favr_warning ) : ?>
				<p><?php echo esc_html( (string) $favr_warning ); ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<section class="favr-dash__card favr-banner-admin__card">
		<p class="favr-banner-admin__status" data-state="<?php echo esc_attr( $view['state'] ); ?>"><?php echo esc_html( $view['status'] ); ?></p>
	</section>

	<form class="favr-dash__card favr-banner-admin__card favr-banner-admin__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="<?php echo esc_attr( $view['action'] ); ?>">
		<?php wp_nonce_field( $view['action'] ); ?>

		<label class="favr-banner-admin__check">
			<input type="checkbox" name="banner[enabled]" value="1" <?php checked( $favr_banner->enabled() ); ?>>
			<span><?php esc_html_e( 'Show the banner', 'favr-sites' ); ?></span>
		</label>

		<label class="favr-banner-admin__field">
			<span class="favr-banner-admin__label"><?php esc_html_e( 'Message', 'favr-sites' ); ?></span>
			<textarea name="banner[message]" rows="2" maxlength="200"><?php echo esc_textarea( $favr_banner->message() ); ?></textarea>
			<span class="favr-banner-admin__help"><?php esc_html_e( 'Plain text, up to 200 characters.', 'favr-sites' ); ?></span>
		</label>

		<div class="favr-banner-admin__row">
			<label class="favr-banner-admin__field">
				<span class="favr-banner-admin__label"><?php esc_html_e( 'Link label (optional)', 'favr-sites' ); ?></span>
				<input type="text" name="banner[link_label]" maxlength="40" value="<?php echo esc_attr( $favr_banner->linkLabel() ); ?>">
			</label>
			<label class="favr-banner-admin__field">
				<span class="favr-banner-admin__label"><?php esc_html_e( 'Link address (optional)', 'favr-sites' ); ?></span>
				<input type="text" name="banner[link_url]" value="<?php echo esc_attr( $favr_banner->linkUrl() ); ?>" placeholder="<?php esc_attr_e( 'https://… or /page/', 'favr-sites' ); ?>">
			</label>
		</div>

		<fieldset class="favr-banner-admin__field">
			<legend class="favr-banner-admin__label"><?php esc_html_e( 'Style', 'favr-sites' ); ?></legend>
			<label class="favr-banner-admin__check">
				<input type="radio" name="banner[style]" value="standard" <?php checked( $favr_banner->style(), 'standard' ); ?>>
				<span><?php esc_html_e( 'Standard (your site’s main colour)', 'favr-sites' ); ?></span>
			</label>
			<label class="favr-banner-admin__check">
				<input type="radio" name="banner[style]" value="urgent" <?php checked( $favr_banner->style(), 'urgent' ); ?>>
				<span><?php esc_html_e( 'Urgent (red)', 'favr-sites' ); ?></span>
			</label>
		</fieldset>

		<div class="favr-banner-admin__row">
			<label class="favr-banner-admin__field">
				<span class="favr-banner-admin__label"><?php esc_html_e( 'Show from (optional)', 'favr-sites' ); ?></span>
				<input type="datetime-local" name="banner[starts]" value="<?php echo esc_attr( str_replace( ' ', 'T', $favr_banner->starts() ) ); ?>">
			</label>
			<label class="favr-banner-admin__field">
				<span class="favr-banner-admin__label"><?php esc_html_e( 'Hide after (optional)', 'favr-sites' ); ?></span>
				<input type="datetime-local" name="banner[ends]" value="<?php echo esc_attr( str_replace( ' ', 'T', $favr_banner->ends() ) ); ?>">
			</label>
		</div>
		<p class="favr-banner-admin__help">
			<?php
			/* translators: %s: time zone, e.g. "America/New_York". */
			echo esc_html( sprintf( __( 'Times are in the site’s time zone (%s).', 'favr-sites' ), $view['zone'] ) );
			?>
		</p>

		<label class="favr-banner-admin__check">
			<input type="checkbox" name="banner[dismissible]" value="1" <?php checked( $favr_banner->dismissible() ); ?>>
			<span><?php esc_html_e( 'Visitors can close it', 'favr-sites' ); ?></span>
		</label>

		<div class="favr-banner-admin__actions">
			<button type="submit" class="favr-banner-admin__save"><?php esc_html_e( 'Save banner', 'favr-sites' ); ?></button>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View site', 'favr-sites' ); ?></a>
		</div>
	</form>
</div>
