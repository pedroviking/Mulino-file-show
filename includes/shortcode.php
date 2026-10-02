<?php
/**
 * Frontend [mulino_documents] shortcode: breadcrumb, subfolder
 * grid, document grid, and the styling for the public-facing browser.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * -----------------------------------------------------------------
 * 4. FRONTEND SHORTCODE: [mulino_documents]
 * -----------------------------------------------------------------
 * Renders the current folder's subfolders + documents, with a
 * breadcrumb trail built from the taxonomy's own parent/child data.
 * No filesystem paths are ever read from user input, only a term
 * slug that is looked up against the mulino_folder taxonomy -- so
 * there's no path-traversal surface here.
 *
 * Attributes (all optional):
 *  - folder="slug"         start in this folder instead of the whole library;
 *                          visitors can't browse above it
 *  - orderby="name|date"   how documents are sorted (default: name)
 *  - document_order="asc|desc" document sort direction (default: asc);
 *                          plain order="..." is accepted too
 *  - folder_order="asc|desc" folder sort direction (default: asc), e.g.
 *                          "desc" to show the newest year first
 *  - hide_empty="yes|no"   hide folders with no documents in them or in
 *                          any of their subfolders (default: no)
 */
function mulino_shortcode( $atts = array() ) {
	$taxonomy = 'mulino_folder';
	$args     = mulino_parse_shortcode_atts( $atts );

	$root_term = false;
	if ( '' !== $args['folder'] ) {
		$root_term = get_term_by( 'slug', $args['folder'], $taxonomy );
		if ( ! $root_term || is_wp_error( $root_term ) ) {
			// Only editors see why the browser is missing; visitors just
			// see nothing rather than a half-broken library.
			if ( current_user_can( 'edit_posts' ) ) {
				return '<p class="mulino-empty">' . esc_html(
					sprintf(
						/* translators: %s: the folder slug given in the shortcode's folder="" attribute. */
						__( 'Mulino file show: the folder "%s" was not found.', 'mulino-file-show' ),
						$args['folder']
					)
				) . '</p>';
			}
			return '';
		}
	}

	// Sanitize + validate the requested folder slug. This is read-only
	// display filtering (which folder to show), not a state-changing
	// action, so nonce verification doesn't apply here the way it
	// would for a form submission.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$requested_slug = isset( $_GET['mulino_folder'] ) ? sanitize_title( wp_unslash( $_GET['mulino_folder'] ) ) : '';
	$current_term   = $requested_slug ? get_term_by( 'slug', $requested_slug, $taxonomy ) : false;
	if ( ! $current_term || is_wp_error( $current_term ) || ! mulino_term_is_within( $current_term, $root_term, $taxonomy ) ) {
		$current_term = $root_term;
	}

	ob_start();
	?>
	<div class="mulino-browser">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value is escaped individually inside this function before being concatenated into the returned HTML string.
		echo mulino_render_breadcrumb( $current_term, $taxonomy, $root_term );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value is escaped individually inside this function before being concatenated into the returned HTML string.
		echo mulino_render_subfolders( $current_term, $taxonomy, $args );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each value is escaped individually inside this function before being concatenated into the returned HTML string.
		echo mulino_render_documents( $current_term, $taxonomy, $args );
		?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'mulino_documents', 'mulino_shortcode' );

/**
 * Merge the shortcode attributes with their defaults and force every
 * value into its allowed set, so the render functions never have to
 * second-guess what they're given.
 */
function mulino_parse_shortcode_atts( $atts ) {
	$atts = shortcode_atts(
		array(
			'folder'       => '',
			'orderby'      => 'name',
			'document_order' => '',
			'order'          => 'asc',
			'folder_order'   => 'asc',
			'hide_empty'     => 'no',
		),
		$atts,
		'mulino_documents'
	);

	$orderby      = strtolower( trim( (string) $atts['orderby'] ) );
	// document_order mirrors folder_order; order is the shorter alias.
	$order        = strtolower( trim( (string) ( '' !== $atts['document_order'] ? $atts['document_order'] : $atts['order'] ) ) );
	$folder_order = strtolower( trim( (string) $atts['folder_order'] ) );
	$hide_empty   = strtolower( trim( (string) $atts['hide_empty'] ) );

	return array(
		'folder'       => sanitize_title( (string) $atts['folder'] ),
		'orderby'      => in_array( $orderby, array( 'name', 'date' ), true ) ? $orderby : 'name',
		'order'        => 'desc' === $order ? 'desc' : 'asc',
		'folder_order' => 'desc' === $folder_order ? 'desc' : 'asc',
		'hide_empty'   => in_array( $hide_empty, array( 'yes', 'true', '1' ), true ),
	);
}

/**
 * Is $term the root folder itself, or somewhere below it? With no
 * root folder (the whole library), every folder qualifies.
 */
function mulino_term_is_within( $term, $root_term, $taxonomy ) {
	if ( ! $root_term ) {
		return true;
	}
	if ( (int) $term->term_id === (int) $root_term->term_id ) {
		return true;
	}
	return in_array( (int) $root_term->term_id, array_map( 'intval', get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) ), true );
}

function mulino_render_breadcrumb( $current_term, $taxonomy, $root_term = false ) {
	$base_url = remove_query_arg( 'mulino_folder' );
	$crumbs   = array( '<a href="' . esc_url( $base_url ) . '">' . esc_html__( 'Home', 'mulino-file-show' ) . '</a>' );

	$at_root = ! $current_term || ( $root_term && (int) $current_term->term_id === (int) $root_term->term_id );

	if ( ! $at_root && ! is_wp_error( $current_term ) ) {
		$ancestors = array_reverse( get_ancestors( $current_term->term_id, $taxonomy, 'taxonomy' ) );
		$visible   = ! $root_term; // with a root folder, start listing below it
		foreach ( $ancestors as $ancestor_id ) {
			if ( ! $visible ) {
				$visible = ( (int) $ancestor_id === (int) $root_term->term_id );
				continue;
			}
			$ancestor = get_term( $ancestor_id, $taxonomy );
			if ( $ancestor && ! is_wp_error( $ancestor ) ) {
				$url      = add_query_arg( 'mulino_folder', $ancestor->slug, $base_url );
				$crumbs[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $ancestor->name ) . '</a>';
			}
		}
		$crumbs[] = '<span class="mulino-current">' . esc_html( $current_term->name ) . '</span>';
	}

	return '<nav class="mulino-breadcrumb">' . implode( ' &raquo; ', $crumbs ) . '</nav>';
}

function mulino_render_subfolders( $current_term, $taxonomy, $args = array() ) {
	$args      = wp_parse_args(
		$args,
		array(
			'folder_order' => 'asc',
			'hide_empty'   => false,
		)
	);
	$parent_id = $current_term ? $current_term->term_id : 0;

	$subfolders = get_terms(
		array(
			'taxonomy'     => $taxonomy,
			'parent'       => $parent_id,
			// With hide_empty, WordPress still keeps a folder whose own
			// count is 0 if one of its subfolders has documents (e.g. a
			// decade folder that only holds year folders), because
			// hierarchical defaults to true.
			'hide_empty'   => (bool) $args['hide_empty'],
			'hierarchical' => true,
		)
	);

	if ( is_wp_error( $subfolders ) || empty( $subfolders ) ) {
		return '';
	}

	$subfolders = mulino_natural_sort(
		$subfolders,
		function ( $folder ) {
			return $folder->name;
		},
		$args['folder_order']
	);

	$base_url = remove_query_arg( 'mulino_folder' );
	$out      = '<div class="mulino-grid">';
	foreach ( $subfolders as $folder ) {
		$url    = add_query_arg( 'mulino_folder', $folder->slug, $base_url );
		$out   .= '<a class="mulino-card mulino-card--folder" href="' . esc_url( $url ) . '">'
				. mulino_folder_icon_svg()
				. '<span class="mulino-name">' . esc_html( $folder->name ) . '</span>'
				. '</a>';
	}
	$out .= '</div>';

	return $out;
}

function mulino_folder_icon_svg() {
	return '<svg class="mulino-icon" viewBox="0 0 56 44" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
		<path d="M2 7a4 4 0 0 1 4-4h13l4 5h27a4 4 0 0 1 4 4v27a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4Z" fill="#ffcf5c" stroke="#e0a52e" stroke-width="1.5"/>
		<path d="M2 14h52v22a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4Z" fill="#ffe08a" stroke="#e0a52e" stroke-width="1.5"/>
	</svg>';
}

function mulino_render_documents( $current_term, $taxonomy, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'orderby'    => 'name',
			'order'      => 'asc',
			'hide_empty' => false,
		)
	);

	if ( $current_term ) {
		// A tax_query is inherently scoped to one specific folder
		// term here (not an open-ended query), so this stays fast
		// even on a large document library.
		$tax_query = array(
			array(
				'taxonomy'         => $taxonomy,
				'field'            => 'term_id',
				'terms'            => $current_term->term_id,
				'include_children' => false,
			),
		);
	} else {
		// Top of the whole library: documents that aren't in any
		// folder, the same ones the admin screen shows under "All".
		$tax_query = array(
			array(
				'taxonomy' => $taxonomy,
				'operator' => 'NOT EXISTS',
			),
		);
	}

	$query_args = array(
		'post_type'      => 'mulino_document',
		'posts_per_page' => -1,
		'orderby'        => 'date' === $args['orderby'] ? 'date' : 'title',
		'order'          => strtoupper( $args['order'] ),
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		'tax_query'      => $tax_query,
	);

	/**
	 * Filters the get_posts() arguments used to list the documents in
	 * one folder of the frontend [mulino_documents] browser.
	 *
	 * @param array         $query_args   Arguments passed to get_posts().
	 * @param WP_Term|false $current_term The folder being shown, or false
	 *                                    at the top of the whole library.
	 */
	$query_args = apply_filters( 'mulino_frontend_document_query_args', $query_args, $current_term );

	$documents = get_posts( $query_args );

	if ( 'name' === $args['orderby'] ) {
		// The database sorts "Minutes 10" before "Minutes 2"; people
		// expect the other way round.
		$documents = mulino_natural_sort(
			$documents,
			function ( $doc ) {
				return get_the_title( $doc );
			},
			$args['order']
		);
	}

	if ( empty( $documents ) ) {
		// Only show the "empty" message if this folder is completely
		// empty -- if it has subfolders, those are enough to look at,
		// and this message would just be visual noise underneath them.
		$subfolder_count = wp_count_terms(
			array(
				'taxonomy'   => $taxonomy,
				'parent'     => $current_term ? $current_term->term_id : 0,
				'hide_empty' => (bool) $args['hide_empty'],
			)
		);
		if ( ! is_wp_error( $subfolder_count ) && $subfolder_count > 0 ) {
			return '';
		}
		return '<p class="mulino-empty">' . esc_html__( 'No documents in this folder.', 'mulino-file-show' ) . '</p>';
	}

	$out = '<div class="mulino-grid">';
	foreach ( $documents as $doc ) {
		$attachment_id = (int) get_post_meta( $doc->ID, '_mulino_file_id', true );
		if ( ! $attachment_id ) {
			continue;
		}
		$url = wp_get_attachment_url( $attachment_id );
		if ( ! $url ) {
			continue;
		}
		$icon = mulino_get_file_type( $url );
		$out .= '<a class="mulino-card mulino-card--file" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">'
				. mulino_file_icon_svg( $icon['label'], $icon['color'] )
				. '<span class="mulino-name">' . esc_html( get_the_title( $doc ) ) . '</span>'
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
function mulino_enqueue_frontend_styles() {
	wp_enqueue_style(
		'mulino-frontend',
		MULINO_URL . 'assets/css/frontend.css',
		array(),
		MULINO_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'mulino_enqueue_frontend_styles' );
