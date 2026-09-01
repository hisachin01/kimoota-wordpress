<?php
/**
 * Plugin Name: Simple Admin Wallpaper
 * Description: 管理画面とログイン画面に任意の背景画像を設定するMUプラグイン。
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const SAW_OPTION = 'simple_admin_wallpaper_options';

function saw_get_options() {
	$defaults = [
		'admin_bg'       => '',
		'login_bg'       => '',
		'admin_opacity'  => '0.92',
		'login_opacity'  => '0.92',
	];

	return wp_parse_args( get_option( SAW_OPTION, [] ), $defaults );
}

add_action( 'admin_menu', function() {
	add_options_page(
		'管理画面壁紙',
		'管理画面壁紙',
		'manage_options',
		'simple-admin-wallpaper',
		'saw_settings_page'
	);
});

add_action( 'admin_init', function() {
	register_setting( 'saw_settings', SAW_OPTION, [
		'type'              => 'array',
		'sanitize_callback' => 'saw_sanitize_options',
		'default'           => [],
	]);
});

function saw_sanitize_options( $input ) {
	return [
		'admin_bg'      => isset( $input['admin_bg'] ) ? esc_url_raw( $input['admin_bg'] ) : '',
		'login_bg'      => isset( $input['login_bg'] ) ? esc_url_raw( $input['login_bg'] ) : '',
		'admin_opacity' => isset( $input['admin_opacity'] ) ? saw_sanitize_opacity( $input['admin_opacity'] ) : '0.92',
		'login_opacity' => isset( $input['login_opacity'] ) ? saw_sanitize_opacity( $input['login_opacity'] ) : '0.92',
	];
}

function saw_sanitize_opacity( $value ) {
	$value = (float) $value;
	if ( $value < 0.3 ) $value = 0.3;
	if ( $value > 1 ) $value = 1;
	return (string) $value;
}

function saw_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	$options = saw_get_options();
	?>
	<div class="wrap">
		<h1>管理画面壁紙</h1>

		<form method="post" action="options.php">
			<?php settings_fields( 'saw_settings' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">管理画面の背景画像URL</th>
					<td>
						<input type="url" name="<?php echo esc_attr( SAW_OPTION ); ?>[admin_bg]" value="<?php echo esc_attr( $options['admin_bg'] ); ?>" class="regular-text" placeholder="https://example.com/image.webp">
					</td>
				</tr>

				<tr>
					<th scope="row">管理画面の透明度</th>
					<td>
						<input type="number" step="0.01" min="0.3" max="1" name="<?php echo esc_attr( SAW_OPTION ); ?>[admin_opacity]" value="<?php echo esc_attr( $options['admin_opacity'] ); ?>">
						<p class="description">0.3〜1。数字が小さいほど背景が見えます。</p>
					</td>
				</tr>

				<tr>
					<th scope="row">ログイン画面の背景画像URL</th>
					<td>
						<input type="url" name="<?php echo esc_attr( SAW_OPTION ); ?>[login_bg]" value="<?php echo esc_attr( $options['login_bg'] ); ?>" class="regular-text" placeholder="https://example.com/login.webp">
					</td>
				</tr>

				<tr>
					<th scope="row">ログインフォームの透明度</th>
					<td>
						<input type="number" step="0.01" min="0.3" max="1" name="<?php echo esc_attr( SAW_OPTION ); ?>[login_opacity]" value="<?php echo esc_attr( $options['login_opacity'] ); ?>">
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

add_action( 'admin_head', function() {
	$options = saw_get_options();

	if ( empty( $options['admin_bg'] ) ) return;
	?>
	<style>
		body.wp-admin #wpwrap {
			background-image: url('<?php echo esc_url( $options['admin_bg'] ); ?>') !important;
			background-position: center center !important;
			background-repeat: no-repeat !important;
			background-attachment: fixed !important;
			background-size: cover !important;
		}

		body.wp-admin #wpbody-content > .wrap,
		body.wp-admin .postbox,
		body.wp-admin .notice,
		body.wp-admin .metabox-holder,
		body.wp-admin #screen-meta,
		body.wp-admin #contextual-help-wrap {
			background-color: rgba(255,255,255,<?php echo esc_attr( $options['admin_opacity'] ); ?>);
		}
	</style>
	<?php
});

add_action( 'login_head', function() {
	$options = saw_get_options();

	if ( empty( $options['login_bg'] ) ) return;
	?>
	<style>
		body.login {
			background-image: url('<?php echo esc_url( $options['login_bg'] ); ?>') !important;
			background-position: center center !important;
			background-repeat: no-repeat !important;
			background-attachment: fixed !important;
			background-size: cover !important;
		}

		body.login #loginform,
		body.login #lostpasswordform,
		body.login #registerform {
			background-color: rgba(255,255,255,<?php echo esc_attr( $options['login_opacity'] ); ?>);
		}
	</style>
	<?php
});