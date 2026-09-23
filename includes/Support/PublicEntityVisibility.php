<?php
/**
 * Shared rules for whether an entity may be exposed on public read APIs.
 *
 * @package WPAIL\Support
 */

declare(strict_types=1);

namespace WPAIL\Support;

use WPAIL\Models\ActionModel;
use WPAIL\Models\FaqModel;
use WPAIL\Models\ProofModel;

class PublicEntityVisibility {

	public static function post_is_published( int $post_id ): bool {
		$post = get_post( $post_id );

		if ( ! is_object( $post ) || ! isset( $post->post_status ) ) {
			return false;
		}

		return 'publish' === $post->post_status;
	}

	public static function faq_is_public( FaqModel $faq, int $post_id ): bool {
		return self::post_is_published( $post_id )
			&& $faq->is_public
			&& 'published' === $faq->status;
	}

	public static function action_is_public( ActionModel $action, int $post_id ): bool {
		return self::post_is_published( $post_id ) && $action->is_public;
	}

	public static function proof_is_public( ProofModel $proof, int $post_id ): bool {
		return self::post_is_published( $post_id ) && $proof->is_public;
	}
}
