<?php
/**
 * REST endpoints: /products, /products/{slug}
 *
 * A read-only proxy over WooCommerce's native product data.
 * No CPT or data duplication — reads live from WooCommerce on each request.
 *
 * Only registered when:
 *   1. The Products endpoint is enabled in Settings.
 *   2. WooCommerce is active (class_exists check in RestRegistrar).
 *
 * GET /wp-json/ai-layer/v1/products
 * GET /wp-json/ai-layer/v1/products/{slug}
 *
 * @package WPAIL\Rest
 */

declare(strict_types=1);

namespace WPAIL\Rest;

class ProductsController extends BaseController {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/products', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'get_items_permissions_check' ],
				'args'                => [
					'per_page' => [
						'description'       => 'Products per page (max 100).',
						'type'              => 'integer',
						'default'           => 20,
						'sanitize_callback' => 'absint',
					],
					'page' => [
						'description'       => 'Page number.',
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					],
					'category' => [
						'description'       => 'Filter by product category slug.',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					],
				],
			],
		] );

		register_rest_route( $this->namespace, '/products/(?P<slug>[a-z0-9_-]+)', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'get_item_permissions_check' ],
				'args'                => [
					'slug' => [
						'description'       => 'Product slug.',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_title',
					],
				],
			],
		] );
	}

	public function get_items( $request ): \WP_REST_Response|\WP_Error {
		$per_page = min( max( 1, (int) $request->get_param( 'per_page' ) ), 100 );
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$category = (string) $request->get_param( 'category' );

		$query_args = [
			'status'  => 'publish',
			'limit'   => $per_page,
			'offset'  => ( $page - 1 ) * $per_page,
			'orderby' => 'title',
			'order'   => 'ASC',
			'return'  => 'objects',
		];

		if ( $category !== '' ) {
			$query_args['category'] = [ $category ];
		}

		$products = wc_get_products( $query_args );

		// Count total for pagination meta.
		$count_args          = $query_args;
		$count_args['limit']  = -1;
		$count_args['offset'] = 0;
		$count_args['return'] = 'ids';
		$total = count( wc_get_products( $count_args ) );

		$data = array_map( [ $this, 'product_to_summary' ], $products );

		return $this->success( $data, [
			'count'       => count( $data ),
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 1,
		] );
	}

	public function get_item( $request ): \WP_REST_Response|\WP_Error {
		$slug  = $request->get_param( 'slug' );
		$posts = get_posts( [
			'name'        => $slug,
			'post_type'   => 'product',
			'post_status' => 'publish',
			'numberposts' => 1,
		] );

		if ( empty( $posts ) ) {
			return $this->not_found( 'Product not found.' );
		}

		$product = wc_get_product( $posts[0]->ID );

		if ( ! $product ) {
			return $this->not_found( 'Product not found.' );
		}

		return $this->success( $this->product_to_detail( $product ) );
	}

	// ------------------------------------------------------------------
	// Transformers.
	// ------------------------------------------------------------------

	/**
	 * Lightweight summary shape for list responses.
	 *
	 * @return array<string, mixed>
	 */
	private function product_to_summary( \WC_Product $product ): array {
		$image_id = $product->get_image_id();

		return [
			'id'         => $product->get_id(),
			'slug'       => $product->get_slug(),
			'name'       => $product->get_name(),
			'type'       => $product->get_type(),
			'price'      => $product->get_price(),
			'currency'   => get_woocommerce_currency(),
			'on_sale'    => $product->is_on_sale(),
			'in_stock'   => $product->is_in_stock(),
			'categories' => $this->get_term_slugs( $product->get_id(), 'product_cat' ),
			'image'      => $image_id ? wp_get_attachment_url( $image_id ) : null,
			'url'        => get_permalink( $product->get_id() ),
		];
	}

	/**
	 * Extended shape for single-product responses.
	 * Adds pricing detail, descriptions, gallery, and physical attributes
	 * that are omitted from the lean summary shape.
	 *
	 * @return array<string, mixed>
	 */
	private function product_to_detail( \WC_Product $product ): array {
		$data = $this->product_to_summary( $product );

		// Pricing detail (omitted from summary for brevity).
		$data['sku']           = $product->get_sku();
		$data['regular_price'] = $product->get_regular_price();
		$data['sale_price']    = $product->get_sale_price() ?: null;

		$data['short_description'] = wp_strip_all_tags( $product->get_short_description() );
		$data['description']       = wp_strip_all_tags( do_blocks( $product->get_description() ) );

		$data['is_virtual']   = $product->is_virtual();
		$data['is_downloadable']   = $product->is_downloadable();
		$data['categories']        = $this->get_terms_full( $product->get_id(), 'product_cat' );
		$data['tags']              = $this->get_term_names( $product->get_id(), 'product_tag' );

		// Gallery images (excludes the main image already in summary).
		$data['gallery'] = array_values( array_filter(
			array_map( 'wp_get_attachment_url', $product->get_gallery_image_ids() )
		) );

		// Physical attributes.
		$data['weight']      = $product->get_weight() ?: null;
		$data['weight_unit'] = get_option( 'woocommerce_weight_unit' );
		$data['dimensions']  = [
			'length' => $product->get_length() ?: null,
			'width'  => $product->get_width()  ?: null,
			'height' => $product->get_height() ?: null,
			'unit'   => get_option( 'woocommerce_dimension_unit' ),
		];

		// Inventory.
		$data['stock_quantity'] = $product->get_stock_quantity();

		if ( $product->is_type( 'variable' ) && $product instanceof \WC_Product_Variable ) {
			$prices = $product->get_variation_prices();

			if ( ! empty( $prices['price'] ) ) {
				$data['price_range'] = [
					'min' => (string) min( $prices['price'] ),
					'max' => (string) max( $prices['price'] ),
				];
			}

			$data['attributes'] = $this->get_variation_attributes( $product );
		}

		return $data;
	}

	// ------------------------------------------------------------------
	// Term helpers.
	// ------------------------------------------------------------------

	/** @return array<string> */
	private function get_term_slugs( int $post_id, string $taxonomy ): array {
		$terms = wp_get_post_terms( $post_id, $taxonomy, [ 'fields' => 'slugs' ] );
		return is_array( $terms ) ? $terms : [];
	}

	/** @return array<string> */
	private function get_term_names( int $post_id, string $taxonomy ): array {
		$terms = wp_get_post_terms( $post_id, $taxonomy, [ 'fields' => 'names' ] );
		return is_array( $terms ) ? $terms : [];
	}

	/**
	 * @return array<array{id: int, name: string, slug: string}>
	 */
	private function get_terms_full( int $post_id, string $taxonomy ): array {
		$terms = wp_get_post_terms( $post_id, $taxonomy );

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return [];
		}

		return array_map(
			fn( \WP_Term $t ) => [ 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug ],
			$terms
		);
	}

	/**
	 * @return array<array{name: string, options: array<string>}>
	 */
	private function get_variation_attributes( \WC_Product_Variable $product ): array {
		$result = [];

		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! ( $attribute instanceof \WC_Product_Attribute ) ) {
				continue;
			}

			$result[] = [
				'name'    => wc_attribute_label( $attribute->get_name() ),
				'options' => $attribute->get_slugs(),
			];
		}

		return $result;
	}
}
