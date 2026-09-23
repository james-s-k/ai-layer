<?php

declare(strict_types=1);

namespace WPAIL\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPAIL\Models\ActionModel;
use WPAIL\Models\FaqModel;
use WPAIL\Models\ProofModel;
use WPAIL\Support\PublicEntityVisibility;

final class PublicEntityVisibilityTest extends TestCase {

	public static function setUpBeforeClass(): void {
		require_once dirname( __DIR__, 2 ) . '/includes/Support/PublicEntityVisibility.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Models/FaqModel.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Models/ActionModel.php';
		require_once dirname( __DIR__, 2 ) . '/includes/Models/ProofModel.php';

		if ( ! function_exists( 'get_post' ) ) {
			function get_post( $post_id ) {
				$published = [ 1, 2, 3, 10, 11, 20, 21 ];
				if ( ! in_array( (int) $post_id, $published, true ) ) {
					return null;
				}
				return (object) [ 'post_status' => 'publish' ];
			}
		}
	}

	public function test_faq_requires_public_status_and_flag(): void {
		$public  = new FaqModel( id: 1, is_public: true, status: 'published' );
		$private = new FaqModel( id: 2, is_public: false, status: 'published' );
		$draft   = new FaqModel( id: 3, is_public: true, status: 'draft' );

		$this->assertTrue( PublicEntityVisibility::faq_is_public( $public, 1 ) );
		$this->assertFalse( PublicEntityVisibility::faq_is_public( $private, 2 ) );
		$this->assertFalse( PublicEntityVisibility::faq_is_public( $draft, 3 ) );
	}

	public function test_action_and_proof_require_public_flag(): void {
		$action       = new ActionModel( id: 10, is_public: true );
		$hidden       = new ActionModel( id: 11, is_public: false );
		$proof        = new ProofModel( id: 20, is_public: true );
		$hidden_proof = new ProofModel( id: 21, is_public: false );

		$this->assertTrue( PublicEntityVisibility::action_is_public( $action, 10 ) );
		$this->assertFalse( PublicEntityVisibility::action_is_public( $hidden, 11 ) );
		$this->assertTrue( PublicEntityVisibility::proof_is_public( $proof, 20 ) );
		$this->assertFalse( PublicEntityVisibility::proof_is_public( $hidden_proof, 21 ) );
	}
}
