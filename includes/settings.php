<?php
/**
 * The plugin's one setting, shown on Settings > Media: whether deleting
 * the plugin should also delete its documents and folders (see
 * uninstall.php). Off by default, so removing the plugin never takes
 * anyone's document library with it by surprise.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function mulino_register_settings() {
	register_setting(
		'media',
		'mulino_delete_data_on_uninstall',
		array(
			'type'              => 'boolean',
			'default'           => false,
			'sanitize_callback' => 'rest_sanitize_boolean',
		)
	);

	add_settings_section(
		'mulino_settings',
		__( 'Mulino file show', 'mulino-file-show' ),
		'__return_false',
		'media'
	);

	add_settings_field(
		'mulino_delete_data_on_uninstall',
		__( 'When the plugin is deleted', 'mulino-file-show' ),
		'mulino_render_delete_data_field',
		'media',
		'mulino_settings',
		array( 'label_for' => 'mulino_delete_data_on_uninstall' )
	);
}
add_action( 'admin_init', 'mulino_register_settings' );

function mulino_render_delete_data_field() {
	?>
	<label for="mulino_delete_data_on_uninstall">
		<input type="checkbox" id="mulino_delete_data_on_uninstall" name="mulino_delete_data_on_uninstall" value="1" <?php checked( (bool) get_option( 'mulino_delete_data_on_uninstall', false ) ); ?> />
		<?php esc_html_e( 'Also delete all documents and folders', 'mulino-file-show' ); ?>
	</label>
	<p class="description"><?php esc_html_e( 'The uploaded files themselves stay in the Media Library. Deactivating the plugin never deletes anything.', 'mulino-file-show' ); ?></p>
	<?php
}
