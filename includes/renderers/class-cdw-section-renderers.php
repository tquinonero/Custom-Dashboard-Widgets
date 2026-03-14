<?php
/**
 * CDW Section Renderers - Gutenberg block markup generators.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates Gutenberg block markup from structured section data.
 */
class CDW_Section_Renderers {

	/**
	 * Render a cover section.
	 *
	 * @param array{title?: string, subtitle?: string, image?: string, minHeight?: int} $data Section data.
	 * @return string Block markup.
	 */
	public static function render_cover( array $data ): string {
		$title      = isset( $data['title'] ) ? $data['title'] : ( isset( $data['heading'] ) ? $data['heading'] : '' );
		$subtitle   = isset( $data['subtitle'] ) ? $data['subtitle'] : ( isset( $data['subheading'] ) ? $data['subheading'] : '' );
		$image      = isset( $data['image'] ) ? $data['image'] : '';
		if ( ! $image && ! empty( $data['image_id'] ) ) {
			$image = (string) wp_get_attachment_url( (int) $data['image_id'] );
		}
		$min_height = isset( $data['minHeight'] ) ? (int) $data['minHeight'] : 600;
		$content    = isset( $data['content'] ) ? $data['content'] : '';
		$overlay    = isset( $data['overlay'] ) ? $data['overlay'] : '';
		if ( ! $overlay && isset( $data['overlay_opacity'] ) ) {
			$overlay = ( (float) $data['overlay_opacity'] >= 0.6 ) ? 'dark' : 'light';
		}

		$image_id  = ! empty( $data['image_id'] ) ? (int) $data['image_id'] : 0;

		$dim_ratio = 50;
		if ( isset( $data['overlay_opacity'] ) ) {
			$dim_ratio = (int) round( (float) $data['overlay_opacity'] * 100 );
		} elseif ( 'dark' === $overlay ) {
			$dim_ratio = 70;
		} elseif ( 'light' === $overlay ) {
			$dim_ratio = 30;
		}
		$dim_step  = (int) ( round( $dim_ratio / 10 ) * 10 );
		$dim_step  = max( 0, min( 100, $dim_step ) );

		if ( $image_id ) {
			$attributes = array(
				'url'                => $image,
				'id'                 => $image_id,
				'dimRatio'           => $dim_ratio,
				'overlayColor'       => 'black',
				'isUserOverlayColor' => true,
				'minHeight'          => $min_height,
				'align'              => 'full',
			);
		} else {
			$attributes = array(
				'url'                => $image,
				'dimRatio'           => $dim_ratio,
				'overlayColor'       => 'black',
				'isUserOverlayColor' => true,
				'minHeight'          => $min_height,
				'align'              => 'full',
			);
		}

		$markup  = '<!-- wp:cover ' . wp_json_encode( $attributes ) . " -->\n";
		$markup .= '<div class="wp-block-cover alignfull" style="min-height:' . $min_height . 'px">';

		if ( $image ) {
			$img_cls = 'wp-block-cover__image-background' . ( $image_id ? ' wp-image-' . $image_id : '' );
			$markup .= '<img class="' . esc_attr( $img_cls ) . '" alt="" src="' . esc_url( $image ) . '" data-object-fit="cover"/>';
		}

		$markup .= '<span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-' . $dim_step . ' has-background-dim"></span>';

		$markup .= '<div class="wp-block-cover__inner-container">' . "\n";

		// Handle nested blocks array - render as INNER content (no block comments, just HTML)
		if ( isset( $data['blocks'] ) && is_array( $data['blocks'] ) ) {
			foreach ( $data['blocks'] as $block ) {
				$markup .= self::render_inner_block( $block );
			}
		} else {
			// Fallback to legacy title/subtitle/content/buttons
			if ( $title ) {
				$markup .= "<!-- wp:heading {\"textAlign\":\"center\",\"level\":1,\"textColor\":\"white\",\"fontSize\":\"extra-large\"} -->\n";
				$markup .= '<h1 class="wp-block-heading has-text-align-center has-white-color has-text-color has-extra-large-font-size">' . esc_html( $title ) . '</h1>' . "\n";
				$markup .= "<!-- /wp:heading -->\n";
			}

			if ( $subtitle ) {
				$markup .= "<!-- wp:paragraph {\"align\":\"center\",\"textColor\":\"white\"} -->\n";
				$markup .= '<p class="has-text-align-center has-white-color has-text-color">' . esc_html( $subtitle ) . '</p>' . "\n";
				$markup .= "<!-- /wp:paragraph -->\n";
			}

			if ( $content ) {
				$markup .= "<!-- wp:paragraph {\"align\":\"center\",\"textColor\":\"white\",\"fontSize\":\"large\"} -->\n";
				$markup .= '<p class="has-text-align-center has-white-color has-text-color has-large-font-size">' . esc_html( $content ) . '</p>' . "\n";
				$markup .= "<!-- /wp:paragraph -->\n";
			}

			if ( isset( $data['buttons'] ) && is_array( $data['buttons'] ) ) {
				$markup .= "<!-- wp:buttons {\"layout\":{\"type\":\"flex\",\"justifyContent\":\"center\"}} -->\n";
				$markup .= '<div class="wp-block-buttons">' . "\n";
				foreach ( $data['buttons'] as $btn ) {
					$btn_text   = isset( $btn['text'] ) ? $btn['text'] : 'Button';
					$btn_url    = isset( $btn['url'] ) ? $btn['url'] : '#';
					$btn_style  = isset( $btn['style'] ) ? $btn['style'] : 'primary';

					$btn_classes = 'wp-block-button__link';
					if ( 'outline' === $btn_style ) {
						$btn_classes .= ' is-style-outline';
					} else {
						$btn_classes .= ' has-vivid-cyan-blue-background-color has-background';
					}
					$btn_classes .= ' has-white-color has-text-color';

					$markup .= "<!-- wp:button -->\n";
					$markup .= '<div class="wp-block-button"><a class="' . esc_attr( $btn_classes ) . '" href="' . esc_url( $btn_url ) . '">' . esc_html( $btn_text ) . '</a></div>' . "\n";
					$markup .= "<!-- /wp:button -->\n";
				}
				$markup .= "</div>\n";
				$markup .= "<!-- /wp:buttons -->\n";
			}
		}

		$markup .= "</div>\n";
		$markup .= "</div>\n";
		$markup .= "<!-- /wp:cover -->\n\n";

		return $markup;
	}

	/**
	 * Render inner block content WITHOUT block comments (for nested blocks).
	 *
	 * @param array<string, mixed> $data Block data.
	 * @return string Inner HTML only.
	 */
	private static function render_inner_block( array $data ): string {
		$block_name = isset( $data['block'] ) ? $data['block'] : ( isset( $data['blockName'] ) ? $data['blockName'] : '' );

		if ( empty( $block_name ) ) {
			return '';
		}

		$block_name_display = str_replace( 'core/', '', $block_name );
		$content = isset( $data['content'] ) ? $data['content'] : '';

		// Use the same rendering logic as render_block_inner but without block comments
		switch ( $block_name ) {
			case 'core/paragraph':
				$align = isset( $data['align'] ) ? ' has-text-align-' . $data['align'] : '';
				return '<p class="' . trim( $align ) . '">' . esc_html( $content ) . '</p>' . "\n";

			case 'core/heading':
				$level = isset( $data['level'] ) ? (int) $data['level'] : 2;
				$align = isset( $data['align'] ) ? ' has-text-align-' . $data['align'] : '';
				$tag = 'h' . $level;
				return '<' . $tag . ' class="' . trim( $align ) . '">' . esc_html( $content ) . '</' . $tag . '>' . "\n";

			case 'core/buttons':
				$markup = '<div class="wp-block-buttons">' . "\n";
				$items = isset( $data['items'] ) ? $data['items'] : array();
				foreach ( $items as $btn ) {
					$text = isset( $btn['text'] ) ? $btn['text'] : 'Button';
					$url = isset( $btn['url'] ) ? $btn['url'] : '#';
					$variant = isset( $btn['variant'] ) && $btn['variant'] === 'outline' ? ' is-style-outline' : '';
					$markup .= '<div class="wp-block-button' . $variant . '"><a class="wp-block-button__link wp-element-button" href="' . esc_attr( $url ) . '">' . esc_html( $text ) . '</a></div>' . "\n";
				}
				$markup .= "</div>\n";
				return $markup;

			case 'core/image':
				$url = isset( $data['url'] ) ? $data['url'] : '';
				$alt = isset( $data['alt'] ) ? $data['alt'] : '';
				return '<figure class="wp-block-image size-full"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"/></figure>' . "\n";

			default:
				return $content;
		}
	}

	/**
	 * Render a two-column section.
	 *
	 * @param array<string, mixed> $data Section data.
	 * @return string Block markup.
	 */
	public static function render_two_column( array $data ): string {
		$left    = isset( $data['left'] ) && is_array( $data['left'] ) ? $data['left'] : array();
		$right   = isset( $data['right'] ) && is_array( $data['right'] ) ? $data['right'] : array();
		$reverse = isset( $data['reverse'] ) && $data['reverse'];
		$title    = isset( $data['title'] ) ? $data['title'] : '';
		$subtitle = isset( $data['subtitle'] ) ? $data['subtitle'] : '';

		// Extract blocks from left and right
		$left_blocks  = isset( $left['blocks'] ) && is_array( $left['blocks'] ) ? $left['blocks'] : array();
		$right_blocks = isset( $right['blocks'] ) && is_array( $right['blocks'] ) ? $right['blocks'] : array();

		// If no blocks found in left/right, check for blocks at root level
		if ( empty( $left_blocks ) && isset( $data['blocks'] ) && is_array( $data['blocks'] ) ) {
			$left_blocks = array_slice( $data['blocks'], 0, 1 );
			$right_blocks = array_slice( $data['blocks'], 1 );
		}

		$attributes = array(
			'align'           => 'full',
			'style'           => array(
				'spacing' => array(
					'padding' => array(
						'top'    => '80px',
						'bottom' => '80px',
						'left'   => '40px',
						'right'  => '40px',
					),
				),
			),
			'backgroundColor' => 'white',
		);

		$markup  = '<!-- wp:columns ' . wp_json_encode( $attributes ) . " -->\n";
		$markup .= '<div class="wp-block-columns alignfull has-white-background-color has-background" style="padding-top:80px;padding-right:40px;padding-bottom:80px;padding-left:40px">' . "\n";

		if ( $title || $subtitle ) {
			$markup .= "<!-- wp:columns {\"align\":\"full\"} -->\n";
			$markup .= '<div class="wp-block-columns alignfull">' . "\n";
			$markup .= "<!-- wp:column -->\n";
			$markup .= '<div class="wp-block-column">' . "\n";
			if ( $subtitle ) {
				$markup .= "<!-- wp:paragraph {\"align\":\"left\",\"textColor\":\"black\",\"fontSize\":\"small\"} -->\n";
				$markup .= '<p class="has-text-align-left has-black-color has-text-color has-small-font-size">' . esc_html( $subtitle ) . '</p>' . "\n";
				$markup .= "<!-- /wp:paragraph -->\n";
			}
			if ( $title ) {
				$markup .= "<!-- wp:heading {\"textAlign\":\"left\",\"level\":2} -->\n";
				$markup .= '<h2 class="wp-block-heading has-text-align-left">' . esc_html( $title ) . '</h2>' . "\n";
				$markup .= "<!-- /wp:heading -->\n";
			}
			$markup .= "</div>\n";
			$markup .= "<!-- /wp:column -->\n";
			$markup .= "</div>\n";
			$markup .= "<!-- /wp:columns -->\n";
		}

		if ( $reverse ) {
			$markup .= self::render_text_column( $right, $right_blocks );
			$markup .= self::render_image_column( $left, $left_blocks );
		} else {
			$markup .= self::render_image_column( $left, $left_blocks );
			$markup .= self::render_text_column( $right, $right_blocks );
		}

		$markup .= "</div>\n";
		$markup .= "<!-- /wp:columns -->\n\n";

		return $markup;
	}

	/**
	 * Render an image column.
	 *
	 * @param array{src?: string, image?: string, alt?: string} $data Column data.
	 * @param array $blocks Optional blocks to render.
	 * @return string Block markup.
	 */
	private static function render_image_column( array $data, array $blocks = array() ): string {
		// If blocks array provided, render them instead
		if ( ! empty( $blocks ) ) {
			$markup  = "<!-- wp:column -->\n";
			$markup .= '<div class="wp-block-column">' . "\n";
			foreach ( $blocks as $block ) {
				$markup .= self::render_inner_block( $block );
			}
			$markup .= "</div>\n";
			$markup .= "<!-- /wp:column -->\n";
			return $markup;
		}

		$src = isset( $data['src'] ) ? $data['src'] : ( isset( $data['image'] ) ? $data['image'] : '' );
		if ( ! $src && ! empty( $data['image_id'] ) ) {
			$src = (string) wp_get_attachment_url( (int) $data['image_id'] );
		}
		$alt = isset( $data['alt'] ) ? $data['alt'] : 'Image';

		if ( ! $src ) {
			return "<!-- wp:column -->\n<div class=\"wp-block-column\">\n</div>\n<!-- /wp:column -->\n";
		}

		$markup  = "<!-- wp:column -->\n";
		$markup .= '<div class="wp-block-column">' . "\n";
		$markup .= "<!-- wp:image {\"align\":\"wide\",\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n";
		$markup .= '<figure class="wp-block-image alignwide size-full"><img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '"/></figure>' . "\n";
		$markup .= "<!-- /wp:image -->\n";
		$markup .= "</div>\n";
		$markup .= "<!-- /wp:column -->\n";

		return $markup;
	}

	/**
	 * Render a text column.
	 *
	 * @param array<string, mixed> $data Column data.
	 * @param array $blocks Optional blocks to render.
	 * @return string Block markup.
	 */
	private static function render_text_column( array $data, array $blocks = array() ): string {
		// If blocks array provided, render them instead - use inner_block to avoid Gutenberg comments
		if ( ! empty( $blocks ) ) {
			$markup  = "<!-- wp:column {\"verticalAlignment\":\"center\"} -->\n";
			$markup .= '<div class="wp-block-column is-vertically-aligned-center">' . "\n";
			foreach ( $blocks as $block ) {
				$markup .= self::render_inner_block( $block );
			}
			$markup .= "</div>\n";
			$markup .= "<!-- /wp:column -->\n";
			return $markup;
		}

		$heading    = isset( $data['heading'] ) ? $data['heading'] : ( isset( $data['title'] ) ? $data['title'] : '' );
		$text       = isset( $data['text'] ) ? $data['text'] : ( isset( $data['content'] ) ? $data['content'] : '' );
		$paragraphs = isset( $data['paragraphs'] ) ? $data['paragraphs'] : array();

		$markup  = "<!-- wp:column {\"verticalAlignment\":\"center\"} -->\n";
		$markup .= '<div class="wp-block-column is-vertically-aligned-center">' . "\n";

		if ( $heading ) {
			$markup .= "<!-- wp:heading -->\n";
			$markup .= '<h2 class="wp-block-heading">' . esc_html( $heading ) . '</h2>' . "\n";
			$markup .= "<!-- /wp:heading -->\n";
		}

		if ( $text ) {
			$markup .= "<!-- wp:paragraph -->\n";
			$markup .= '<p>' . esc_html( $text ) . '</p>' . "\n";
			$markup .= "<!-- /wp:paragraph -->\n";
		}

		foreach ( $paragraphs as $paragraph ) {
			$markup .= "<!-- wp:paragraph -->\n";
			$markup .= '<p>' . esc_html( $paragraph ) . '</p>' . "\n";
			$markup .= "<!-- /wp:paragraph -->\n";
		}

		$markup .= "</div>\n";
		$markup .= "<!-- /wp:column -->\n";

		return $markup;
	}

	/**
	 * Render a three-column section.
	 *
	 * @param array<string, mixed> $data Section data.
	 * @return string Block markup.
	 */
	public static function render_three_column( array $data ): string {
		// Support both 'columns' and 'items' keys
		$columns = isset( $data['columns'] ) ? $data['columns'] : array();
		if ( empty( $columns ) && isset( $data['items'] ) && is_array( $data['items'] ) ) {
			$columns = $data['items'];
		}
		$title    = isset( $data['title'] ) ? $data['title'] : '';
		$subtitle = isset( $data['subtitle'] ) ? $data['subtitle'] : '';

		$attributes = array(
			'align'           => 'full',
			'style'           => array(
				'spacing' => array(
					'padding' => array(
						'top'    => '80px',
						'bottom' => '40px',
						'left'   => '40px',
						'right'  => '40px',
					),
				),
			),
			'backgroundColor' => 'white',
		);

		$markup  = '<!-- wp:columns ' . wp_json_encode( $attributes ) . " -->\n";
		$markup .= '<div class="wp-block-columns alignfull has-white-background-color has-background" style="padding-top:80px;padding-right:40px;padding-bottom:40px;padding-left:40px">' . "\n";

		if ( $title || $subtitle ) {
			$markup .= "<!-- wp:column -->\n";
			$markup .= '<div class="wp-block-column">' . "\n";
			if ( $subtitle ) {
				$markup .= "<!-- wp:paragraph {\"align\":\"center\",\"textColor\":\"black\",\"fontSize\":\"small\"} -->\n";
				$markup .= '<p class="has-text-align-center has-black-color has-text-color has-small-font-size">' . esc_html( $subtitle ) . '</p>' . "\n";
				$markup .= "<!-- /wp:paragraph -->\n";
			}
			if ( $title ) {
				$markup .= "<!-- wp:heading {\"textAlign\":\"center\",\"level\":2} -->\n";
				$markup .= '<h2 class="wp-block-heading has-text-align-center">' . esc_html( $title ) . '</h2>' . "\n";
				$markup .= "<!-- /wp:heading -->\n";
			}
			$markup .= "</div>\n";
			$markup .= "<!-- /wp:column -->\n";
		}

		// Render up to 3 columns from items/columns array
		$num_columns = count( $columns );
		if ( $num_columns > 3 ) {
			$num_columns = 3;
		}
		for ( $i = 0; $i < $num_columns; $i++ ) {
			$col     = isset( $columns[ $i ] ) ? $columns[ $i ] : array();
			$markup .= self::render_feature_column( $col );
		}

		$markup .= "</div>\n";
		$markup .= "<!-- /wp:columns -->\n\n";

		return $markup;
	}

	/**
	 * Render a feature column.
	 *
	 * @param array{heading?: string, text?: string} $data Column data.
	 * @return string Block markup.
	 */
	private static function render_feature_column( array $data ): string {
		$heading = isset( $data['heading'] ) ? $data['heading'] : ( isset( $data['title'] ) ? $data['title'] : '' );
		$text    = isset( $data['text'] ) ? $data['text'] : ( isset( $data['content'] ) ? $data['content'] : '' );
		$icon    = isset( $data['icon'] ) ? $data['icon'] : '';

		$markup  = "<!-- wp:column -->\n";
		$markup .= '<div class="wp-block-column">' . "\n";

		// Handle nested blocks array in column - use inner_block to avoid Gutenberg comments
		if ( isset( $data['blocks'] ) && is_array( $data['blocks'] ) ) {
			foreach ( $data['blocks'] as $block ) {
				$markup .= self::render_inner_block( $block );
			}
		} else {
			// Fallback to legacy heading/text/icon
			if ( $icon ) {
				$markup .= "<!-- wp:paragraph {\"align\":\"center\",\"fontSize\":\"extra-large\"} -->\n";
				$markup .= '<p class="has-text-align-center has-extra-large-font-size">' . esc_html( $icon ) . '</p>' . "\n";
				$markup .= "<!-- /wp:paragraph -->\n";
			}

			if ( $heading ) {
				$markup .= "<!-- wp:heading {\"textAlign\":\"center\",\"level\":3} -->\n";
				$markup .= '<h3 class="wp-block-heading has-text-align-center">' . esc_html( $heading ) . '</h3>' . "\n";
				$markup .= "<!-- /wp:heading -->\n";
			}

			if ( $text ) {
				$markup .= "<!-- wp:paragraph {\"align\":\"center\"} -->\n";
				$markup .= '<p class="has-text-align-center">' . esc_html( $text ) . '</p>' . "\n";
				$markup .= "<!-- /wp:paragraph -->\n";
			}
		}

		$markup .= "</div>\n";
		$markup .= "<!-- /wp:column -->\n";

		return $markup;
	}

	/**
	 * Render a footer section.
	 *
	 * @param array<string, mixed> $data Section data.
	 * @return string Block markup.
	 */
	public static function render_footer( array $data ): string {
		$columns = isset( $data['columns'] ) ? $data['columns'] : array();

		$markup  = "<!-- wp:group {\"tagName\":\"footer\",\"align\":\"full\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"60px\",\"bottom\":\"40px\",\"left\":\"40px\",\"right\":\"40px\"}},\"color\":{\"background\":\"#FDF6E3\",\"text\":\"#5D4E37\"}}} -->\n";
		$markup .= '<footer class="wp-block-group alignfull has-text-color has-background" style="color:#5D4E37;background-color:#FDF6E3;padding-top:60px;padding-right:40px;padding-bottom:40px;padding-left:40px">' . "\n";

		$col_attrs = array(
			'align' => 'full',
			'style' => array(
				'spacing' => array(
					'columnGap' => '40px',
					'rowGap'    => '40px',
					'padding'   => array(
						'left'  => '40px',
						'right' => '40px',
					),
				),
			),
		);

		$markup .= '<!-- wp:columns ' . wp_json_encode( $col_attrs ) . " -->\n";
		$markup .= '<div class="wp-block-columns alignfull" style="padding-right:40px;padding-left:40px">' . "\n";

		$col_count = count( $columns );
		if ( $col_count < 1 ) {
			$col_count = 4;
			$columns   = array(
				array(
					'type' => 'about',
					'text' => 'Company description.',
				),
				array(
					'type'  => 'links',
					'items' => array( 'Home', 'About', 'Services', 'Contact' ),
				),
				array(
					'type'     => 'social',
					'networks' => array( 'Twitter', 'LinkedIn', 'Instagram' ),
				),
				array(
					'type'  => 'contact',
					'email' => 'info@example.com',
					'phone' => '555-123-4567',
				),
			);
		}

		foreach ( $columns as $col ) {
			$markup .= self::render_footer_column( $col );
		}

		$markup .= "</div>\n";
		$markup .= "<!-- /wp:columns -->\n";

		$markup .= "<!-- wp:separator {\"align\":\"full\",\"style\":{\"color\":{\"background\":\"#E8DCC8\"}}} -->\n";
		$markup .= '<hr class="wp-block-separator alignfull has-text-color has-alpha-channel-opacity has-background" style="background-color:#E8DCC8;color:#E8DCC8"/>' . "\n";
		$markup .= "<!-- /wp:separator -->\n";

		$markup .= "<!-- wp:paragraph {\"align\":\"center\",\"style\":{\"color\":{\"text\":\"#8B7355\"}}} -->\n";
		$markup .= '<p class="has-text-align-center has-text-color" style="color:#8B7355">© ' . gmdate( 'Y' ) . ' Company Name. All rights reserved.</p>' . "\n";
		$markup .= "<!-- /wp:paragraph -->\n";

		$markup .= "</footer>\n";
		$markup .= "<!-- /wp:group -->\n\n";

		return $markup;
	}

	/**
	 * Render a footer column.
	 *
	 * @param array<string, mixed> $data Column data.
	 * @return string Block markup.
	 */
	private static function render_footer_column( array $data ): string {
		$type = isset( $data['type'] ) ? $data['type'] : 'text';

		$markup  = "<!-- wp:column -->\n";
		$markup .= '<div class="wp-block-column">' . "\n";

		$structured_types = array( 'about', 'links', 'social', 'contact' );
		if ( ! in_array( $type, $structured_types, true ) ) {
			$col_heading = isset( $data['heading'] ) ? $data['heading'] : ( isset( $data['title'] ) ? $data['title'] : '' );
			$col_text    = isset( $data['content'] ) ? $data['content'] : ( isset( $data['text'] ) ? $data['text'] : '' );
			if ( $col_heading ) {
				$markup .= "<!-- wp:heading {\"textAlign\":\"center\",\"level\":3,\"style\":{\"color\":{\"text\":\"#8B7355\"}}} -->\n";
				$markup .= '<h3 class="wp-block-heading has-text-align-center has-text-color" style="color:#8B7355">' . esc_html( $col_heading ) . '</h3>' . "\n";
				$markup .= "<!-- /wp:heading -->\n";
			}
			if ( $col_text ) {
				$markup .= "<!-- wp:paragraph {\"align\":\"center\",\"style\":{\"color\":{\"text\":\"#5D4E37\"}}} -->\n";
				$markup .= '<p class="has-text-align-center has-text-color" style="color:#5D4E37">' . nl2br( esc_html( $col_text ) ) . '</p>' . "\n";
				$markup .= "<!-- /wp:paragraph -->\n";
			}
		} else {
			switch ( $type ) {
				case 'about':
					$text    = isset( $data['text'] ) ? $data['text'] : 'Company description.';
					$markup .= "<!-- wp:heading {\"textAlign\":\"center\",\"level\":3,\"style\":{\"color\":{\"text\":\"#8B7355\"}}} -->\n";
					$markup .= '<h3 class="wp-block-heading has-text-align-center has-text-color" style="color:#8B7355">About</h3>' . "\n";
					$markup .= "<!-- /wp:heading -->\n";
					$markup .= "<!-- wp:paragraph {\"align\":\"center\",\"style\":{\"color\":{\"text\":\"#5D4E37\"}}} -->\n";
					$markup .= '<p class="has-text-align-center has-text-color" style="color:#5D4E37">' . esc_html( $text ) . '</p>' . "\n";
					$markup .= "<!-- /wp:paragraph -->\n";
					break;

				case 'links':
					$items   = isset( $data['items'] ) ? $data['items'] : array();
					$links   = isset( $data['links'] ) ? $data['links'] : array();
					$col_title = isset( $data['title'] ) ? $data['title'] : 'Quick Links';
					$markup .= "<!-- wp:heading {\"textAlign\":\"center\",\"level\":3,\"style\":{\"color\":{\"text\":\"#8B7355\"}}} -->\n";
					$markup .= '<h3 class="wp-block-heading has-text-align-center has-text-color" style="color:#8B7355">' . esc_html( $col_title ) . '</h3>' . "\n";
					$markup .= "<!-- /wp:heading -->\n";
					$markup .= "<!-- wp:list {\"style\":{\"color\":{\"text\":\"#5D4E37\"}}} -->\n";
					$markup .= '<ul class="has-text-align-center has-text-color" style="color:#5D4E37">';
					foreach ( $items as $i => $item ) {
						$link = isset( $links[ $i ] ) ? $links[ $i ] : '#';
						$markup .= '<li><a href="' . esc_url( $link ) . '">' . esc_html( $item ) . '</a></li>';
					}
					$markup .= "</ul>\n";
					$markup .= "<!-- /wp:list -->\n";
					break;

				case 'social':
					$networks = isset( $data['networks'] ) ? $data['networks'] : array();
					$urls     = isset( $data['urls'] ) ? $data['urls'] : array();
					$col_title = isset( $data['title'] ) ? $data['title'] : 'Connect';
					$markup  .= "<!-- wp:heading {\"textAlign\":\"center\",\"level\":3,\"style\":{\"color\":{\"text\":\"#8B7355\"}}} -->\n";
					$markup  .= '<h3 class="wp-block-heading has-text-align-center has-text-color" style="color:#8B7355">' . esc_html( $col_title ) . '</h3>' . "\n";
					$markup  .= "<!-- /wp:heading -->\n";
					$markup  .= "<!-- wp:list {\"style\":{\"color\":{\"text\":\"#5D4E37\"}}} -->\n";
					$markup  .= '<ul class="has-text-align-center has-text-color" style="color:#5D4E37">';
					foreach ( $networks as $i => $network ) {
						$url = isset( $urls[ $i ] ) ? $urls[ $i ] : '#';
						$markup .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( ucfirst( $network ) ) . '</a></li>';
					}
					$markup .= "</ul>\n";
					$markup .= "<!-- /wp:list -->\n";
					break;

				case 'contact':
					$email        = isset( $data['email'] ) ? $data['email'] : '';
					$phone        = isset( $data['phone'] ) ? $data['phone'] : '';
					$markup      .= "<!-- wp:heading {\"textAlign\":\"center\",\"level\":3,\"style\":{\"color\":{\"text\":\"#8B7355\"}}} -->\n";
					$markup      .= '<h3 class="wp-block-heading has-text-align-center has-text-color" style="color:#8B7355">Contact</h3>' . "\n";
					$markup      .= "<!-- /wp:heading -->\n";
					$markup      .= "<!-- wp:paragraph {\"align\":\"center\",\"style\":{\"color\":{\"text\":\"#5D4E37\"}}} -->\n";
					$contact_text = '';
					if ( $email ) {
						$contact_text .= '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
					}
					if ( $phone ) {
						if ( $contact_text ) {
							$contact_text .= '<br/>';
						}
						$contact_text .= esc_html( $phone );
					}
					$markup .= '<p class="has-text-align-center has-text-color" style="color:#5D4E37">' . $contact_text . '</p>' . "\n";
					$markup .= "<!-- /wp:paragraph -->\n";
					break;

				default:
					// Fallback: render any text/content key that may be present.
					$text = isset( $data['text'] ) ? $data['text'] : ( isset( $data['content'] ) ? $data['content'] : '' );
					if ( $text ) {
						$markup .= "<!-- wp:paragraph {\"align\":\"center\",\"style\":{\"color\":{\"text\":\"#5D4E37\"}}} -->\n";
						$markup .= '<p class="has-text-align-center has-text-color" style="color:#5D4E37">' . nl2br( esc_html( $text ) ) . '</p>' . "\n";
						$markup .= "<!-- /wp:paragraph -->\n";
					}
			}
		}

		$markup .= "</div>\n";
		$markup .= "<!-- /wp:column -->\n";

		return $markup;
	}

	/**
	 * Render all sections and return complete page content.
	 *
	 * @param array<int, array{type: string}> $sections Array of section data.
	 * @return string Complete block markup.
	 */
	public static function render_sections( array $sections ): string {
		$markup = '';

		foreach ( $sections as $section ) {
			$type = isset( $section['type'] ) ? $section['type'] : '';

			switch ( $type ) {
				case 'cover':
					$markup .= self::render_cover( $section );
					break;
				case 'two-column':
					$markup .= self::render_two_column( $section );
					break;
				case 'three-column':
					$markup .= self::render_three_column( $section );
					break;
				case 'footer':
					$markup .= self::render_footer( $section );
					break;
				case 'block':
					$markup .= self::render_block( $section );
					break;
				default:
					// If no type specified but has blocks array, render each block
					if ( isset( $section['blocks'] ) && is_array( $section['blocks'] ) ) {
						foreach ( $section['blocks'] as $block ) {
							$markup .= self::render_block( $block );
						}
					} elseif ( isset( $section['block'] ) ) {
						// Has block key - treat as single block section
						$markup .= self::render_block( $section );
					} elseif ( isset( $section['items'] ) && is_array( $section['items'] ) ) {
						// Has items array - render each as block
						foreach ( $section['items'] as $block ) {
							$markup .= self::render_block( $block );
						}
					}
			}
		}

		return $markup;
	}

	/**
	 * Map input fields to Gutenberg block attributes.
	 *
	 * @param array<string, mixed> $data Input data.
	 * @return array<string, mixed> Gutenberg attributes.
	 */
	private static function map_attributes( array $data ): array {
		$attrs = array();

		$field_map = array(
			'align'         => 'align',
			'alignText'     => 'alignText',
			'alignContent'  => 'alignContent',
			'width'         => 'width',
			'height'        => 'height',
			'sizeSlug'      => 'sizeSlug',
			'className'     => 'className',
			'textColor'     => 'textColor',
			'bgColor'       => 'backgroundColor',
			'url'           => 'url',
			'src'           => 'url',
			'image'         => 'url',
			'alt'           => 'alt',
			'link'          => 'url',
			'linkDestination' => 'linkDestination',
			'id'            => 'id',
			'level'         => 'level',
			'numberOfItems' => 'numberOfItems',
			'columns'       => 'columns',
			'rows'          => 'rows',
			'minHeight'     => 'minHeight',
			'minHeightUnit' => 'minHeightUnit',
			'overlayColor'  => 'overlayColor',
			'overlayUrl'    => 'overlayUrl',
			'hasParallax'   => 'hasParallax',
			'isDark'        => 'isDark',
			'focalPoint'    => 'focalPoint',
			'textAlign'     => 'textAlign',
			'fontSize'      => 'fontSize',
			'dimRatio'      => 'dimRatio',
			'style'         => 'style',
			'borderColor'   => 'borderColor',
			'lock'          => 'lock',
			'allowedBlocks' => 'allowedBlocks',
			'templateLock'  => 'templateLock',
			'verticalAlignment' => 'verticalAlignment',
			'layout'        => 'layout',
			'customOverlayColor' => 'customOverlayColor',
			'isUserOverlayColor' => 'isUserOverlayColor',
		);

		foreach ( $field_map as $input_key => $attr_key ) {
			if ( isset( $data[ $input_key ] ) ) {
				$attrs[ $attr_key ] = $data[ $input_key ];
			}
		}

		return $attrs;
	}

	/**
	 * Render a generic Gutenberg block.
	 *
	 * @param array<string, mixed> $data Section data with 'block' key.
	 * @return string Block markup.
	 */
	public static function render_block( array $data ): string {
		// Handle both "block" and "blockName" keys for block type specification
		$block_name = isset( $data['block'] ) ? $data['block'] : ( isset( $data['blockName'] ) ? $data['blockName'] : '' );

		if ( empty( $block_name ) ) {
			return '<!-- wp:paragraph --><p>Error: block name is required</p><!-- /wp:paragraph -->';
		}

		$valid_blocks = array(
			'core/paragraph',
			'core/heading',
			'core/image',
			'core/gallery',
			'core/cover',
			'core/group',
			'core/columns',
			'core/column',
			'core/buttons',
			'core/button',
			'core/spacer',
			'core/separator',
			'core/quote',
			'core/code',
			'core/preformatted',
			'core/list',
			'core/list-item',
			'core/verse',
			'core/audio',
			'core/video',
			'core/file',
			'core/embed',
			'core/archives',
			'core/calendar',
			'core/categories',
			'core/latest-posts',
			'core/read-more',
			'core/search',
			'core/rss',
			'core/social-links',
			'core/social-link',
			'core/tag-cloud',
		);

		if ( ! in_array( $block_name, $valid_blocks, true ) ) {
			return '<!-- wp:paragraph --><p>Error: Unknown block "' . esc_html( $block_name ) . '". Valid blocks: ' . implode( ', ', $valid_blocks ) . '</p><!-- /wp:paragraph -->';
		}

		// Handle buttons block with items array
		if ( 'core/buttons' === $block_name ) {
			return self::render_buttons_block( $data );
		}

		// Handle columns block
		if ( 'core/columns' === $block_name ) {
			return self::render_columns_block( $data );
		}

		// Handle single button
		if ( 'core/button' === $block_name ) {
			return self::render_single_button( $data );
		}

		// Handle list block
		if ( 'core/list' === $block_name ) {
			return self::render_list_block( $data );
		}

		// Handle cover block
		if ( 'core/cover' === $block_name ) {
			return self::render_cover_block( $data );
		}

		// Handle separator block (needs specific JSON attrs for default style)
		if ( 'core/separator' === $block_name ) {
			if ( isset( $data['style']['color']['background'] ) ) {
				$bg      = $data['style']['color']['background'];
				$sep_attrs = array( 'align' => 'full', 'style' => array( 'color' => array( 'background' => $bg ) ) );
				$markup  = '<!-- wp:separator ' . wp_json_encode( $sep_attrs ) . " -->\n";
				$markup .= '<hr class="wp-block-separator alignfull has-text-color has-alpha-channel-opacity has-background" style="background-color:' . esc_attr( $bg ) . ';color:' . esc_attr( $bg ) . '"/>' . "\n";
				$markup .= "<!-- /wp:separator -->\n";
			} else {
				$sep_attrs = array( 'opacity' => 'css', 'className' => 'has-text-color has-background has-gray-background-color' );
				$markup    = '<!-- wp:separator ' . wp_json_encode( $sep_attrs ) . " -->\n";
				$markup   .= '<hr class="wp-block-separator has-css-opacity has-text-color has-background has-gray-background-color"/>' . "\n";
				$markup   .= "<!-- /wp:separator -->\n";
			}
			return $markup;
		}

		$attrs = self::map_attributes( $data );
		unset( $attrs['innerHTML'] );

		// For headings: remap text-alignment 'align' to 'textAlign', drop default level.
		if ( 'core/heading' === $block_name ) {
			if ( isset( $attrs['align'] ) && ! in_array( $attrs['align'], array( 'wide', 'full' ), true ) ) {
				$attrs['textAlign'] = $attrs['align'];
				unset( $attrs['align'] );
			}
			if ( isset( $attrs['level'] ) && 2 === $attrs['level'] ) {
				unset( $attrs['level'] );
			}
		}

		$content = isset( $data['content'] ) ? $data['content'] : '';

		if ( isset( $data['innerContent'] ) && is_array( $data['innerContent'] ) ) {
			$content = implode( "\n", $data['innerContent'] );
		}

		$block_name_display = str_replace( 'core/', '', $block_name );

		$markup  = '<!-- wp:' . $block_name_display;
		if ( ! empty( $attrs ) ) {
			$markup .= ' ' . wp_json_encode( $attrs );
		}
		$markup .= " -->\n";

		$markup .= self::render_block_inner( $block_name, $data, $content );

		$markup .= "<!-- /wp:" . $block_name_display . " -->\n";

		return $markup;
	}

	/**
	 * Render inner content based on block type.
	 *
	 * @param string               $block_name Block name.
	 * @param array<string, mixed> $data       Block data.
	 * @param string               $content    Inner content.
	 * @return string Inner HTML.
	 */
	private static function render_block_inner( string $block_name, array $data, string $content ): string {
		switch ( $block_name ) {
			case 'core/paragraph':
				return '<p' . self::get_text_class( $data ) . '>' . esc_html( $content ) . '</p>';

			case 'core/heading':
				$level   = isset( $data['level'] ) ? (int) $data['level'] : 2;
				$tag     = 'h' . $level;
				$classes = array( 'wp-block-heading' );
				$align   = ! empty( $data['align'] ) ? $data['align'] : ( ! empty( $data['textAlign'] ) ? $data['textAlign'] : '' );
				if ( $align ) {
					$classes[] = 'has-text-align-' . $align;
				}
				if ( ! empty( $data['textColor'] ) ) {
					$classes[] = 'has-' . $data['textColor'] . '-color';
					$classes[] = 'has-text-color';
				}
				if ( ! empty( $data['fontSize'] ) ) {
					$classes[] = 'has-' . $data['fontSize'] . '-font-size';
				}
				return '<' . $tag . ' class="' . implode( ' ', $classes ) . '">' . esc_html( $content ) . '</' . $tag . '>';

			case 'core/image':
				$url             = isset( $data['url'] ) ? $data['url'] : '';
				$alt             = isset( $data['alt'] ) ? $data['alt'] : '';
				$link_destination = isset( $data['linkDestination'] ) ? $data['linkDestination'] : 'none';
				$class_name      = isset( $data['className'] ) ? $data['className'] : 'size-full';
				$figure_classes  = 'wp-block-image ' . $class_name;

				if ( 'none' !== $link_destination && isset( $data['link'] ) && $data['link'] ) {
					return '<figure class="' . $figure_classes . '"><a href="' . esc_url( $data['link'] ) . '"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"/></a></figure>';
				}
				return '<figure class="' . $figure_classes . '"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"/></figure>';

			case 'core/cover':
				return self::render_cover_inner( $data );

			case 'core/group':
				$attrs      = self::map_attributes( $data );
				$class_name = isset( $attrs['className'] ) ? ' ' . $attrs['className'] : '';
				$align      = isset( $attrs['align'] ) ? ' align' . $attrs['align'] : '';
				$style      = '';
				if ( isset( $data['style'] ) && isset( $data['style']['spacing'] ) && isset( $data['style']['spacing']['padding'] ) ) {
					$padding = $data['style']['spacing']['padding'];
					$style   = ' style="';
					if ( isset( $padding['top'] ) ) {
						$style .= 'padding-top:' . esc_attr( $padding['top'] ) . ';';
					}
					if ( isset( $padding['bottom'] ) ) {
						$style .= 'padding-bottom:' . esc_attr( $padding['bottom'] ) . ';';
					}
					$style .= '"';
				}
				return '<div class="wp-block-group' . $align . $class_name . '"' . $style . '>' . self::render_children( $data ) . '</div>';

			case 'core/spacer':
				$height = isset( $data['height'] ) ? $data['height'] : '100px';
				return "\n" . '<div style="height:' . esc_attr( $height ) . '" aria-hidden="true" class="wp-block-spacer"></div>' . "\n";

			case 'core/separator':
				$style = '';
				if ( isset( $data['color'] ) ) {
					$style = ' style="background-color:' . esc_attr( $data['color'] ) . '"';
				}
				return '<hr class="wp-block-separator has-text-color has-background has-' . esc_attr( isset( $data['color'] ) ? $data['color'] : 'gray' ) . '-background-color"' . $style . '/>';

			case 'core/quote':
				$cite = isset( $data['cite'] ) ? $data['cite'] : ( isset( $data['citation'] ) ? $data['citation'] : '' );
				return '<blockquote class="wp-block-quote"><!-- wp:paragraph -->' . "\n" . '<p>' . esc_html( $content ) . '</p>' . "\n" . '<!-- /wp:paragraph -->' . ( $cite ? '<cite>' . esc_html( $cite ) . '</cite>' : '' ) . '</blockquote>';

			case 'core/code':
				return '<code class="wp-block-code">' . esc_html( $content ) . '</code>';

			case 'core/preformatted':
				return '<pre class="wp-block-preformatted"><code>' . esc_html( $content ) . '</code></pre>';

			case 'core/verse':
				return '<pre class="wp-block-verse">' . esc_html( $content ) . '</pre>';

			case 'core/file':
				$url  = isset( $data['url'] ) ? $data['url'] : '';
				$text = isset( $data['text'] ) ? $data['text'] : basename( $url );
				return '<div class="wp-block-file"><a href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a></div>';

			case 'core/audio':
			case 'core/video':
				$url = isset( $data['url'] ) ? $data['url'] : '';
				return '<div class="wp-block-' . str_replace( 'core/', '', $block_name ) . '"><audio controls src="' . esc_url( $url ) . '"></audio></div>';

			default:
				return $content;
		}
	}

	/**
	 * Get text class attribute for text-based blocks.
	 *
	 * @param array<string, mixed> $data Block data.
	 * @return string Class attribute string.
	 */
	private static function get_text_class( array $data ): string {
		$classes = array();

		if ( ! empty( $data['align'] ) ) {
			$classes[] = 'has-text-align-' . $data['align'];
		}
		if ( ! empty( $data['textColor'] ) ) {
			$classes[] = 'has-' . $data['textColor'] . '-color';
			$classes[] = 'has-text-color';
		}
		if ( ! empty( $data['bgColor'] ) ) {
			$classes[] = 'has-' . $data['bgColor'] . '-background-color';
			$classes[] = 'has-background';
		}
		if ( ! empty( $data['fontSize'] ) ) {
			$classes[] = 'has-' . $data['fontSize'] . '-font-size';
		}

		if ( empty( $classes ) ) {
			return '';
		}

		return ' class="' . implode( ' ', $classes ) . '"';
	}

	/**
	 * Render a complete cover block with outer wrapper.
	 *
	 * @param array<string, mixed> $data Cover data.
	 * @return string Complete cover block markup.
	 */
	private static function render_cover_block( array $data ): string {
		$attrs      = self::map_attributes( $data );
		$block_name = 'cover';

		$url             = isset( $attrs['url'] ) ? $attrs['url'] : '';
		$align           = isset( $attrs['align'] ) ? ' align' . $attrs['align'] : '';
		$class_name      = isset( $attrs['className'] ) ? ' ' . $attrs['className'] : '';
		$size_slug       = isset( $attrs['sizeSlug'] ) ? ' size-' . $attrs['sizeSlug'] : ' size-large';
		$dim_ratio       = isset( $attrs['dimRatio'] ) ? $attrs['dimRatio'] : 0;
		$custom_overlay  = isset( $attrs['customOverlayColor'] ) ? $attrs['customOverlayColor'] : '';

		unset( $attrs['innerHTML'] );

		$markup  = '<!-- wp:' . $block_name;
		if ( ! empty( $attrs ) ) {
			$markup .= ' ' . wp_json_encode( $attrs );
		}
		$markup .= " -->\n";

		$markup .= '<div class="wp-block-cover' . $align . $class_name . '">';

		if ( $url ) {
			$markup .= '<img class="wp-block-cover__image-background' . $size_slug . '" alt="" src="' . esc_url( $url ) . '" data-object-fit="cover"/>';
		}

		if ( $custom_overlay ) {
			$markup .= '<span aria-hidden="true" class="wp-block-cover__background has-background-dim-0 has-background-dim" style="background-color:' . esc_attr( $custom_overlay ) . '"></span>';
		}

		$markup .= '<div class="wp-block-cover__inner-container">' . "\n";

		if ( isset( $data['children'] ) && is_array( $data['children'] ) ) {
			foreach ( $data['children'] as $child ) {
				$markup .= self::render_block( $child );
			}
		}

		$markup .= "</div>\n";
		$markup .= "</div>\n";
		$markup .= "<!-- /wp:" . $block_name . " -->\n";

		return $markup;
	}

	/**
	 * Render inner content for cover block.
	 *
	 * @param array<string, mixed> $data Cover data.
	 * @return string Inner HTML.
	 */
	private static function render_cover_inner( array $data ): string {
		$url        = isset( $data['url'] ) ? $data['url'] : '';
		$min_height = isset( $data['minHeight'] ) ? (int) $data['minHeight'] : 600;
		$dim_ratio  = isset( $data['dimRatio'] ) ? $data['dimRatio'] : 40;

		$markup  = '<div class="wp-block-cover__inner-container">' . "\n";

		if ( isset( $data['children'] ) && is_array( $data['children'] ) ) {
			foreach ( $data['children'] as $child ) {
				$markup .= self::render_block( $child );
			}
		}

		$markup .= "</div>\n";

		return $markup;
	}

	/**
	 * Render buttons block with nested button blocks.
	 *
	 * @param array<string, mixed> $data Buttons data.
	 * @return string Buttons block markup.
	 */
	public static function render_buttons_block( array $data ): string {
		$attrs = self::map_attributes( $data );
		unset( $attrs['innerHTML'] );

		$markup  = '<!-- wp:buttons';
		if ( ! empty( $attrs ) ) {
			$markup .= ' ' . wp_json_encode( $attrs );
		}
		$markup .= " -->\n";
		$markup .= '<div class="wp-block-buttons">' . "\n";

		// Support "buttons" array, "items" array, or "children" array
		$buttons = isset( $data['buttons'] ) ? $data['buttons'] : array();
		if ( empty( $buttons ) && isset( $data['items'] ) && is_array( $data['items'] ) ) {
			$buttons = $data['items'];
		}
		if ( empty( $buttons ) && isset( $data['children'] ) && is_array( $data['children'] ) ) {
			$buttons = $data['children'];
		}

		foreach ( $buttons as $btn ) {
			// Normalize button data - ensure it has block name
			if ( is_array( $btn ) ) {
				$btn['block'] = isset( $btn['block'] ) ? $btn['block'] : 'core/button';
				$markup      .= self::render_block( $btn );
			} else {
				// String button text
				$markup .= "<!-- wp:button -->\n";
				$markup .= '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">' . esc_html( $btn ) . '</a></div>' . "\n";
				$markup .= "<!-- /wp:button -->\n";
			}
		}

		$markup .= "</div>\n";
		$markup .= "<!-- /wp:buttons -->\n";

		return $markup;
	}

	/**
	 * Render a single button.
	 *
	 * @param array<string, mixed> $data Button data.
	 * @return string Button block markup.
	 */
	private static function render_single_button( array $data ): string {
		$attrs = self::map_attributes( $data );
		unset( $attrs['innerHTML'] );

		$url     = isset( $data['url'] ) ? $data['url'] : ( isset( $data['link'] ) ? $data['link'] : '' );
		$text    = isset( $data['text'] ) ? $data['text'] : 'Button';
		$target  = isset( $data['newTab'] ) && $data['newTab'] ? ' target="_blank" rel="noopener"' : '';
		$variant = isset( $data['variant'] ) ? $data['variant'] : '';

		$classes = array( 'wp-block-button__link', 'wp-element-button' );
		if ( isset( $data['bgColor'] ) ) {
			$classes[] = 'has-' . $data['bgColor'] . '-background-color';
			$classes[] = 'has-background';
		}
		if ( isset( $data['textColor'] ) ) {
			$classes[] = 'has-' . $data['textColor'] . '-color';
		}
		if ( isset( $data['className'] ) ) {
			$classes[] = $data['className'];
		}

		$class_str    = implode( ' ', $classes );
		$is_outline   = ( 'outline' === $variant );
		$wrapper_attr = $is_outline ? ' {"className":"is-style-outline"}' : '';
		$wrapper_cls  = $is_outline ? ' is-style-outline' : '';

		$markup  = '<!-- wp:button' . $wrapper_attr . ' --><div class="wp-block-button' . $wrapper_cls . '">' . "\n";
		$markup .= '<a class="' . esc_attr( $class_str ) . '" href="' . esc_url( $url ) . '"' . $target . '>' . esc_html( $text ) . '</a>' . "\n";
		$markup .= "</div><!-- /wp:button -->\n";

		return $markup;
	}

	/**
	 * Render list block.
	 *
	 * @param array<string, mixed> $data List data.
	 * @return string List block markup.
	 */
	private static function render_list_block( array $data ): string {
		$attrs = self::map_attributes( $data );

		$markup  = '<!-- wp:list';
		if ( ! empty( $attrs ) ) {
			$markup .= ' ' . wp_json_encode( $attrs );
		}
		$markup .= " -->\n";

		$items = isset( $data['items'] ) ? $data['items'] : array();
		if ( ! empty( $items ) ) {
			$markup .= '<ul>' . "\n";
			foreach ( $items as $item ) {
				$markup .= '<li>' . esc_html( $item ) . '</li>' . "\n";
			}
			$markup .= '</ul>' . "\n";
		}

		$markup .= "<!-- /wp:list -->\n";

		return $markup;
	}

	/**
	 * Render columns block with nested column blocks.
	 *
	 * @param array<string, mixed> $data Columns data.
	 * @return string Columns block markup.
	 */
	private static function render_columns_block( array $data ): string {
		$attrs = self::map_attributes( $data );

		$markup  = '<!-- wp:columns';
		if ( ! empty( $attrs ) ) {
			$markup .= ' ' . wp_json_encode( $attrs );
		}
		$markup .= " -->\n";

		$align = isset( $attrs['align'] ) ? ' align' . $attrs['align'] : '';
		$markup .= '<div class="wp-block-columns' . $align . ' is-not-stacked-on-mobile">' . "\n";

		$columns = isset( $data['columns'] ) ? $data['columns'] : array();
		if ( isset( $data['children'] ) && is_array( $data['children'] ) ) {
			$columns = $data['children'];
		}

		foreach ( $columns as $col ) {
			$col['block'] = 'core/column';
			$markup      .= self::render_column_block( $col );
		}

		$markup .= "</div>\n";
		$markup .= "<!-- /wp:columns -->\n";

		return $markup;
	}

	/**
	 * Render a single column block.
	 *
	 * @param array<string, mixed> $data Column data.
	 * @return string Column block markup.
	 */
	private static function render_column_block( array $data ): string {
		$attrs = self::map_attributes( $data );

		$markup  = '<!-- wp:column';
		if ( ! empty( $attrs ) ) {
			$markup .= ' ' . wp_json_encode( $attrs );
		}
		$markup .= " -->\n";

		$vertical_align = isset( $attrs['verticalAlignment'] ) ? $attrs['verticalAlignment'] : '';
		$vert_class     = $vertical_align ? ' is-vertically-aligned-' . $vertical_align : '';
		$markup .= '<div class="wp-block-column' . $vert_class . '">' . "\n";

		if ( isset( $data['children'] ) && is_array( $data['children'] ) ) {
			foreach ( $data['children'] as $child ) {
				$markup .= self::render_block( $child );
			}
		}

		$markup .= "</div>\n";
		$markup .= "<!-- /wp:column -->\n";

		return $markup;
	}

	/**
	 * Render child blocks recursively.
	 *
	 * @param array<string, mixed> $data Block data with 'children' key.
	 * @return string Rendered children HTML.
	 */
	private static function render_children( array $data ): string {
		$markup = '';
		if ( isset( $data['children'] ) && is_array( $data['children'] ) ) {
			foreach ( $data['children'] as $child ) {
				$markup .= self::render_block( $child );
			}
		}
		return $markup;
	}
}
