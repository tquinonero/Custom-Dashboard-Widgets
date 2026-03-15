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

	// ---------------------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------------------

	/**
	 * Sanitize inline HTML content.
	 *
	 * Allows a safe subset of inline tags so that content values may contain
	 * <strong>, <em>, <a>, <br>, <code>, <span>, and <s> without being
	 * stripped. All other tags and attributes are removed.
	 *
	 * Use this instead of esc_html() for any field that may legitimately carry
	 * inline markup (headings, paragraphs, button labels, etc.).
	 *
	 * @param string $content Raw content, possibly containing inline HTML.
	 * @return string Sanitized content safe for direct output inside a block.
	 */
	private static function sanitize_content( string $content ): string {
		$allowed = array(
			'a'      => array( 'href' => true, 'title' => true, 'target' => true, 'rel' => true ),
			'br'     => array(),
			'em'     => array(),
			'strong' => array(),
			'code'   => array(),
			'span'   => array( 'class' => true, 'style' => true ),
			's'      => array(),
			'sup'    => array(),
			'sub'    => array(),
		);
		return wp_kses( $content, $allowed );
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
			'align'              => 'align',
			'alignText'          => 'alignText',
			'alignContent'       => 'alignContent',
			'width'              => 'width',
			'height'             => 'height',
			'sizeSlug'           => 'sizeSlug',
			'className'          => 'className',
			'textColor'          => 'textColor',
			'bgColor'            => 'backgroundColor',
			'backgroundColor'    => 'backgroundColor',
			'url'                => 'url',
			'src'                => 'url',
			'image'              => 'url',
			'alt'                => 'alt',
			'link'               => 'url',
			'linkDestination'    => 'linkDestination',
			'id'                 => 'id',
			'level'              => 'level',
			'numberOfItems'      => 'numberOfItems',
			'columns'            => 'columns',
			'rows'               => 'rows',
			'minHeight'          => 'minHeight',
			'minHeightUnit'      => 'minHeightUnit',
			'overlayColor'       => 'overlayColor',
			'overlayUrl'         => 'overlayUrl',
			'hasParallax'        => 'hasParallax',
			'isDark'             => 'isDark',
			'focalPoint'         => 'focalPoint',
			'textAlign'          => 'textAlign',
			'fontSize'           => 'fontSize',
			'dimRatio'           => 'dimRatio',
			'style'              => 'style',
			'borderColor'        => 'borderColor',
			'lock'               => 'lock',
			'allowedBlocks'      => 'allowedBlocks',
			'templateLock'       => 'templateLock',
			'verticalAlignment'  => 'verticalAlignment',
			'layout'             => 'layout',
			'customOverlayColor' => 'customOverlayColor',
			'isUserOverlayColor' => 'isUserOverlayColor',
			'ordered'            => 'ordered',
			'reversed'           => 'reversed',
			'start'              => 'start',
			'caption'            => 'caption',
			'providerNameSlug'   => 'providerNameSlug',
			'responsive'         => 'responsive',
			'type'               => 'type',
		);

		foreach ( $field_map as $input_key => $attr_key ) {
			if ( isset( $data[ $input_key ] ) ) {
				$attrs[ $attr_key ] = $data[ $input_key ];
			}
		}

		// 'type' conflicts with section type key — only keep it when it is a
		// genuine block attribute (e.g. on core/list for ordered/unordered).
		// Remove it for section-level objects where 'type' means 'cover' etc.
		if ( isset( $attrs['type'] ) && in_array( $attrs['type'], array( 'cover', 'two-column', 'three-column', 'footer', 'block' ), true ) ) {
			unset( $attrs['type'] );
		}

		return $attrs;
	}

	/**
	 * Build a CSS class attribute string for text-based blocks.
	 *
	 * @param array<string, mixed> $data Block data.
	 * @return string Class attribute string including leading space, or empty.
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
		if ( ! empty( $data['bgColor'] ) || ! empty( $data['backgroundColor'] ) ) {
			$bg        = ! empty( $data['bgColor'] ) ? $data['bgColor'] : $data['backgroundColor'];
			$classes[] = 'has-' . $bg . '-background-color';
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

	// ---------------------------------------------------------------------------
	// Public entry point
	// ---------------------------------------------------------------------------

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
					// No type specified — try to infer intent.
					if ( isset( $section['blocks'] ) && is_array( $section['blocks'] ) ) {
						foreach ( $section['blocks'] as $block ) {
							$markup .= self::render_block( $block );
						}
					} elseif ( isset( $section['block'] ) ) {
						$markup .= self::render_block( $section );
					} elseif ( isset( $section['items'] ) && is_array( $section['items'] ) ) {
						foreach ( $section['items'] as $block ) {
							$markup .= self::render_block( $block );
						}
					}
			}
		}

		return $markup;
	}

	// ---------------------------------------------------------------------------
	// Generic block renderer
	// ---------------------------------------------------------------------------

	/**
	 * Render a generic Gutenberg block.
	 *
	 * Returns a WP_Error string comment on unknown block names so that failures
	 * are visible in the editor rather than silently corrupting the page.
	 *
	 * @param array<string, mixed> $data Section data with 'block' key.
	 * @return string Block markup.
	 */
	public static function render_block( array $data ): string {
		$block_name = isset( $data['block'] ) ? $data['block'] : ( isset( $data['blockName'] ) ? $data['blockName'] : '' );

		if ( empty( $block_name ) ) {
			// Graceful no-op: missing block key produces nothing rather than an
			// error paragraph that would appear on the live page.
			return '';
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
			'core/html',
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
			// FIX: Return an HTML comment instead of an error paragraph so failures
			// are visible to editors in block markup but invisible to site visitors.
			return '<!-- cdw-render-error: unknown block "' . esc_attr( $block_name ) . '" -->' . "\n";
		}

		// Delegate specialised blocks to dedicated methods.
		switch ( $block_name ) {
			case 'core/buttons':
				return self::render_buttons_block( $data );

			case 'core/columns':
				return self::render_columns_block( $data );

			case 'core/button':
				return self::render_single_button( $data );

			case 'core/list':
				return self::render_list_block( $data );

			case 'core/cover':
				return self::render_cover_block( $data );

			case 'core/separator':
				return self::render_separator_block( $data );

			case 'core/html':
				// Raw HTML block — content is already markup, do not escape.
				$raw = isset( $data['content'] ) ? $data['content'] : '';
				return "<!-- wp:html -->\n" . $raw . "\n<!-- /wp:html -->\n";
		}

		// --- Generic path ---
		$attrs = self::map_attributes( $data );
		unset( $attrs['innerHTML'] );

		// For headings: inline text alignment lives in 'textAlign', not 'align'
		// (which is reserved for wide/full layout). Remap when needed and drop
		// the default level=2 to keep markup clean.
		if ( 'core/heading' === $block_name ) {
			if ( isset( $attrs['align'] ) && ! in_array( $attrs['align'], array( 'wide', 'full' ), true ) ) {
				$attrs['textAlign'] = $attrs['align'];
				unset( $attrs['align'] );
			}
			if ( isset( $attrs['level'] ) && 2 === (int) $attrs['level'] ) {
				unset( $attrs['level'] );
			}
		}

		$content = isset( $data['content'] ) ? $data['content'] : '';
		if ( isset( $data['innerContent'] ) && is_array( $data['innerContent'] ) ) {
			$content = implode( "\n", $data['innerContent'] );
		}

		$slug    = str_replace( 'core/', '', $block_name );
		$markup  = '<!-- wp:' . $slug;
		if ( ! empty( $attrs ) ) {
			$markup .= ' ' . wp_json_encode( $attrs );
		}
		$markup .= " -->\n";
		$markup .= self::render_block_inner( $block_name, $data, $content );
		$markup .= "\n<!-- /wp:" . $slug . " -->\n";

		return $markup;
	}

	/**
	 * Render inner HTML for a block given its name.
	 *
	 * @param string               $block_name Full block name (e.g. core/paragraph).
	 * @param array<string, mixed> $data       Block data.
	 * @param string               $content    Pre-extracted inner content string.
	 * @return string Inner HTML (no block comment delimiters).
	 */
	private static function render_block_inner( string $block_name, array $data, string $content ): string {
		switch ( $block_name ) {
			case 'core/paragraph':
				return '<p' . self::get_text_class( $data ) . '>' . self::sanitize_content( $content ) . '</p>';

			case 'core/heading':
				$level   = isset( $data['level'] ) ? (int) $data['level'] : 2;
				$tag     = 'h' . max( 1, min( 6, $level ) );
				$classes = array( 'wp-block-heading' );
				$align   = ! empty( $data['textAlign'] ) ? $data['textAlign'] : ( ! empty( $data['align'] ) && ! in_array( $data['align'], array( 'wide', 'full' ), true ) ? $data['align'] : '' );
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
				return '<' . $tag . ' class="' . implode( ' ', $classes ) . '">' . self::sanitize_content( $content ) . '</' . $tag . '>';

			case 'core/image':
				$url     = isset( $data['url'] ) ? $data['url'] : '';
				$alt     = isset( $data['alt'] ) ? $data['alt'] : '';
				$caption = isset( $data['caption'] ) ? $data['caption'] : '';
				$link    = isset( $data['link'] ) ? $data['link'] : '';
				$size    = isset( $data['sizeSlug'] ) ? $data['sizeSlug'] : 'full';
				$img_tag = '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"/>';
				if ( $link ) {
					$img_tag = '<a href="' . esc_url( $link ) . '">' . $img_tag . '</a>';
				}
				$cap_tag = $caption ? '<figcaption class="wp-element-caption">' . self::sanitize_content( $caption ) . '</figcaption>' : '';
				return '<figure class="wp-block-image size-' . esc_attr( $size ) . '">' . $img_tag . $cap_tag . '</figure>';

			case 'core/group':
				$attrs     = self::map_attributes( $data );
				$classes   = array( 'wp-block-group' );
				$inline    = '';
				if ( ! empty( $attrs['align'] ) ) {
					$classes[] = 'align' . $attrs['align'];
				}
				if ( ! empty( $attrs['className'] ) ) {
					$classes[] = $attrs['className'];
				}
				if ( ! empty( $data['style']['spacing']['padding'] ) ) {
					$p       = $data['style']['spacing']['padding'];
					$parts   = array();
					foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
						if ( ! empty( $p[ $side ] ) ) {
							$parts[] = 'padding-' . $side . ':' . esc_attr( $p[ $side ] );
						}
					}
					if ( $parts ) {
						$inline = ' style="' . implode( ';', $parts ) . '"';
					}
				}
				$tag_name = isset( $data['tagName'] ) ? $data['tagName'] : 'div';
				$tag_name = in_array( $tag_name, array( 'div', 'section', 'article', 'main', 'aside', 'header', 'footer' ), true ) ? $tag_name : 'div';
				return '<' . $tag_name . ' class="' . implode( ' ', $classes ) . '"' . $inline . '>' . self::render_children( $data ) . '</' . $tag_name . '>';

			case 'core/spacer':
				$height = isset( $data['height'] ) ? $data['height'] : '100px';
				return '<div style="height:' . esc_attr( $height ) . '" aria-hidden="true" class="wp-block-spacer"></div>';

			case 'core/quote':
				$cite    = isset( $data['cite'] ) ? $data['cite'] : ( isset( $data['citation'] ) ? $data['citation'] : '' );
				$inner   = "<!-- wp:paragraph -->\n<p>" . self::sanitize_content( $content ) . "</p>\n<!-- /wp:paragraph -->";
				$cite_el = $cite ? '<cite>' . self::sanitize_content( $cite ) . '</cite>' : '';
				return '<blockquote class="wp-block-quote">' . $inner . $cite_el . '</blockquote>';

			case 'core/code':
				return '<pre class="wp-block-code"><code>' . esc_html( $content ) . '</code></pre>';

			case 'core/preformatted':
				return '<pre class="wp-block-preformatted">' . esc_html( $content ) . '</pre>';

			case 'core/verse':
				return '<pre class="wp-block-verse">' . esc_html( $content ) . '</pre>';

			case 'core/file':
				$url        = isset( $data['url'] ) ? $data['url'] : '';
				$text       = isset( $data['text'] ) ? $data['text'] : basename( $url );
				$show_btn   = ! isset( $data['showDownloadButton'] ) || $data['showDownloadButton'];
				$btn_markup = $show_btn ? '<a href="' . esc_url( $url ) . '" download>' . esc_html( $text ) . '</a>' : '';
				return '<div class="wp-block-file"><a href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a>' . $btn_markup . '</div>';

			// FIX: core/audio and core/video had the same case body — video was
			// outputting an <audio> element inside a wp-block-video wrapper.
			case 'core/audio':
				$url     = isset( $data['url'] ) ? $data['url'] : '';
				$caption = isset( $data['caption'] ) ? $data['caption'] : '';
				$cap_el  = $caption ? '<figcaption>' . self::sanitize_content( $caption ) . '</figcaption>' : '';
				return '<figure class="wp-block-audio"><audio controls src="' . esc_url( $url ) . '"></audio>' . $cap_el . '</figure>';

			case 'core/video':
				$url     = isset( $data['url'] ) ? $data['url'] : '';
				$caption = isset( $data['caption'] ) ? $data['caption'] : '';
				$cap_el  = $caption ? '<figcaption>' . self::sanitize_content( $caption ) . '</figcaption>' : '';
				return '<figure class="wp-block-video"><video controls src="' . esc_url( $url ) . '"></video>' . $cap_el . '</figure>';

			default:
				return self::sanitize_content( $content );
		}
	}

	/**
	 * Render child blocks recursively.
	 *
	 * @param array<string, mixed> $data Block data with optional 'children' key.
	 * @return string Rendered children markup.
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

	// ---------------------------------------------------------------------------
	// Specialised block renderers
	// ---------------------------------------------------------------------------

	/**
	 * Render core/separator block.
	 *
	 * @param array<string, mixed> $data Block data.
	 * @return string Block markup.
	 */
	private static function render_separator_block( array $data ): string {
		if ( ! empty( $data['style']['color']['background'] ) ) {
			$bg      = $data['style']['color']['background'];
			$attrs   = array(
				'align' => 'full',
				'style' => array( 'color' => array( 'background' => $bg ) ),
			);
			$markup  = '<!-- wp:separator ' . wp_json_encode( $attrs ) . " -->\n";
			$markup .= '<hr class="wp-block-separator alignfull has-text-color has-alpha-channel-opacity has-background" style="background-color:' . esc_attr( $bg ) . ';color:' . esc_attr( $bg ) . '"/>' . "\n";
			$markup .= "<!-- /wp:separator -->\n";
		} else {
			$markup  = "<!-- wp:separator -->\n";
			$markup .= '<hr class="wp-block-separator has-alpha-channel-opacity"/>' . "\n";
			$markup .= "<!-- /wp:separator -->\n";
		}
		return $markup;
	}

	/**
	 * Render core/buttons block with nested core/button children.
	 *
	 * @param array<string, mixed> $data Buttons data.
	 * @return string Block markup.
	 */
	public static function render_buttons_block( array $data ): string {
		$attrs = self::map_attributes( $data );
		unset( $attrs['innerHTML'] );

		// Default to centred flex layout if none supplied.
		if ( empty( $attrs['layout'] ) ) {
			$attrs['layout'] = array( 'type' => 'flex', 'justifyContent' => 'center' );
		}

		$markup  = '<!-- wp:buttons ' . wp_json_encode( $attrs ) . " -->\n";
		$markup .= '<div class="wp-block-buttons">' . "\n";

		$buttons = isset( $data['buttons'] ) ? $data['buttons'] : array();
		if ( empty( $buttons ) && isset( $data['items'] ) && is_array( $data['items'] ) ) {
			$buttons = $data['items'];
		}
		if ( empty( $buttons ) && isset( $data['children'] ) && is_array( $data['children'] ) ) {
			$buttons = $data['children'];
		}

		foreach ( $buttons as $btn ) {
			if ( is_array( $btn ) ) {
				$btn['block'] = 'core/button';
				$markup      .= self::render_block( $btn );
			} else {
				// Plain string — treat as button label with no URL.
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
	 * Render a single core/button block.
	 *
	 * @param array<string, mixed> $data Button data.
	 * @return string Block markup.
	 */
	private static function render_single_button( array $data ): string {
		$url     = isset( $data['url'] ) ? $data['url'] : ( isset( $data['link'] ) ? $data['link'] : '' );
		$text    = isset( $data['text'] ) ? $data['text'] : 'Button';
		$target  = ! empty( $data['newTab'] ) ? ' target="_blank" rel="noopener noreferrer"' : '';
		$variant = isset( $data['variant'] ) ? $data['variant'] : '';

		$is_outline  = ( 'outline' === $variant );
		$wrapper_cls = $is_outline ? ' is-style-outline' : '';

		// FIX: Use wp_json_encode for block attributes rather than hardcoded JSON
		// string so special characters and future attribute additions are handled.
		$block_attrs = array();
		if ( $is_outline ) {
			$block_attrs['className'] = 'is-style-outline';
		}
		if ( ! empty( $data['bgColor'] ) ) {
			$block_attrs['backgroundColor'] = $data['bgColor'];
		}
		if ( ! empty( $data['textColor'] ) ) {
			$block_attrs['textColor'] = $data['textColor'];
		}

		$attr_str = ! empty( $block_attrs ) ? ' ' . wp_json_encode( $block_attrs ) : '';

		$link_classes = array( 'wp-block-button__link', 'wp-element-button' );
		if ( ! empty( $data['bgColor'] ) ) {
			$link_classes[] = 'has-' . $data['bgColor'] . '-background-color';
			$link_classes[] = 'has-background';
		}
		if ( ! empty( $data['textColor'] ) ) {
			$link_classes[] = 'has-' . $data['textColor'] . '-color';
			$link_classes[] = 'has-text-color';
		}

		$markup  = '<!-- wp:button' . $attr_str . " -->\n";
		$markup .= '<div class="wp-block-button' . $wrapper_cls . '">';
		$markup .= '<a class="' . esc_attr( implode( ' ', $link_classes ) ) . '" href="' . esc_url( $url ) . '"' . $target . '>' . self::sanitize_content( $text ) . '</a>';
		$markup .= "</div>\n";
		$markup .= "<!-- /wp:button -->\n";

		return $markup;
	}

	/**
	 * Render core/list block using core/list-item children (WP 6.0+ format).
	 *
	 * @param array<string, mixed> $data List data.
	 * @return string Block markup.
	 */
	private static function render_list_block( array $data ): string {
		$attrs   = self::map_attributes( $data );
		$ordered = ! empty( $data['ordered'] );
		$tag     = $ordered ? 'ol' : 'ul';
		$items   = isset( $data['items'] ) ? $data['items'] : array();

		$markup  = '<!-- wp:list ' . wp_json_encode( $attrs ) . " -->\n";
		$markup .= '<' . $tag . '>' . "\n";

		// FIX: Use core/list-item block comments (required since WP 6.0).
		// Bare <li> tags without block comments are parsed as a Classic block.
		foreach ( $items as $item ) {
			$markup .= "<!-- wp:list-item -->\n";
			$markup .= '<li>' . self::sanitize_content( is_string( $item ) ? $item : ( isset( $item['content'] ) ? $item['content'] : '' ) ) . '</li>' . "\n";
			$markup .= "<!-- /wp:list-item -->\n";
		}

		$markup .= '</' . $tag . '>' . "\n";
		$markup .= "<!-- /wp:list -->\n";

		return $markup;
	}

	/**
	 * Render a standalone core/cover block (used via render_block dispatch).
	 *
	 * @param array<string, mixed> $data Cover data.
	 * @return string Block markup.
	 */
	private static function render_cover_block( array $data ): string {
		$url        = isset( $data['url'] ) ? $data['url'] : ( isset( $data['image'] ) ? $data['image'] : '' );
		$image_id   = ! empty( $data['image_id'] ) ? (int) $data['image_id'] : ( ! empty( $data['id'] ) ? (int) $data['id'] : 0 );
		$min_height = isset( $data['minHeight'] ) ? (int) $data['minHeight'] : 400;
		$dim_ratio  = isset( $data['dimRatio'] ) ? (int) $data['dimRatio'] : 50;
		$align      = isset( $data['align'] ) ? $data['align'] : 'full';

		$attrs = array(
			'url'                => $url,
			'dimRatio'           => $dim_ratio,
			'overlayColor'       => 'black',
			'isUserOverlayColor' => true,
			'minHeight'          => $min_height,
			'align'              => $align,
		);
		if ( $image_id ) {
			$attrs['id'] = $image_id;
		}

		$dim_step = max( 0, min( 100, (int) round( $dim_ratio / 10 ) * 10 ) );

		$markup  = '<!-- wp:cover ' . wp_json_encode( $attrs ) . " -->\n";
		$markup .= '<div class="wp-block-cover align' . esc_attr( $align ) . '" style="min-height:' . $min_height . 'px">' . "\n";

		if ( $url ) {
			$img_cls = 'wp-block-cover__image-background' . ( $image_id ? ' wp-image-' . $image_id : '' );
			$markup .= '<img class="' . esc_attr( $img_cls ) . '" alt="" src="' . esc_url( $url ) . '" data-object-fit="cover"/>' . "\n";
		}

		$markup .= '<span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-' . $dim_step . ' has-background-dim"></span>' . "\n";
		$markup .= '<div class="wp-block-cover__inner-container">' . "\n";

		if ( isset( $data['children'] ) && is_array( $data['children'] ) ) {
			foreach ( $data['children'] as $child ) {
				$markup .= self::render_block( $child );
			}
		}

		$markup .= "</div>\n</div>\n<!-- /wp:cover -->\n";

		return $markup;
	}

	/**
	 * Render core/columns block with nested core/column children.
	 *
	 * @param array<string, mixed> $data Columns data.
	 * @return string Block markup.
	 */
	private static function render_columns_block( array $data ): string {
		$attrs = self::map_attributes( $data );
		unset( $attrs['innerHTML'] );

		$align      = ! empty( $attrs['align'] ) ? ' align' . $attrs['align'] : '';
		$markup     = '<!-- wp:columns ' . wp_json_encode( $attrs ) . " -->\n";
		$markup    .= '<div class="wp-block-columns' . $align . '">' . "\n";

		$columns = array();
		if ( isset( $data['children'] ) && is_array( $data['children'] ) ) {
			$columns = $data['children'];
		} elseif ( isset( $data['columns'] ) && is_array( $data['columns'] ) ) {
			$columns = $data['columns'];
		}

		foreach ( $columns as $col ) {
			$col['block'] = 'core/column';
			$markup      .= self::render_column_block( $col );
		}

		$markup .= "</div>\n<!-- /wp:columns -->\n";

		return $markup;
	}

	/**
	 * Render a single core/column block.
	 *
	 * @param array<string, mixed> $data Column data.
	 * @return string Block markup.
	 */
	private static function render_column_block( array $data ): string {
		$attrs = self::map_attributes( $data );
		unset( $attrs['innerHTML'] );

		// FIX: Preserve width attribute for non-equal column layouts.
		// map_attributes maps 'width' → 'width'; ensure it stays in attrs.
		$vert            = ! empty( $attrs['verticalAlignment'] ) ? $attrs['verticalAlignment'] : '';
		$vert_class      = $vert ? ' is-vertically-aligned-' . $vert : '';
		$width_style     = ! empty( $attrs['width'] ) ? ' style="flex-basis:' . esc_attr( $attrs['width'] ) . '"' : '';

		$markup  = '<!-- wp:column ' . wp_json_encode( $attrs ) . " -->\n";
		$markup .= '<div class="wp-block-column' . $vert_class . '"' . $width_style . '>' . "\n";
		$markup .= self::render_children( $data );
		$markup .= "</div>\n<!-- /wp:column -->\n";

		return $markup;
	}

	// ---------------------------------------------------------------------------
	// Section renderers
	// ---------------------------------------------------------------------------

	/**
	 * Render a full-width cover hero section.
	 *
	 * @param array<string, mixed> $data Section data.
	 * @return string Block markup.
	 */
	public static function render_cover( array $data ): string {
		$title      = isset( $data['title'] ) ? $data['title'] : ( isset( $data['heading'] ) ? $data['heading'] : '' );
		$subtitle   = isset( $data['subtitle'] ) ? $data['subtitle'] : ( isset( $data['subheading'] ) ? $data['subheading'] : '' );
		$image      = isset( $data['image'] ) ? $data['image'] : '';
		$image_id   = ! empty( $data['image_id'] ) ? (int) $data['image_id'] : 0;
		$content    = isset( $data['content'] ) ? $data['content'] : '';
		$min_height = isset( $data['minHeight'] ) ? (int) $data['minHeight'] : 600;
		$overlay    = isset( $data['overlay'] ) ? $data['overlay'] : '';

		if ( ! $image && $image_id ) {
			$image = (string) wp_get_attachment_url( $image_id );
		}

		$dim_ratio = 50;
		if ( isset( $data['overlay_opacity'] ) ) {
			$dim_ratio = (int) round( (float) $data['overlay_opacity'] * 100 );
		} elseif ( 'dark' === $overlay ) {
			$dim_ratio = 70;
		} elseif ( 'light' === $overlay ) {
			$dim_ratio = 30;
		}
		$dim_step = max( 0, min( 100, (int) ( round( $dim_ratio / 10 ) * 10 ) ) );

		$attributes = array(
			'url'                => $image,
			'dimRatio'           => $dim_ratio,
			'overlayColor'       => 'black',
			'isUserOverlayColor' => true,
			'minHeight'          => $min_height,
			'align'              => 'full',
		);
		if ( $image_id ) {
			$attributes['id'] = $image_id;
		}

		$markup  = '<!-- wp:cover ' . wp_json_encode( $attributes ) . " -->\n";
		$markup .= '<div class="wp-block-cover alignfull" style="min-height:' . $min_height . 'px">' . "\n";

		if ( $image ) {
			$img_cls = 'wp-block-cover__image-background' . ( $image_id ? ' wp-image-' . $image_id : '' );
			$markup .= '<img class="' . esc_attr( $img_cls ) . '" alt="" src="' . esc_url( $image ) . '" data-object-fit="cover"/>' . "\n";
		}

		$markup .= '<span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim-' . $dim_step . ' has-background-dim"></span>' . "\n";
		$markup .= '<div class="wp-block-cover__inner-container">' . "\n";

		if ( isset( $data['blocks'] ) && is_array( $data['blocks'] ) ) {
			// Caller supplied explicit inner blocks — render them with full block
			// comment delimiters so the editor can parse them back correctly.
			foreach ( $data['blocks'] as $block ) {
				$markup .= self::render_block( $block );
			}
		} else {
			// Legacy shorthand fields.
			if ( $title ) {
				$markup .= "<!-- wp:heading {\"textAlign\":\"center\",\"level\":1,\"textColor\":\"white\",\"fontSize\":\"extra-large\"} -->\n";
				$markup .= '<h1 class="wp-block-heading has-text-align-center has-white-color has-text-color has-extra-large-font-size">' . self::sanitize_content( $title ) . '</h1>' . "\n";
				$markup .= "<!-- /wp:heading -->\n";
			}

			if ( $subtitle ) {
				$markup .= "<!-- wp:paragraph {\"align\":\"center\",\"textColor\":\"white\"} -->\n";
				$markup .= '<p class="has-text-align-center has-white-color has-text-color">' . self::sanitize_content( $subtitle ) . '</p>' . "\n";
				$markup .= "<!-- /wp:paragraph -->\n";
			}

			if ( $content ) {
				$markup .= "<!-- wp:paragraph {\"align\":\"center\",\"textColor\":\"white\",\"fontSize\":\"large\"} -->\n";
				$markup .= '<p class="has-text-align-center has-white-color has-text-color has-large-font-size">' . self::sanitize_content( $content ) . '</p>' . "\n";
				$markup .= "<!-- /wp:paragraph -->\n";
			}

			if ( isset( $data['buttons'] ) && is_array( $data['buttons'] ) ) {
				$markup .= "<!-- wp:buttons {\"layout\":{\"type\":\"flex\",\"justifyContent\":\"center\"}} -->\n";
				$markup .= '<div class="wp-block-buttons">' . "\n";
				foreach ( $data['buttons'] as $btn ) {
					$btn_text  = isset( $btn['text'] ) ? $btn['text'] : 'Button';
					$btn_url   = isset( $btn['url'] ) ? $btn['url'] : '#';
					$btn_style = isset( $btn['style'] ) ? $btn['style'] : 'primary';
					if ( 'outline' === $btn_style ) {
						$markup .= "<!-- wp:button {\"className\":\"is-style-outline\"} -->\n";
						$markup .= '<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $btn_url ) . '">' . self::sanitize_content( $btn_text ) . '</a></div>' . "\n";
					} else {
						$markup .= "<!-- wp:button -->\n";
						$markup .= '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button has-vivid-cyan-blue-background-color has-background has-white-color has-text-color" href="' . esc_url( $btn_url ) . '">' . self::sanitize_content( $btn_text ) . '</a></div>' . "\n";
					}
					$markup .= "<!-- /wp:button -->\n";
				}
				$markup .= "</div>\n<!-- /wp:buttons -->\n";
			}
		}

		$markup .= "</div>\n</div>\n<!-- /wp:cover -->\n\n";

		return $markup;
	}

	/**
	 * Render a two-column content section.
	 *
	 * FIX: The original emitted a nested wp:columns inside the outer wp:columns
	 * wrapper for the optional title/subtitle header, without a wp:column parent,
	 * producing invalid markup. The header is now rendered as a standalone
	 * wp:group above the columns wrapper.
	 *
	 * @param array<string, mixed> $data Section data.
	 * @return string Block markup.
	 */
	public static function render_two_column( array $data ): string {
		$left    = isset( $data['left'] ) && is_array( $data['left'] ) ? $data['left'] : array();
		$right   = isset( $data['right'] ) && is_array( $data['right'] ) ? $data['right'] : array();
		$reverse = ! empty( $data['reverse'] );
		$title   = isset( $data['title'] ) ? $data['title'] : '';
		$subtitle = isset( $data['subtitle'] ) ? $data['subtitle'] : '';
		$bg_color = isset( $data['backgroundColor'] ) ? $data['backgroundColor'] : 'white';

		$left_blocks  = isset( $left['blocks'] ) && is_array( $left['blocks'] ) ? $left['blocks'] : array();
		$right_blocks = isset( $right['blocks'] ) && is_array( $right['blocks'] ) ? $right['blocks'] : array();

		if ( empty( $left_blocks ) && isset( $data['blocks'] ) && is_array( $data['blocks'] ) ) {
			$left_blocks  = array_slice( $data['blocks'], 0, 1 );
			$right_blocks = array_slice( $data['blocks'], 1 );
		}

		$markup = '';

		// FIX: Render title/subtitle as a standalone group ABOVE the columns
		// wrapper, not as a column inside it.
		if ( $title || $subtitle ) {
			$markup .= "<!-- wp:group {\"align\":\"full\"} -->\n";
			$markup .= '<div class="wp-block-group alignfull" style="padding:40px 40px 0">' . "\n";
			if ( $subtitle ) {
				$markup .= "<!-- wp:paragraph {\"align\":\"left\",\"fontSize\":\"small\"} -->\n";
				$markup .= '<p class="has-text-align-left has-small-font-size">' . self::sanitize_content( $subtitle ) . '</p>' . "\n";
				$markup .= "<!-- /wp:paragraph -->\n";
			}
			if ( $title ) {
				$markup .= "<!-- wp:heading {\"textAlign\":\"left\",\"level\":2} -->\n";
				$markup .= '<h2 class="wp-block-heading has-text-align-left">' . self::sanitize_content( $title ) . '</h2>' . "\n";
				$markup .= "<!-- /wp:heading -->\n";
			}
			$markup .= "</div>\n<!-- /wp:group -->\n\n";
		}

		$col_attrs = array(
			'align'           => 'full',
			'backgroundColor' => $bg_color,
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
		);

		$markup .= '<!-- wp:columns ' . wp_json_encode( $col_attrs ) . " -->\n";
		$markup .= '<div class="wp-block-columns alignfull has-' . esc_attr( $bg_color ) . '-background-color has-background" style="padding-top:80px;padding-right:40px;padding-bottom:80px;padding-left:40px">' . "\n";

		if ( $reverse ) {
			$markup .= self::render_text_column( $right, $right_blocks );
			$markup .= self::render_image_column( $left, $left_blocks );
		} else {
			$markup .= self::render_image_column( $left, $left_blocks );
			$markup .= self::render_text_column( $right, $right_blocks );
		}

		$markup .= "</div>\n<!-- /wp:columns -->\n\n";

		return $markup;
	}

	/**
	 * Render the image column of a two-column section.
	 *
	 * FIX: When a 'blocks' array is supplied the blocks are now rendered with
	 * full block comment delimiters via render_block() rather than the
	 * comment-stripping render_inner_block() path.
	 *
	 * @param array<string, mixed> $data   Column data.
	 * @param array                $blocks Optional pre-extracted blocks.
	 * @return string Block markup.
	 */
	private static function render_image_column( array $data, array $blocks = array() ): string {
		$markup  = "<!-- wp:column -->\n";
		$markup .= '<div class="wp-block-column">' . "\n";

		if ( ! empty( $blocks ) ) {
			foreach ( $blocks as $block ) {
				$markup .= self::render_block( $block );
			}
		} else {
			$src = isset( $data['src'] ) ? $data['src'] : ( isset( $data['image'] ) ? $data['image'] : '' );
			if ( ! $src && ! empty( $data['image_id'] ) ) {
				$src = (string) wp_get_attachment_url( (int) $data['image_id'] );
			}
			$alt = isset( $data['alt'] ) ? $data['alt'] : '';
			if ( $src ) {
				$markup .= "<!-- wp:image {\"align\":\"wide\",\"sizeSlug\":\"full\",\"linkDestination\":\"none\"} -->\n";
				$markup .= '<figure class="wp-block-image alignwide size-full"><img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '"/></figure>' . "\n";
				$markup .= "<!-- /wp:image -->\n";
			}
		}

		$markup .= "</div>\n<!-- /wp:column -->\n";

		return $markup;
	}

	/**
	 * Render the text column of a two-column section.
	 *
	 * FIX: When a 'blocks' array is supplied the blocks are rendered with full
	 * block comment delimiters rather than stripped inner HTML.
	 *
	 * @param array<string, mixed> $data   Column data.
	 * @param array                $blocks Optional pre-extracted blocks.
	 * @return string Block markup.
	 */
	private static function render_text_column( array $data, array $blocks = array() ): string {
		$markup  = "<!-- wp:column {\"verticalAlignment\":\"center\"} -->\n";
		$markup .= '<div class="wp-block-column is-vertically-aligned-center">' . "\n";

		if ( ! empty( $blocks ) ) {
			foreach ( $blocks as $block ) {
				$markup .= self::render_block( $block );
			}
		} else {
			$heading    = isset( $data['heading'] ) ? $data['heading'] : ( isset( $data['title'] ) ? $data['title'] : '' );
			$text       = isset( $data['text'] ) ? $data['text'] : ( isset( $data['content'] ) ? $data['content'] : '' );
			$paragraphs = isset( $data['paragraphs'] ) ? $data['paragraphs'] : array();

			if ( $heading ) {
				$markup .= "<!-- wp:heading -->\n";
				$markup .= '<h2 class="wp-block-heading">' . self::sanitize_content( $heading ) . '</h2>' . "\n";
				$markup .= "<!-- /wp:heading -->\n";
			}
			if ( $text ) {
				$markup .= "<!-- wp:paragraph -->\n";
				$markup .= '<p>' . self::sanitize_content( $text ) . '</p>' . "\n";
				$markup .= "<!-- /wp:paragraph -->\n";
			}
			foreach ( $paragraphs as $para ) {
				$markup .= "<!-- wp:paragraph -->\n";
				$markup .= '<p>' . self::sanitize_content( $para ) . '</p>' . "\n";
				$markup .= "<!-- /wp:paragraph -->\n";
			}
		}

		$markup .= "</div>\n<!-- /wp:column -->\n";

		return $markup;
	}

	/**
	 * Render a three-column feature section.
	 *
	 * FIX: The original placed the optional title/subtitle header as a bare
	 * column inside the same wp:columns wrapper as the three feature columns,
	 * yielding up to 4 columns instead of 3. The header is now rendered as a
	 * standalone wp:group above the columns wrapper, mirroring the two-column
	 * fix.
	 *
	 * @param array<string, mixed> $data Section data.
	 * @return string Block markup.
	 */
	public static function render_three_column( array $data ): string {
		$columns  = isset( $data['columns'] ) && is_array( $data['columns'] ) ? $data['columns'] : array();
		if ( empty( $columns ) && isset( $data['items'] ) && is_array( $data['items'] ) ) {
			$columns = $data['items'];
		}
		$title    = isset( $data['title'] ) ? $data['title'] : '';
		$subtitle = isset( $data['subtitle'] ) ? $data['subtitle'] : '';
		$bg_color = isset( $data['backgroundColor'] ) ? $data['backgroundColor'] : 'white';

		$markup = '';

		// FIX: Title/subtitle rendered as a standalone group ABOVE the columns.
		if ( $title || $subtitle ) {
			$markup .= "<!-- wp:group {\"align\":\"full\"} -->\n";
			$markup .= '<div class="wp-block-group alignfull" style="padding:80px 40px 0">' . "\n";
			if ( $subtitle ) {
				$markup .= "<!-- wp:paragraph {\"align\":\"center\",\"fontSize\":\"small\"} -->\n";
				$markup .= '<p class="has-text-align-center has-small-font-size">' . self::sanitize_content( $subtitle ) . '</p>' . "\n";
				$markup .= "<!-- /wp:paragraph -->\n";
			}
			if ( $title ) {
				$markup .= "<!-- wp:heading {\"textAlign\":\"center\",\"level\":2} -->\n";
				$markup .= '<h2 class="wp-block-heading has-text-align-center">' . self::sanitize_content( $title ) . '</h2>' . "\n";
				$markup .= "<!-- /wp:heading -->\n";
			}
			$markup .= "</div>\n<!-- /wp:group -->\n\n";
		}

		$col_attrs = array(
			'align'           => 'full',
			'backgroundColor' => $bg_color,
			'style'           => array(
				'spacing' => array(
					'padding' => array(
						'top'    => '40px',
						'bottom' => '80px',
						'left'   => '40px',
						'right'  => '40px',
					),
				),
			),
		);

		$markup .= '<!-- wp:columns ' . wp_json_encode( $col_attrs ) . " -->\n";
		$markup .= '<div class="wp-block-columns alignfull has-' . esc_attr( $bg_color ) . '-background-color has-background" style="padding-top:40px;padding-right:40px;padding-bottom:80px;padding-left:40px">' . "\n";

		$num_columns = min( 3, count( $columns ) );
		for ( $i = 0; $i < $num_columns; $i++ ) {
			$markup .= self::render_feature_column( $columns[ $i ] );
		}

		$markup .= "</div>\n<!-- /wp:columns -->\n\n";

		return $markup;
	}

	/**
	 * Render a feature column inside a three-column section.
	 *
	 * FIX: When a 'blocks' array is present, blocks are rendered via render_block
	 * (with delimiters) rather than render_inner_block (stripped HTML).
	 *
	 * @param array<string, mixed> $data Column data.
	 * @return string Block markup.
	 */
	private static function render_feature_column( array $data ): string {
		$markup  = "<!-- wp:column -->\n";
		$markup .= '<div class="wp-block-column">' . "\n";

		if ( isset( $data['blocks'] ) && is_array( $data['blocks'] ) ) {
			foreach ( $data['blocks'] as $block ) {
				$markup .= self::render_block( $block );
			}
		} else {
			$heading = isset( $data['heading'] ) ? $data['heading'] : ( isset( $data['title'] ) ? $data['title'] : '' );
			$text    = isset( $data['text'] ) ? $data['text'] : ( isset( $data['content'] ) ? $data['content'] : '' );
			$icon    = isset( $data['icon'] ) ? $data['icon'] : '';

			if ( $icon ) {
				$markup .= "<!-- wp:paragraph {\"align\":\"center\",\"fontSize\":\"extra-large\"} -->\n";
				$markup .= '<p class="has-text-align-center has-extra-large-font-size">' . esc_html( $icon ) . '</p>' . "\n";
				$markup .= "<!-- /wp:paragraph -->\n";
			}
			if ( $heading ) {
				$markup .= "<!-- wp:heading {\"textAlign\":\"center\",\"level\":3} -->\n";
				$markup .= '<h3 class="wp-block-heading has-text-align-center">' . self::sanitize_content( $heading ) . '</h3>' . "\n";
				$markup .= "<!-- /wp:heading -->\n";
			}
			if ( $text ) {
				$markup .= "<!-- wp:paragraph {\"align\":\"center\"} -->\n";
				$markup .= '<p class="has-text-align-center">' . self::sanitize_content( $text ) . '</p>' . "\n";
				$markup .= "<!-- /wp:paragraph -->\n";
			}
		}

		$markup .= "</div>\n<!-- /wp:column -->\n";

		return $markup;
	}

	/**
	 * Render a footer section.
	 *
	 * Colours are now driven by $data keys ('background_color', 'text_color',
	 * 'heading_color', 'muted_color') so the agent can override them per page.
	 * Sensible defaults are retained when keys are absent.
	 *
	 * @param array<string, mixed> $data Section data.
	 * @return string Block markup.
	 */
	public static function render_footer( array $data ): string {
		$columns       = isset( $data['columns'] ) && is_array( $data['columns'] ) ? $data['columns'] : array();
		$bg_color      = isset( $data['background_color'] ) ? $data['background_color'] : '#1a1a1a';
		$text_color    = isset( $data['text_color'] ) ? $data['text_color'] : '#cccccc';
		$heading_color = isset( $data['heading_color'] ) ? $data['heading_color'] : '#ffffff';
		$muted_color   = isset( $data['muted_color'] ) ? $data['muted_color'] : '#888888';
		$border_color  = isset( $data['border_color'] ) ? $data['border_color'] : '#333333';
		$copyright     = isset( $data['copyright'] ) ? $data['copyright'] : '© ' . gmdate( 'Y' ) . ' Company Name. All rights reserved.';

		$markup  = "<!-- wp:group {\"tagName\":\"footer\",\"align\":\"full\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"60px\",\"bottom\":\"40px\",\"left\":\"40px\",\"right\":\"40px\"}},\"color\":{\"background\":\"" . esc_js( $bg_color ) . "\",\"text\":\"" . esc_js( $text_color ) . "\"}}} -->\n";
		$markup .= '<footer class="wp-block-group alignfull has-text-color has-background" style="color:' . esc_attr( $text_color ) . ';background-color:' . esc_attr( $bg_color ) . ';padding-top:60px;padding-right:40px;padding-bottom:40px;padding-left:40px">' . "\n";

		if ( ! empty( $columns ) ) {
			$col_attrs = array(
				'align' => 'full',
				'style' => array( 'spacing' => array( 'columnGap' => '40px', 'rowGap' => '40px' ) ),
			);
			$markup .= '<!-- wp:columns ' . wp_json_encode( $col_attrs ) . " -->\n";
			$markup .= '<div class="wp-block-columns alignfull">' . "\n";
			foreach ( $columns as $col ) {
				$markup .= self::render_footer_column( $col, $heading_color, $text_color );
			}
			$markup .= "</div>\n<!-- /wp:columns -->\n";
		}

		// Separator.
		$sep_attrs = array( 'align' => 'full', 'style' => array( 'color' => array( 'background' => $border_color ) ) );
		$markup   .= '<!-- wp:separator ' . wp_json_encode( $sep_attrs ) . " -->\n";
		$markup   .= '<hr class="wp-block-separator alignfull has-text-color has-alpha-channel-opacity has-background" style="background-color:' . esc_attr( $border_color ) . ';color:' . esc_attr( $border_color ) . '"/>' . "\n";
		$markup   .= "<!-- /wp:separator -->\n";

		// Copyright line.
		$markup .= "<!-- wp:paragraph {\"align\":\"center\",\"style\":{\"color\":{\"text\":\"" . esc_js( $muted_color ) . "\"}}} -->\n";
		$markup .= '<p class="has-text-align-center has-text-color" style="color:' . esc_attr( $muted_color ) . '">' . self::sanitize_content( $copyright ) . '</p>' . "\n";
		$markup .= "<!-- /wp:paragraph -->\n";

		$markup .= "</footer>\n<!-- /wp:group -->\n\n";

		return $markup;
	}

	/**
	 * Render a single footer column.
	 *
	 * @param array<string, mixed> $data          Column data.
	 * @param string               $heading_color Heading colour hex.
	 * @param string               $text_color    Body text colour hex.
	 * @return string Block markup.
	 */
	private static function render_footer_column( array $data, string $heading_color = '#ffffff', string $text_color = '#cccccc' ): string {
		$type    = isset( $data['type'] ) ? $data['type'] : 'text';
		$markup  = "<!-- wp:column -->\n";
		$markup .= '<div class="wp-block-column">' . "\n";

		switch ( $type ) {
			case 'about':
				$text = isset( $data['text'] ) ? $data['text'] : '';
				$markup .= self::footer_heading( isset( $data['title'] ) ? $data['title'] : 'About', $heading_color );
				if ( $text ) {
					$markup .= self::footer_paragraph( $text, $text_color );
				}
				break;

			case 'links':
				$items     = isset( $data['items'] ) ? $data['items'] : array();
				$links     = isset( $data['links'] ) ? $data['links'] : array();
				$col_title = isset( $data['title'] ) ? $data['title'] : 'Quick Links';
				$markup   .= self::footer_heading( $col_title, $heading_color );
				$markup   .= "<!-- wp:list -->\n<ul>\n";
				foreach ( $items as $i => $item ) {
					$url     = isset( $links[ $i ] ) ? $links[ $i ] : '#';
					$markup .= "<!-- wp:list-item -->\n";
					$markup .= '<li><a href="' . esc_url( $url ) . '" style="color:' . esc_attr( $text_color ) . '">' . self::sanitize_content( $item ) . '</a></li>' . "\n";
					$markup .= "<!-- /wp:list-item -->\n";
				}
				$markup .= "</ul>\n<!-- /wp:list -->\n";
				break;

			case 'social':
				$networks  = isset( $data['networks'] ) ? $data['networks'] : array();
				$urls      = isset( $data['urls'] ) ? $data['urls'] : array();
				$col_title = isset( $data['title'] ) ? $data['title'] : 'Connect';
				$markup   .= self::footer_heading( $col_title, $heading_color );
				$markup   .= "<!-- wp:list -->\n<ul>\n";
				foreach ( $networks as $i => $network ) {
					$url     = isset( $urls[ $i ] ) ? $urls[ $i ] : '#';
					$markup .= "<!-- wp:list-item -->\n";
					$markup .= '<li><a href="' . esc_url( $url ) . '" style="color:' . esc_attr( $text_color ) . '">' . esc_html( ucfirst( $network ) ) . '</a></li>' . "\n";
					$markup .= "<!-- /wp:list-item -->\n";
				}
				$markup .= "</ul>\n<!-- /wp:list -->\n";
				break;

			case 'contact':
				$email   = isset( $data['email'] ) ? $data['email'] : '';
				$phone   = isset( $data['phone'] ) ? $data['phone'] : '';
				$address = isset( $data['address'] ) ? $data['address'] : '';
				$markup .= self::footer_heading( isset( $data['title'] ) ? $data['title'] : 'Contact', $heading_color );
				$lines   = array();
				if ( $email ) {
					$lines[] = '<a href="mailto:' . esc_attr( $email ) . '" style="color:' . esc_attr( $text_color ) . '">' . esc_html( $email ) . '</a>';
				}
				if ( $phone ) {
					$lines[] = esc_html( $phone );
				}
				if ( $address ) {
					$lines[] = esc_html( $address );
				}
				if ( $lines ) {
					$markup .= self::footer_paragraph( implode( '<br/>', $lines ), $text_color, true );
				}
				break;

			default:
				// Generic text column.
				$col_heading = isset( $data['heading'] ) ? $data['heading'] : ( isset( $data['title'] ) ? $data['title'] : '' );
				$col_text    = isset( $data['content'] ) ? $data['content'] : ( isset( $data['text'] ) ? $data['text'] : '' );
				if ( $col_heading ) {
					$markup .= self::footer_heading( $col_heading, $heading_color );
				}
				if ( $col_text ) {
					$markup .= self::footer_paragraph( $col_text, $text_color );
				}
		}

		$markup .= "</div>\n<!-- /wp:column -->\n";

		return $markup;
	}

	/**
	 * Emit a footer column heading block.
	 *
	 * @param string $text  Heading text.
	 * @param string $color Hex colour.
	 * @return string Block markup.
	 */
	private static function footer_heading( string $text, string $color ): string {
		$attrs = array( 'level' => 3, 'style' => array( 'color' => array( 'text' => $color ) ) );
		return '<!-- wp:heading ' . wp_json_encode( $attrs ) . " -->\n"
			. '<h3 class="wp-block-heading has-text-color" style="color:' . esc_attr( $color ) . '">' . esc_html( $text ) . '</h3>' . "\n"
			. "<!-- /wp:heading -->\n";
	}

	/**
	 * Emit a footer paragraph block.
	 *
	 * @param string $text    Content (may contain safe inline HTML when $raw is true).
	 * @param string $color   Hex colour.
	 * @param bool   $raw     When true, $text is already sanitized HTML.
	 * @return string Block markup.
	 */
	private static function footer_paragraph( string $text, string $color, bool $raw = false ): string {
		$attrs   = array( 'style' => array( 'color' => array( 'text' => $color ) ) );
		$content = $raw ? $text : self::sanitize_content( $text );
		return '<!-- wp:paragraph ' . wp_json_encode( $attrs ) . " -->\n"
			. '<p class="has-text-color" style="color:' . esc_attr( $color ) . '">' . $content . '</p>' . "\n"
			. "<!-- /wp:paragraph -->\n";
	}
}