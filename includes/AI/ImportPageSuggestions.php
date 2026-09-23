<?php
/**
 * Page suggestions and presets for AI Import quick selection.
 *
 * @package WPAIL\AI
 */

declare(strict_types=1);

namespace WPAIL\AI;

use WPAIL\LLMsTxt\LLMsTxtSettings;
use WP_Post;

class ImportPageSuggestions {

	public const PRESET_ESSENTIAL  = 'essential';
	public const PRESET_TOP_LEVEL  = 'top_level';
	public const SUGGESTION_LIMIT  = 15;
	public const TOP_LEVEL_CAP     = 20;
	public const RECOMMENDED_MAX   = 15;

	/** Slugs that commonly hold business entity content. */
	private const ESSENTIAL_SLUGS = [
		'about',
		'about-us',
		'about-me',
		'services',
		'service',
		'our-services',
		'what-we-do',
		'contact',
		'contact-us',
		'get-in-touch',
		'faq',
		'faqs',
		'pricing',
		'prices',
		'areas',
		'areas-we-cover',
		'areas-covered',
		'locations',
		'areas-we-serve',
		'coverage',
	];

	/** Title keywords (whole-word match, case-insensitive). */
	private const ESSENTIAL_TITLE_WORDS = [
		'about',
		'services',
		'service',
		'contact',
		'faq',
		'pricing',
		'locations',
		'areas',
	];

	/** Slugs to show but leave unchecked by default. */
	private const OPTIONAL_SLUGS = [
		'privacy',
		'privacy-policy',
		'terms',
		'terms-and-conditions',
		'blog',
		'news',
	];

	/**
	 * @return list<array{id: int, title: string, slug: string}>
	 */
	public static function get_preset_pages( string $preset ): array {
		return match ( $preset ) {
			self::PRESET_ESSENTIAL => self::collect_essential_pages( self::TOP_LEVEL_CAP ),
			self::PRESET_TOP_LEVEL => self::get_top_level_pages( self::TOP_LEVEL_CAP ),
			default                => [],
		};
	}

	/**
	 * Curated list for checkbox UI on page load.
	 *
	 * @return list<array{id: int, title: string, slug: string, reason: string, default_checked: bool}>
	 */
	public static function get_suggested_pages(): array {
		$candidates = [];
		$front_id   = (int) get_option( 'page_on_front' );

		if ( $front_id > 0 ) {
			$page = get_post( $front_id );
			if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
				$candidates[ $front_id ] = [
					'item'            => self::format_page( $page ),
					'reason'          => __( 'Homepage', 'ai-layer' ),
					'default_checked' => true,
					'priority'        => 0,
				];
			}
		}

		foreach ( self::get_llmstxt_page_ids() as $page_id ) {
			if ( isset( $candidates[ $page_id ] ) ) {
				continue;
			}
			$page = get_post( $page_id );
			if ( ! $page instanceof WP_Post || 'publish' !== $page->post_status ) {
				continue;
			}
			$candidates[ $page_id ] = [
				'item'            => self::format_page( $page ),
				'reason'          => __( 'Key page (llms.txt)', 'ai-layer' ),
				'default_checked' => true,
				'priority'        => 1,
			];
		}

		foreach ( self::find_essential_pages( self::SUGGESTION_LIMIT * 2 ) as $page ) {
			$id = $page['id'];
			if ( isset( $candidates[ $id ] ) ) {
				continue;
			}
			$optional = self::is_optional_slug( $page['slug'] );
			$candidates[ $id ] = [
				'item'            => $page,
				'reason'          => $optional
					? __( 'Common page', 'ai-layer' )
					: __( 'Likely business content', 'ai-layer' ),
				'default_checked' => ! $optional,
				'priority'        => $optional ? 4 : 2,
			];
		}

		foreach ( self::get_top_level_pages( self::SUGGESTION_LIMIT ) as $page ) {
			$id = $page['id'];
			if ( isset( $candidates[ $id ] ) ) {
				continue;
			}
			$candidates[ $id ] = [
				'item'            => $page,
				'reason'          => __( 'Top-level page', 'ai-layer' ),
				'default_checked' => false,
				'priority'        => 3,
			];
		}

		uasort(
			$candidates,
			static fn( array $a, array $b ): int => $a['priority'] <=> $b['priority']
				?: strcasecmp( $a['item']['title'], $b['item']['title'] )
		);

		$out = [];
		foreach ( array_slice( $candidates, 0, self::SUGGESTION_LIMIT, true ) as $entry ) {
			$out[] = [
				'id'              => $entry['item']['id'],
				'title'           => $entry['item']['title'],
				'slug'            => $entry['item']['slug'],
				'reason'          => $entry['reason'],
				'default_checked' => $entry['default_checked'],
			];
		}

		return $out;
	}

	/**
	 * Top-level published pages for parent-scope picker.
	 *
	 * @return list<array{id: int, title: string, slug: string, child_count: int}>
	 */
	public static function get_section_parents(): array {
		$parents = get_pages(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'parent'      => 0,
				'sort_column' => 'menu_order,post_title',
				'sort_order'  => 'ASC',
			]
		);

		$out = [];
		foreach ( $parents as $page ) {
			if ( ! $page instanceof WP_Post ) {
				continue;
			}
			$out[] = [
				'id'           => $page->ID,
				'title'        => $page->post_title,
				'slug'         => $page->post_name,
				'child_count'  => self::count_descendants( $page->ID ),
			];
		}

		return $out;
	}

	/**
	 * Search published pages by title (and content) for the admin picker.
	 *
	 * @return list<array{id: int, title: string, slug: string}>
	 */
	public static function search_pages( string $term, int $limit = 10 ): array {
		$term = trim( $term );
		if ( mb_strlen( $term ) < 2 ) {
			return [];
		}

		$query = new \WP_Query(
			[
				'post_type'              => 'page',
				'post_status'            => 'publish',
				's'                      => $term,
				'posts_per_page'         => max( 1, min( 20, $limit ) ),
				'orderby'                => 'relevance',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		$pages = [];
		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$pages[] = self::format_page( $post );
			}
		}

		return $pages;
	}

	/**
	 * A parent page plus all published descendant pages.
	 *
	 * @return list<array{id: int, title: string, slug: string}>
	 */
	public static function get_pages_under_parent( int $parent_id ): array {
		$parent_id = absint( $parent_id );
		if ( $parent_id <= 0 ) {
			return [];
		}

		$parent = get_post( $parent_id );
		if ( ! $parent instanceof WP_Post || 'page' !== $parent->post_type || 'publish' !== $parent->post_status ) {
			return [];
		}

		$pages = [ self::format_page( $parent ) ];

		$children = get_pages(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'child_of'    => $parent_id,
				'sort_column' => 'menu_order,post_title',
				'sort_order'  => 'ASC',
			]
		);

		foreach ( $children as $child ) {
			if ( $child instanceof WP_Post ) {
				$pages[] = self::format_page( $child );
			}
		}

		return $pages;
	}

	/**
	 * @return list<array{id: int, title: string, slug: string}>
	 */
	private static function collect_essential_pages( int $cap ): array {
		$by_id = [];

		foreach ( self::find_essential_pages( $cap ) as $page ) {
			$by_id[ $page['id'] ] = $page;
		}

		$front_id = (int) get_option( 'page_on_front' );
		if ( $front_id > 0 ) {
			$front = get_post( $front_id );
			if ( $front instanceof WP_Post && 'publish' === $front->post_status ) {
				$by_id[ $front_id ] = self::format_page( $front );
			}
		}

		return array_values( array_slice( $by_id, 0, $cap, true ) );
	}

	/**
	 * @return list<array{id: int, title: string, slug: string}>
	 */
	private static function find_essential_pages( int $limit ): array {
		$pages = get_pages(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'sort_column' => 'menu_order,post_title',
				'sort_order'  => 'ASC',
				'number'      => 200,
			]
		);

		$matched = [];
		foreach ( $pages as $page ) {
			if ( ! $page instanceof WP_Post ) {
				continue;
			}
			if ( self::is_essential_page( $page ) ) {
				$matched[] = self::format_page( $page );
			}
			if ( count( $matched ) >= $limit ) {
				break;
			}
		}

		return $matched;
	}

	/**
	 * @return list<array{id: int, title: string, slug: string}>
	 */
	private static function get_top_level_pages( int $cap ): array {
		$pages = get_pages(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'parent'      => 0,
				'sort_column' => 'menu_order,post_title',
				'sort_order'  => 'ASC',
				'number'      => $cap,
			]
		);

		$out = [];
		foreach ( $pages as $page ) {
			if ( $page instanceof WP_Post ) {
				$out[] = self::format_page( $page );
			}
		}

		return $out;
	}

	private static function is_essential_page( WP_Post $page ): bool {
		$slug = $page->post_name;
		if ( in_array( $slug, self::ESSENTIAL_SLUGS, true ) ) {
			return true;
		}

		foreach ( self::ESSENTIAL_SLUGS as $needle ) {
			if ( str_contains( $slug, $needle ) ) {
				return true;
			}
		}

		$title = strtolower( $page->post_title );
		foreach ( self::ESSENTIAL_TITLE_WORDS as $word ) {
			if ( preg_match( '/\b' . preg_quote( $word, '/' ) . '\b/', $title ) ) {
				return true;
			}
		}

		return false;
	}

	private static function is_optional_slug( string $slug ): bool {
		if ( in_array( $slug, self::OPTIONAL_SLUGS, true ) ) {
			return true;
		}

		foreach ( self::OPTIONAL_SLUGS as $needle ) {
			if ( str_contains( $slug, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/** @return list<int> */
	private static function get_llmstxt_page_ids(): array {
		$settings = LLMsTxtSettings::get_all();
		$pages    = $settings['pages'] ?? [];
		$ids      = [];

		foreach ( (array) ( $pages['common'] ?? [] ) as $page_id ) {
			$page_id = absint( $page_id );
			if ( $page_id > 0 ) {
				$ids[] = $page_id;
			}
		}

		foreach ( (array) ( $pages['custom'] ?? [] ) as $row ) {
			$page_id = absint( is_array( $row ) ? ( $row['id'] ?? 0 ) : 0 );
			if ( $page_id > 0 ) {
				$ids[] = $page_id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	private static function count_descendants( int $parent_id ): int {
		$children = get_pages(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'child_of'    => $parent_id,
			]
		);

		return count( $children );
	}

	/** @return array{id: int, title: string, slug: string} */
	private static function format_page( WP_Post $page ): array {
		return [
			'id'    => $page->ID,
			'title' => $page->post_title,
			'slug'  => $page->post_name,
		];
	}
}
