<?php
/**
 * Frontend [mfs_documents] shortcode: breadcrumb, subfolder
 * grid, document grid, and the styling for the public-facing browser.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * -----------------------------------------------------------------
 * 4. FRONTEND SHORTCODE: [mfs_documents]
 * -----------------------------------------------------------------
 * Renders the current folder's subfolders + documents, with a
 * breadcrumb trail built from the taxonomy's own parent/child data.
 * No filesystem paths are ever read from user input, only a term
 * slug that is looked up against the mfs_folder taxonomy -- so
 * there's no path-traversal surface here.
 */
function mfs_shortcode() {
	$taxonomy = 'mfs_folder';

	// Sanitize + validate the requested folder slug. This is read-only
	// display filtering (which folder to show), not a state-changing
	// action, so nonce verification doesn't apply here the way it
	// would for a form submission.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$requested_slug = isset( $_GET['mfs_folder'] ) ? sanitize_title( wp_unslash( $_GET['mfs_folder'] ) ) : '';
	$current_term   = $requested_slug ? get_term_by( 'slug', $requested_slug, $taxonomy ) : false;

	ob_start();
	?>
	<div class="mfs-browser">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value is escaped individually inside this function before being concatenated into the returned HTML string.
		echo mfs_render_breadcrumb( $current_term, $taxonomy );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value is escaped individually inside this function before being concatenated into the returned HTML string.
		echo mfs_render_subfolders( $current_term, $taxonomy );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value is escaped individually inside this function before being concatenated into the returned HTML string.
		echo mfs_render_documents( $current_term, $taxonomy );
		?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'mfs_documents', 'mfs_shortcode' );

function mfs_render_breadcrumb( $current_term, $taxonomy ) {
	$base_url = remove_query_arg( 'mfs_folder' );
	$crumbs   = array( '<a href="' . esc_url( $base_url ) . '">' . esc_html__( 'Home', 'mulino-file-show' ) . '</a>' );

	if ( $current_term && ! is_wp_error( $current_term ) ) {
		$ancestors = array_reverse( get_ancestors( $current_term->term_id, $taxonomy, 'taxonomy' ) );
		foreach ( $ancestors as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, $taxonomy );
			if ( $ancestor && ! is_wp_error( $ancestor ) ) {
				$url      = add_query_arg( 'mfs_folder', $ancestor->slug, $base_url );
				$crumbs[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $ancestor->name ) . '</a>';
			}
		}
		$crumbs[] = '<span class="mfs-current">' . esc_html( $current_term->name ) . '</span>';
	}

	return '<nav class="mfs-breadcrumb">' . implode( ' &raquo; ', $crumbs ) . '</nav>';
}

function mfs_render_subfolders( $current_term, $taxonomy ) {
	$parent_id = $current_term ? $current_term->term_id : 0;

	$subfolders = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'parent'     => $parent_id,
			'hide_empty' => false,
		)
	);

	if ( is_wp_error( $subfolders ) || empty( $subfolders ) ) {
		return '';
	}

	$base_url = remove_query_arg( 'mfs_folder' );
	$out      = '<div class="mfs-grid">';
	foreach ( $subfolders as $folder ) {
		$url    = add_query_arg( 'mfs_folder', $folder->slug, $base_url );
		$out   .= '<a class="mfs-card mfs-card--folder" href="' . esc_url( $url ) . '">'
				. mfs_folder_icon_svg()
				. '<span class="mfs-name">' . esc_html( $folder->name ) . '</span>'
				. '</a>';
	}
	$out .= '</div>';

	return $out;
}

function mfs_folder_icon_svg() {
	return '<svg class="mfs-icon" viewBox="0 0 56 44" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
		<path d="M2 7a4 4 0 0 1 4-4h13l4 5h27a4 4 0 0 1 4 4v27a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4Z" fill="#ffcf5c" stroke="#e0a52e" stroke-width="1.5"/>
		<path d="M2 14h52v22a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4Z" fill="#ffe08a" stroke="#e0a52e" stroke-width="1.5"/>
	</svg>';
}

function mfs_render_documents( $current_term, $taxonomy ) {
	// At the root (no folder selected) we don't list documents that
	// might be attached directly to top-level terms only -- adjust
	// this if you want root-level "loose" documents too.
	if ( ! $current_term ) {
		return '';
	}

	$documents = get_posts(
		array(
			'post_type'      => 'mfs_document',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			// A tax_query is inherently scoped to one specific folder
			// term here (not an open-ended query), so this stays fast
			// even on a large document library.
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			'tax_query'      => array(
				array(
					'taxonomy'         => $taxonomy,
					'field'            => 'term_id',
					'terms'            => $current_term->term_id,
					'include_children' => false,
				),
			),
		)
	);

	if ( empty( $documents ) ) {
		// Only show the "empty" message if this folder is completely
		// empty -- if it has subfolders, those are enough to look at,
		// and this message would just be visual noise underneath them.
		$subfolder_count = wp_count_terms(
			array(
				'taxonomy'   => $taxonomy,
				'parent'     => $current_term->term_id,
				'hide_empty' => false,
			)
		);
		if ( ! is_wp_error( $subfolder_count ) && $subfolder_count > 0 ) {
			return '';
		}
		return '<p class="mfs-empty">' . esc_html__( 'No documents in this folder.', 'mulino-file-show' ) . '</p>';
	}

	$out = '<div class="mfs-grid">';
	foreach ( $documents as $doc ) {
		$attachment_id = (int) get_post_meta( $doc->ID, '_mfs_file_id', true );
		if ( ! $attachment_id ) {
			continue;
		}
		$url = wp_get_attachment_url( $attachment_id );
		if ( ! $url ) {
			continue;
		}
		$icon = mfs_get_file_type( $url );
		$out .= '<a class="mfs-card mfs-card--file" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">'
				. mfs_file_icon_svg( $icon['label'], $icon['color'] )
				. '<span class="mfs-name">' . esc_html( get_the_title( $doc ) ) . '</span>'
				. '</a>';
	}
	$out .= '</div>';

	return $out;
}

/**
 * -----------------------------------------------------------------
 * 5. FRONTEND STYLESHEET
 * -----------------------------------------------------------------
 * Enqueued unconditionally on the frontend: the file is tiny, and
 * this is simpler and more reliable than trying to detect shortcode
 * usage before wp_enqueue_scripts runs (shortcodes render later).
 */
function mfs_enqueue_frontend_styles() {
	wp_enqueue_style(
		'mdl-frontend',
		MFS_URL . 'assets/css/frontend.css',
		array(),
		MFS_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'mfs_enqueue_frontend_styles' );
