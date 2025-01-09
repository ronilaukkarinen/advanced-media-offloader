<?php
/*
** A settings page to configure the plugin.
**  It should ask to select a cloud storage provider and provide the necessary credentials.
*/

// Exit if accessed directly.
if (!defined('ABSPATH')) {
	exit;
}

// check user capabilities
if (!current_user_can('manage_options')) {
	return;
}

// show error/update messages
settings_errors('advmo_messages');

$options = get_option('advmo_options');
?>
<div id="advmo">
	<div class="wrap">
		<h1><?php esc_attr_e('Advanced Media Offloader', 'advanced-media-offloader'); ?></h1>
		<p><?php esc_attr_e('This plugin allows you to offload your media files to a cloud storage provider.', 'advanced-media-offloader'); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields('advmo'); ?>
			<?php do_settings_sections('advmo'); ?>
			<?php submit_button(); ?>
		</form>
		<?php echo advmo_get_copyright_text(); ?>
	</div>
</div>