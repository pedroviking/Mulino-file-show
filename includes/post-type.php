<?php
/**
 * Post type + hierarchical taxonomy registration, and the
 * "Select File" meta box on the (now hidden) document edit screen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * -----------------------------------------------------------------
 * 1. CUSTOM POST TYPE: one post = one document
 * -----------------------------------------------------------------
 * We use a real post type (not a raw attachment) so we get the
 * standard WP admin list table, Quick Edit, and Bulk Edit for free.
 */
function mfs_register_post_type() {
	register_post_type(
		'mfs_document',
		array(
			'label'              => __( 'Documents', 'mulino-file-show' ),
			'labels'             => array(
				'name'          => __( 'Documents', 'mulino-file-show' ),
				'singular_name' => __( 'Document', 'mulino-file-show' ),
				'add_new_item'  => __( 'Add New Document', 'mulino-file-show' ),
				'edit_item'     => __( 'Edit Document', 'mulino-file-show' ),
			),
			'public'             => false,      // no single document pages, we render via shortcode
			'show_ui'            => true,
			'show_in_menu'       => false,     // we add our own single top-level "Documents" menu instead
			'menu_icon'          => 'dashicons-media-document',
			'supports'           => array( 'title' ),
			'capability_type'    => 'post',      // reuse normal Editor/Admin capabilities
			'map_meta_cap'       => true,
			'hierarchical'       => false,
			'show_in_rest'       => false,
		)
	);
}
add_action( 'init', 'mfs_register_post_type' );

/**
 * -----------------------------------------------------------------
 * 2. HIERARCHICAL TAXONOMY: the "folders" (Decade > Year)
 * -----------------------------------------------------------------
 * Hierarchical, so WP still manages parent/child relationships,
 * counts, get_ancestors(), etc. for us -- we just don't show its
 * own admin screen, since folder management now happens entirely
 * inside the Document Manager screen.
 */
function mfs_register_taxonomy() {
	register_taxonomy(
		'mfs_folder',
		'mfs_document',
		array(
			'label'             => __( 'Folders', 'mulino-file-show' ),
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_menu'      => false,     // hide the separate "Folders" term-admin screen
			'show_in_quick_edit'=> true,
			'query_var'         => false,
			'rewrite'           => false,
			'show_in_rest'      => false,
		)
	);
}
add_action( 'init', 'mfs_register_taxonomy' );

/**
 * -----------------------------------------------------------------
 * 3. FILE-ATTACH META BOX (admin side)
 * -----------------------------------------------------------------
 * Adds a "Select File" button on the document edit screen that opens
 * the normal WP Media uploader and stores the chosen attachment ID.
 */
function mfs_add_file_meta_box() {
	add_meta_box(
		'mfs_file_box',
		__( 'Document File', 'mulino-file-show' ),
		'mfs_render_file_meta_box',
		'mfs_document',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'mfs_add_file_meta_box' );

function mfs_render_file_meta_box( $post ) {
	wp_nonce_field( 'mfs_save_file', 'mfs_file_nonce' );
	$attachment_id = (int) get_post_meta( $post->ID, '_mfs_file_id', true );
	$file_url      = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
	$file_name     = $attachment_id ? basename( get_attached_file( $attachment_id ) ) : '';
	?>
	<p>
		<button type="button" class="button" id="mfs_select_file"><?php esc_html_e( 'Select File', 'mulino-file-show' ); ?></button>
		<span id="mfs_file_name"><?php echo esc_html( $file_name ); ?></span>
	</p>
	<input type="hidden" name="mfs_file_id" id="mfs_file_id" value="<?php echo esc_attr( $attachment_id ); ?>" />
	<?php if ( $file_url ) : ?>
		<p><a href="<?php echo esc_url( $file_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View current file', 'mulino-file-show' ); ?></a></p>
	<?php endif; ?>
	<?php
}

function mfs_save_file_meta( $post_id ) {
	if ( ! isset( $_POST['mfs_file_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mfs_file_nonce'] ) ), 'mfs_save_file' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( isset( $_POST['mfs_file_id'] ) ) {
		update_post_meta( $post_id, '_mfs_file_id', absint( $_POST['mfs_file_id'] ) );
	}
}
add_action( 'save_post_mfs_document', 'mfs_save_file_meta' );

function mfs_admin_enqueue( $hook ) {
	global $post_type;
	if ( 'mfs_document' === $post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		wp_enqueue_media();

		wp_enqueue_script(
			'mdl-media-picker',
			MFS_URL . 'assets/js/media-picker.js',
			array( 'jquery', 'media-editor' ),
			MFS_VERSION,
			true
		);
		wp_localize_script(
			'mdl-media-picker',
			'mfsMediaPicker',
			array(
				'title'      => __( 'Select or upload a document', 'mulino-file-show' ),
				'buttonText' => __( 'Use this file', 'mulino-file-show' ),
			)
		);
	}
}
add_action( 'admin_enqueue_scripts', 'mfs_admin_enqueue' );
