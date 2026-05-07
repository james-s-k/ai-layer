<?php
/**
 * REST endpoint: GET /openapi
 *
 * Returns the OpenAPI 3.1.0 specification for all AI Layer endpoints.
 *
 * @package WPAIL\Rest
 */

declare(strict_types=1);

namespace WPAIL\Rest;

use WPAIL\Admin\SettingsPage;

class OpenApiController extends BaseController {

	/**
	 * Register the OpenAPI route.
	 */
	public function register_routes(): void {
		register_rest_route(
			WPAIL_REST_NS,
			'/openapi',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_spec' ],
					'permission_callback' => '__return_true',
				],
			]
		);
	}

	/**
	 * Return the OpenAPI 3.1.0 specification.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_spec(): \WP_REST_Response {
		$spec = [
			'openapi' => '3.1.0',
			'info'    => [
				'title'       => 'AI Layer API',
				'description' => 'Structured business knowledge layer for WordPress. Exposes canonical business data via versioned REST endpoints for AI systems, agents, and search tools.',
				'version'     => WPAIL_VERSION,
				'contact'     => [ 'url' => home_url( '/ai-layer' ) ],
			],
			'servers' => [
				[
					'url'         => rtrim( rest_url( WPAIL_REST_NS ), '/' ),
					'description' => 'AI Layer API',
				],
			],
			'paths'      => $this->build_paths(),
			'components' => [
				'schemas'         => $this->build_schemas(),
				'securitySchemes' => [
					'BasicAuth' => [
						'type'        => 'http',
						'scheme'      => 'basic',
						'description' => 'WordPress Application Passwords. Use your WordPress username and an Application Password generated at Users → Profile → Application Passwords.',
					],
				],
			],
		];

		$response = new \WP_REST_Response( $spec, 200 );
		$response->header( 'Content-Type', 'application/json' );
		$response->header( 'Cache-Control', 'public, max-age=3600' );

		return $response;
	}

	/**
	 * Build the paths object for the OpenAPI spec.
	 *
	 * @return array<string, mixed>
	 */
	private function build_paths(): array {
		$paths = [
			'/manifest' => [
				'get' => [
					'summary'     => 'Discovery manifest',
					'description' => 'Canonical AI Layer manifest. Returns the business profile, available entity endpoints, relationships, query capabilities, and discovery file locations. The recommended first request for any agent exploring this site.',
					'operationId' => 'getManifest',
					'tags'        => [ 'Discovery' ],
					'responses'   => [
						'200' => [
							'description' => 'Manifest',
							'content'     => [
								'application/json' => [
									'schema' => [ '$ref' => '#/components/schemas/Manifest' ],
								],
							],
						],
					],
				],
			],
			'/openapi'  => [
				'get' => [
					'summary'     => 'OpenAPI specification',
					'description' => 'Returns this OpenAPI 3.1.0 specification describing all AI Layer endpoints.',
					'operationId' => 'getOpenApi',
					'tags'        => [ 'Discovery' ],
					'responses'   => [
						'200' => [ 'description' => 'OpenAPI specification document' ],
					],
				],
			],
			'/profile'  => [
				'get' => [
					'summary'     => 'Business profile',
					'description' => 'Returns the canonical business profile: name, contact, address, opening hours, and social links.',
					'operationId' => 'getProfile',
					'tags'        => [ 'Core' ],
					'responses'   => [
						'200' => [
							'description' => 'Business profile',
							'content'     => [
								'application/json' => [
									'schema' => [ '$ref' => '#/components/schemas/ProfileResponse' ],
								],
							],
						],
					],
				],
			],
			'/services' => [
				'get' => [
					'summary'     => 'List services',
					'description' => 'Returns all published services with summary fields.',
					'operationId' => 'listServices',
					'tags'        => [ 'Services' ],
					'responses'   => [
						'200' => [
							'description' => 'Array of services',
							'content'     => [
								'application/json' => [
									'schema' => [ '$ref' => '#/components/schemas/ServicesResponse' ],
								],
							],
						],
					],
				],
			],
			'/services/{slug}' => [
				'get' => [
					'summary'     => 'Get service by slug',
					'description' => 'Returns full detail for a single service including related FAQs, locations, proof, and actions.',
					'operationId' => 'getService',
					'tags'        => [ 'Services' ],
					'parameters'  => [
						[
							'name'        => 'slug',
							'in'          => 'path',
							'required'    => true,
							'schema'      => [ 'type' => 'string' ],
							'description' => 'Service slug',
						],
					],
					'responses'   => [
						'200' => [
							'description' => 'Service detail',
							'content'     => [
								'application/json' => [
									'schema' => [ '$ref' => '#/components/schemas/ServiceDetailResponse' ],
								],
							],
						],
						'404' => [ 'description' => 'Service not found' ],
					],
				],
			],
			'/locations' => [
				'get' => [
					'summary'     => 'List locations',
					'description' => 'Returns all published locations and service areas.',
					'operationId' => 'listLocations',
					'tags'        => [ 'Locations' ],
					'responses'   => [
						'200' => [
							'description' => 'Array of locations',
							'content'     => [
								'application/json' => [
									'schema' => [ '$ref' => '#/components/schemas/LocationsResponse' ],
								],
							],
						],
					],
				],
			],
			'/locations/{slug}' => [
				'get' => [
					'summary'     => 'Get location by slug',
					'description' => 'Returns full detail for a single location including related services.',
					'operationId' => 'getLocation',
					'tags'        => [ 'Locations' ],
					'parameters'  => [
						[
							'name'        => 'slug',
							'in'          => 'path',
							'required'    => true,
							'schema'      => [ 'type' => 'string' ],
							'description' => 'Location slug',
						],
					],
					'responses'   => [
						'200' => [
							'description' => 'Location detail',
							'content'     => [
								'application/json' => [
									'schema' => [ '$ref' => '#/components/schemas/LocationDetailResponse' ],
								],
							],
						],
						'404' => [ 'description' => 'Location not found' ],
					],
				],
			],
			'/faqs' => [
				'get' => [
					'summary'     => 'List FAQs',
					'description' => 'Returns all published FAQs. Optionally filter by service or location.',
					'operationId' => 'listFaqs',
					'tags'        => [ 'FAQs' ],
					'parameters'  => [
						[
							'name'        => 'service',
							'in'          => 'query',
							'required'    => false,
							'schema'      => [ 'type' => 'string' ],
							'description' => 'Filter by service slug',
						],
						[
							'name'        => 'location',
							'in'          => 'query',
							'required'    => false,
							'schema'      => [ 'type' => 'string' ],
							'description' => 'Filter by location slug',
						],
					],
					'responses'   => [
						'200' => [
							'description' => 'Array of FAQs',
							'content'     => [
								'application/json' => [
									'schema' => [ '$ref' => '#/components/schemas/FaqsResponse' ],
								],
							],
						],
					],
				],
			],
			'/faqs/{id}' => [
				'get' => [
					'summary'     => 'Get FAQ by ID',
					'description' => 'Returns a single FAQ.',
					'operationId' => 'getFaq',
					'tags'        => [ 'FAQs' ],
					'parameters'  => [
						[
							'name'        => 'id',
							'in'          => 'path',
							'required'    => true,
							'schema'      => [ 'type' => 'integer' ],
							'description' => 'FAQ post ID',
						],
					],
					'responses'   => [
						'200' => [
							'description' => 'FAQ',
							'content'     => [
								'application/json' => [
									'schema' => [ '$ref' => '#/components/schemas/FaqResponse' ],
								],
							],
						],
						'404' => [ 'description' => 'FAQ not found' ],
					],
				],
			],
			'/proof' => [
				'get' => [
					'summary'     => 'List proof & trust items',
					'description' => 'Returns all published trust signals: testimonials, case studies, accreditations, statistics, and awards.',
					'operationId' => 'listProof',
					'tags'        => [ 'Proof & Trust' ],
					'responses'   => [
						'200' => [
							'description' => 'Array of proof items',
							'content'     => [
								'application/json' => [
									'schema' => [ '$ref' => '#/components/schemas/ProofResponse' ],
								],
							],
						],
					],
				],
			],
			'/proof/{id}' => [
				'get' => [
					'summary'     => 'Get proof item by ID',
					'description' => 'Returns a single proof or trust signal.',
					'operationId' => 'getProof',
					'tags'        => [ 'Proof & Trust' ],
					'parameters'  => [
						[
							'name'        => 'id',
							'in'          => 'path',
							'required'    => true,
							'schema'      => [ 'type' => 'integer' ],
							'description' => 'Proof item post ID',
						],
					],
					'responses'   => [
						'200' => [
							'description' => 'Proof item',
							'content'     => [
								'application/json' => [
									'schema' => [ '$ref' => '#/components/schemas/ProofItemResponse' ],
								],
							],
						],
						'404' => [ 'description' => 'Proof item not found' ],
					],
				],
			],
			'/actions' => [
				'get' => [
					'summary'     => 'List actions',
					'description' => 'Returns all published calls-to-action.',
					'operationId' => 'listActions',
					'tags'        => [ 'Actions' ],
					'responses'   => [
						'200' => [
							'description' => 'Array of actions',
							'content'     => [
								'application/json' => [
									'schema' => [ '$ref' => '#/components/schemas/ActionsResponse' ],
								],
							],
						],
					],
				],
			],
			'/actions/{id}' => [
				'get' => [
					'summary'     => 'Get action by ID',
					'description' => 'Returns a single call-to-action.',
					'operationId' => 'getAction',
					'tags'        => [ 'Actions' ],
					'parameters'  => [
						[
							'name'        => 'id',
							'in'          => 'path',
							'required'    => true,
							'schema'      => [ 'type' => 'integer' ],
							'description' => 'Action post ID',
						],
					],
					'responses'   => [
						'200' => [
							'description' => 'Action',
							'content'     => [
								'application/json' => [
									'schema' => [ '$ref' => '#/components/schemas/ActionItemResponse' ],
								],
							],
						],
						'404' => [ 'description' => 'Action not found' ],
					],
				],
			],
			'/answers' => [
				'get'  => [
					'summary'     => 'Query answer engine or list answers',
					'description' => 'With `?query=`: runs the rules-based answer engine and returns a structured response with confidence scoring, matched service/location, supporting proof, and next actions. Without `?query=`: lists all manually authored Answers.',
					'operationId' => 'queryAnswers',
					'tags'        => [ 'Answers' ],
					'parameters'  => [
						[
							'name'        => 'query',
							'in'          => 'query',
							'required'    => false,
							'schema'      => [ 'type' => 'string' ],
							'description' => 'Natural language question, e.g. "Do you offer SEO in Manchester?"',
						],
						[
							'name'        => 'service_id',
							'in'          => 'query',
							'required'    => false,
							'schema'      => [ 'type' => 'integer' ],
							'description' => 'Pre-select a service ID to narrow the answer',
						],
						[
							'name'        => 'location_id',
							'in'          => 'query',
							'required'    => false,
							'schema'      => [ 'type' => 'integer' ],
							'description' => 'Pre-select a location ID to narrow the answer',
						],
					],
					'responses'   => [
						'200' => [
							'description' => 'Answer engine response or list of authored answers',
							'content'     => [
								'application/json' => [
									'schema' => [ '$ref' => '#/components/schemas/AnswerResponse' ],
								],
							],
						],
						'404' => [ 'description' => 'No answer found for query' ],
					],
				],
				'post' => [
					'summary'     => 'Create an authored Answer',
					'description' => 'Creates a new manually authored Answer that takes guaranteed priority in the answer engine. Requires the `wpail_manage_content` capability (Administrators by default).',
					'operationId' => 'createAnswer',
					'tags'        => [ 'Answers' ],
					'security'    => [ [ 'BasicAuth' => [] ] ],
					'requestBody' => [
						'required' => true,
						'content'  => [
							'application/json' => [
								'schema' => [ '$ref' => '#/components/schemas/AnswerWrite' ],
							],
						],
					],
					'responses'   => [
						'201' => [ 'description' => 'Answer created' ],
						'400' => [ 'description' => 'Invalid input' ],
						'401' => [ 'description' => 'Authentication required' ],
						'403' => [ 'description' => 'Insufficient permissions' ],
					],
				],
			],
			'/answers/{id}' => [
				'get'    => [
					'summary'     => 'Get authored Answer by ID',
					'operationId' => 'getAnswer',
					'tags'        => [ 'Answers' ],
					'parameters'  => [
						[
							'name'     => 'id',
							'in'       => 'path',
							'required' => true,
							'schema'   => [ 'type' => 'integer' ],
						],
					],
					'responses'   => [
						'200' => [ 'description' => 'Authored Answer' ],
						'404' => [ 'description' => 'Not found' ],
					],
				],
				'patch'  => [
					'summary'     => 'Update an authored Answer',
					'description' => 'Requires the `wpail_manage_content` capability (Administrators by default).',
					'operationId' => 'updateAnswer',
					'tags'        => [ 'Answers' ],
					'security'    => [ [ 'BasicAuth' => [] ] ],
					'parameters'  => [
						[
							'name'     => 'id',
							'in'       => 'path',
							'required' => true,
							'schema'   => [ 'type' => 'integer' ],
						],
					],
					'requestBody' => [
						'required' => true,
						'content'  => [
							'application/json' => [
								'schema' => [ '$ref' => '#/components/schemas/AnswerWrite' ],
							],
						],
					],
					'responses'   => [
						'200' => [ 'description' => 'Updated' ],
						'401' => [ 'description' => 'Auth required' ],
						'403' => [ 'description' => 'Forbidden' ],
						'404' => [ 'description' => 'Not found' ],
					],
				],
				'delete' => [
					'summary'     => 'Delete an authored Answer',
					'description' => 'Requires the `wpail_manage_content` capability (Administrators by default).',
					'operationId' => 'deleteAnswer',
					'tags'        => [ 'Answers' ],
					'security'    => [ [ 'BasicAuth' => [] ] ],
					'parameters'  => [
						[
							'name'     => 'id',
							'in'       => 'path',
							'required' => true,
							'schema'   => [ 'type' => 'integer' ],
						],
					],
					'responses'   => [
						'200' => [ 'description' => 'Deleted' ],
						'401' => [ 'description' => 'Auth required' ],
						'403' => [ 'description' => 'Forbidden' ],
						'404' => [ 'description' => 'Not found' ],
					],
				],
			],
		];

		if ( class_exists( 'WooCommerce' ) && SettingsPage::get( SettingsPage::SETTING_PRODUCTS_ENABLED ) ) {
			$paths['/products'] = [
				'get' => [
					'summary'     => 'List products',
					'description' => 'Returns published WooCommerce products.',
					'operationId' => 'listProducts',
					'tags'        => [ 'Products' ],
					'parameters'  => [
						[
							'name'        => 'per_page',
							'in'          => 'query',
							'required'    => false,
							'schema'      => [ 'type' => 'integer', 'default' => 20, 'maximum' => 100 ],
							'description' => 'Number of products per page',
						],
						[
							'name'        => 'page',
							'in'          => 'query',
							'required'    => false,
							'schema'      => [ 'type' => 'integer' ],
							'description' => 'Page number',
						],
						[
							'name'        => 'category',
							'in'          => 'query',
							'required'    => false,
							'schema'      => [ 'type' => 'string' ],
							'description' => 'Filter by product category slug',
						],
					],
					'responses'   => [
						'200' => [
							'description' => 'Array of products',
							'content'     => [
								'application/json' => [
									'schema' => [ '$ref' => '#/components/schemas/ProductsResponse' ],
								],
							],
						],
					],
				],
			];

			$paths['/products/{slug}'] = [
				'get' => [
					'summary'     => 'Get product by slug',
					'description' => 'Returns full detail for a single WooCommerce product.',
					'operationId' => 'getProduct',
					'tags'        => [ 'Products' ],
					'parameters'  => [
						[
							'name'        => 'slug',
							'in'          => 'path',
							'required'    => true,
							'schema'      => [ 'type' => 'string' ],
							'description' => 'Product slug',
						],
					],
					'responses'   => [
						'200' => [
							'description' => 'Product detail',
							'content'     => [
								'application/json' => [
									'schema' => [ '$ref' => '#/components/schemas/ProductDetailResponse' ],
								],
							],
						],
						'404' => [ 'description' => 'Product not found' ],
					],
				],
			];
		}

		return $paths;
	}

	/**
	 * Build the components/schemas object for the OpenAPI spec.
	 *
	 * @return array<string, mixed>
	 */
	private function build_schemas(): array {
		$schemas = [
			'Manifest'              => [
				'type'       => 'object',
				'properties' => [
					'name'             => [ 'type' => 'string' ],
					'description'      => [ 'type' => 'string' ],
					'website'          => [ 'type' => 'string', 'format' => 'uri' ],
					'version'          => [ 'type' => 'string' ],
					'ai_layer_version' => [ 'type' => 'string' ],
					'language'         => [ 'type' => 'string' ],
					'updated_at'       => [ 'type' => 'string', 'format' => 'date-time' ],
					'entities'         => [ 'type' => 'object' ],
					'answers'          => [ 'type' => 'string', 'format' => 'uri' ],
					'openapi'          => [ 'type' => 'string', 'format' => 'uri' ],
					'discovery'        => [ 'type' => 'object' ],
					'relationships'    => [ 'type' => 'object' ],
					'query'            => [ 'type' => 'object' ],
					'authentication'   => [ 'type' => 'object' ],
					'content_policy'   => [ 'type' => 'object' ],
				],
			],
			'ServiceSummary'        => [
				'type'       => 'object',
				'properties' => [
					'id'   => [ 'type' => 'integer' ],
					'slug' => [ 'type' => 'string' ],
					'name' => [ 'type' => 'string' ],
				],
			],
			'LocationSummary'       => [
				'type'       => 'object',
				'properties' => [
					'id'   => [ 'type' => 'integer' ],
					'slug' => [ 'type' => 'string' ],
					'name' => [ 'type' => 'string' ],
				],
			],
			'FaqSummary'            => [
				'type'       => 'object',
				'properties' => [
					'id'           => [ 'type' => 'integer' ],
					'question'     => [ 'type' => 'string', 'x-content-trust' => 'user-authored' ],
					'short_answer' => [ 'type' => 'string', 'x-content-trust' => 'user-authored' ],
				],
			],
			'ActionSummary'         => [
				'type'       => 'object',
				'properties' => [
					'id'     => [ 'type' => 'integer' ],
					'type'   => [ 'type' => 'string', 'enum' => [ 'book', 'call', 'email', 'quote', 'visit', 'download', 'chat' ] ],
					'label'  => [ 'type' => 'string' ],
					'url'    => [ 'type' => [ 'string', 'null' ], 'format' => 'uri' ],
					'phone'  => [ 'type' => [ 'string', 'null' ] ],
					'method' => [ 'type' => 'string' ],
				],
			],
			'ProofSummary'          => [
				'type'       => 'object',
				'properties' => [
					'id'       => [ 'type' => 'integer' ],
					'type'     => [ 'type' => 'string', 'enum' => [ 'testimonial', 'statistic', 'accreditation', 'case_study', 'award', 'media_mention' ] ],
					'headline' => [ 'type' => 'string', 'x-content-trust' => 'user-authored' ],
				],
			],
			'AnswerResponse'        => [
				'type'       => 'object',
				'properties' => [
					'data' => [
						'type'       => 'object',
						'properties' => [
							'answer_short'    => [ 'type' => 'string', 'x-content-trust' => 'user-authored' ],
							'answer_long'     => [ 'type' => 'string', 'x-content-trust' => 'user-authored' ],
							'confidence'      => [ 'type' => 'string', 'enum' => [ 'high', 'medium', 'low' ] ],
							'source'          => [ 'type' => 'string', 'enum' => [ 'manual', 'faq', 'dynamic' ] ],
							'services'        => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/ServiceSummary' ] ],
							'locations'       => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/LocationSummary' ] ],
							'actions'         => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/ActionSummary' ] ],
							'source_faqs'     => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/FaqSummary' ] ],
							'supporting_data' => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/ProofSummary' ] ],
						],
					],
				],
			],
			'AnswerWrite'           => [
				'type'       => 'object',
				'properties' => [
					'short_answer'   => [ 'type' => 'string', 'description' => 'Short direct answer' ],
					'long_answer'    => [ 'type' => 'string', 'description' => 'Extended answer with detail' ],
					'query_patterns' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ], 'description' => 'Question patterns this answer matches' ],
				],
			],
			'ProfileResponse'       => [
				'type'       => 'object',
				'properties' => [
					'data' => [
						'type'       => 'object',
						'properties' => [
							'name'          => [ 'type' => 'string' ],
							'legal_name'    => [ 'type' => 'string' ],
							'short_summary' => [ 'type' => 'string' ],
							'long_summary'  => [ 'type' => 'string' ],
							'contact'       => [
								'type'       => 'object',
								'properties' => [
									'phone'   => [ 'type' => 'string' ],
									'email'   => [ 'type' => 'string' ],
									'website' => [ 'type' => 'string' ],
								],
							],
							'address'       => [ 'type' => 'object' ],
							'opening_hours' => [ 'type' => 'string' ],
							'social'        => [ 'type' => 'object' ],
						],
					],
				],
			],
			'ServicesResponse'      => [
				'type'       => 'object',
				'properties' => [
					'data' => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/ServiceSummary' ] ],
				],
			],
			'ServiceDetailResponse' => [
				'type'       => 'object',
				'properties' => [
					'data' => [
						'type'       => 'object',
						'properties' => [
							'id'            => [ 'type' => 'integer' ],
							'slug'          => [ 'type' => 'string' ],
							'name'          => [ 'type' => 'string' ],
							'short_summary' => [ 'type' => 'string', 'x-content-trust' => 'user-authored' ],
							'long_summary'  => [ 'type' => 'string', 'x-content-trust' => 'user-authored' ],
							'faqs'          => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/FaqSummary' ] ],
							'locations'     => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/LocationSummary' ] ],
							'proof'         => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/ProofSummary' ] ],
							'actions'       => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/ActionSummary' ] ],
						],
					],
				],
			],
			'LocationsResponse'     => [
				'type'       => 'object',
				'properties' => [
					'data' => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/LocationSummary' ] ],
				],
			],
			'LocationDetailResponse' => [
				'type'       => 'object',
				'properties' => [
					'data' => [
						'type'       => 'object',
						'properties' => [
							'id'       => [ 'type' => 'integer' ],
							'slug'     => [ 'type' => 'string' ],
							'name'     => [ 'type' => 'string' ],
							'services' => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/ServiceSummary' ] ],
						],
					],
				],
			],
			'FaqsResponse'          => [
				'type'       => 'object',
				'properties' => [
					'data' => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/FaqSummary' ] ],
				],
			],
			'FaqResponse'           => [
				'type'       => 'object',
				'properties' => [
					'data' => [ '$ref' => '#/components/schemas/FaqSummary' ],
				],
			],
			'ProofResponse'         => [
				'type'       => 'object',
				'properties' => [
					'data' => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/ProofSummary' ] ],
				],
			],
			'ProofItemResponse'     => [
				'type'       => 'object',
				'properties' => [
					'data' => [ '$ref' => '#/components/schemas/ProofSummary' ],
				],
			],
			'ActionsResponse'       => [
				'type'       => 'object',
				'properties' => [
					'data' => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/ActionSummary' ] ],
				],
			],
			'ActionItemResponse'    => [
				'type'       => 'object',
				'properties' => [
					'data' => [ '$ref' => '#/components/schemas/ActionSummary' ],
				],
			],
		];

		if ( class_exists( 'WooCommerce' ) && SettingsPage::get( SettingsPage::SETTING_PRODUCTS_ENABLED ) ) {
			$schemas['ProductSummary'] = [
				'type'       => 'object',
				'properties' => [
					'id'            => [ 'type' => 'integer' ],
					'slug'          => [ 'type' => 'string' ],
					'name'          => [ 'type' => 'string' ],
					'price'         => [ 'type' => 'string' ],
					'regular_price' => [ 'type' => 'string' ],
					'status'        => [ 'type' => 'string' ],
				],
			];

			$schemas['ProductsResponse'] = [
				'type'       => 'object',
				'properties' => [
					'data' => [ 'type' => 'array', 'items' => [ '$ref' => '#/components/schemas/ProductSummary' ] ],
				],
			];

			$schemas['ProductDetailResponse'] = [
				'type'       => 'object',
				'properties' => [
					'data' => [
						'type'       => 'object',
						'properties' => [
							'id'            => [ 'type' => 'integer' ],
							'slug'          => [ 'type' => 'string' ],
							'name'          => [ 'type' => 'string' ],
							'description'   => [ 'type' => 'string' ],
							'price'         => [ 'type' => 'string' ],
							'regular_price' => [ 'type' => 'string' ],
							'categories'    => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
							'images'        => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
						],
					],
				],
			];
		}

		return $schemas;
	}
}
