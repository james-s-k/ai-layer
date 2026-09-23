<?php
/**
 * Shared admin-ajax handler for searchable page pickers.
 *
 * @package WPAIL\Admin
 */

declare(strict_types=1);

namespace WPAIL\Admin;

use WPAIL\AI\ImportPageSuggestions;

class PageSearchAjax {

	public const NONCE_ACTION = 'wpail_search_pages';

	public function register(): void {
		add_action( 'wp_ajax_wpail_search_pages', [ $this, 'search' ] );
	}

	public function search(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Permission denied.' ], 403 );
		}

		$term  = sanitize_text_field( wp_unslash( $_POST['term'] ?? '' ) );
		$pages = ImportPageSuggestions::search_pages( $term );

		wp_send_json_success( [ 'pages' => $pages ] );
	}
}
