<?php
/**
 * "Your profile" for Editors.
 *
 * @package FavrSites
 *
 * @var array<string, mixed> $view View model from FavrSites\Profile\ProfilePage::maybeTakeOver().
 */

defined( 'ABSPATH' ) || exit;

/** @var \WP_User $favr_user */
$favr_user   = $view['user'];
$favr_errors = $view['errors'];
$favr_email  = is_array( $view['new_email'] ) ? (string) ( $view['new_email']['newemail'] ?? '' ) : '';
?>
<div class="wrap favr-dash favr-profile">
	<header class="favr-profile__head">
		<?php echo get_avatar( $favr_user->ID, 72, '', '', array( 'class' => 'favr-profile__avatar' ) ); ?>
		<div>
			<h1 class="favr-profile__title"><?php esc_html_e( 'Your profile', 'favr-sites' ); ?></h1>
			<p class="favr-profile__sub">
				<?php
				/* translators: %s: username. */
				printf( esc_html__( 'You sign in as %s', 'favr-sites' ), '<strong>' . esc_html( $favr_user->user_login ) . '</strong>' );
				?>
				· <a href="https://gravatar.com/" target="_blank" rel="noopener"><?php esc_html_e( 'Change your photo at Gravatar', 'favr-sites' ); ?></a>
			</p>
		</div>
	</header>

	<?php if ( $view['updated'] ) : ?>
		<div class="favr-profile__notice is-success" role="status"><?php esc_html_e( 'Profile updated.', 'favr-sites' ); ?></div>
	<?php endif; ?>
	<?php if ( $favr_errors instanceof \WP_Error && $favr_errors->has_errors() ) : ?>
		<div class="favr-profile__notice is-error" role="alert">
			<?php foreach ( $favr_errors->get_error_messages() as $favr_message ) : ?>
				<p><?php echo wp_kses( $favr_message, array( 'strong' => array() ) ); ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'profile.php' ) ); ?>" class="favr-profile__form" novalidate="novalidate">
		<?php wp_nonce_field( 'update-user_' . $favr_user->ID ); ?>
		<input type="hidden" name="action" value="update">
		<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $favr_user->ID ); ?>">
		<input type="hidden" name="checkuser_id" value="<?php echo esc_attr( (string) get_current_user_id() ); ?>">
		<input type="hidden" name="from" value="profile">

		<section class="favr-dash__card" aria-labelledby="favr-profile-you">
			<h2 id="favr-profile-you"><?php esc_html_e( 'You', 'favr-sites' ); ?></h2>
			<div class="favr-profile__row">
				<p class="favr-profile__field">
					<label for="first_name"><?php esc_html_e( 'First name', 'favr-sites' ); ?></label>
					<input type="text" name="first_name" id="first_name" value="<?php echo esc_attr( $favr_user->first_name ); ?>" autocomplete="given-name">
				</p>
				<p class="favr-profile__field">
					<label for="last_name"><?php esc_html_e( 'Last name', 'favr-sites' ); ?></label>
					<input type="text" name="last_name" id="last_name" value="<?php echo esc_attr( $favr_user->last_name ); ?>" autocomplete="family-name">
				</p>
			</div>
			<p class="favr-profile__field">
				<label for="email"><?php esc_html_e( 'Email', 'favr-sites' ); ?></label>
				<input type="email" name="email" id="email" value="<?php echo esc_attr( $favr_user->user_email ); ?>" autocomplete="email" required>
				<?php if ( '' !== $favr_email && $favr_email !== $favr_user->user_email ) : ?>
					<span class="favr-profile__hint">
						<?php
						/* translators: %s: pending new email address. */
						printf( esc_html__( 'Waiting for you to confirm %s. Check that inbox for the link.', 'favr-sites' ), '<strong>' . esc_html( $favr_email ) . '</strong>' );
						?>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'profile.php?dismiss=' . $favr_user->ID . '_new_email' ), 'dismiss-' . $favr_user->ID . '_new_email' ) ); ?>"><?php esc_html_e( 'Cancel', 'favr-sites' ); ?></a>
					</span>
				<?php else : ?>
					<span class="favr-profile__hint"><?php esc_html_e( 'If you change it, we’ll email the new address to confirm it first.', 'favr-sites' ); ?></span>
				<?php endif; ?>
			</p>
		</section>

		<section class="favr-dash__card" aria-labelledby="favr-profile-password">
			<h2 id="favr-profile-password"><?php esc_html_e( 'Password', 'favr-sites' ); ?></h2>
			<div class="favr-profile__row">
				<p class="favr-profile__field">
					<label for="pass1"><?php esc_html_e( 'New password', 'favr-sites' ); ?></label>
					<input type="password" name="pass1" id="pass1" value="" autocomplete="new-password" spellcheck="false">
				</p>
				<p class="favr-profile__field">
					<label for="pass2"><?php esc_html_e( 'Confirm new password', 'favr-sites' ); ?></label>
					<input type="password" name="pass2" id="pass2" value="" autocomplete="new-password" spellcheck="false">
				</p>
			</div>
			<span class="favr-profile__hint"><?php esc_html_e( 'Leave both blank to keep your current password.', 'favr-sites' ); ?></span>
		</section>

		<?php if ( $view['schemes'] ) : ?>
			<section class="favr-dash__card" aria-labelledby="favr-profile-colours">
				<h2 id="favr-profile-colours"><?php esc_html_e( 'Colour scheme', 'favr-sites' ); ?></h2>
				<fieldset class="favr-swatches">
					<legend class="screen-reader-text"><?php esc_html_e( 'Colour scheme', 'favr-sites' ); ?></legend>
					<?php foreach ( $view['schemes'] as $favr_scheme ) : ?>
						<?php
						$favr_colors = $favr_scheme['colors'] + array_fill( 0, 4, '#ccc' );
						$favr_style  = sprintf( '--c1:%s;--c2:%s;--c3:%s;--c4:%s', $favr_colors[0], $favr_colors[1], $favr_colors[2], $favr_colors[3] );
						?>
						<label class="favr-swatch" title="<?php echo esc_attr( $favr_scheme['name'] ); ?>">
							<input type="radio" name="admin_color" value="<?php echo esc_attr( $favr_scheme['slug'] ); ?>" <?php checked( $favr_scheme['current'] ); ?>>
							<span class="favr-swatch__dot" style="<?php echo esc_attr( $favr_style ); ?>" aria-hidden="true"></span>
							<span class="favr-swatch__name"><?php echo esc_html( $favr_scheme['name'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
			</section>
		<?php endif; ?>

		<p class="favr-profile__actions">
			<button type="submit" class="favr-profile__save"><?php esc_html_e( 'Save changes', 'favr-sites' ); ?></button>
		</p>
	</form>
</div>
