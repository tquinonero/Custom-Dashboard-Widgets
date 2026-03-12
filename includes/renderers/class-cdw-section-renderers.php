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
		$title      = isset( $data['title'] ) ? $data['title'] : '';
		$subtitle   = isset( $data['subtitle'] ) ? $data['subtitle'] : '';
		$image      = isset( $data['image'] ) ? $data['image'] : '';
		$min_height = isset( $data['minHeight'] ) ? (int) $data['minHeight'] : 600;
		$content    = isset( $data['content'] ) ? $data['content'] : '';
		$overlay    = isset( $data['overlay'] ) ? $data['overlay'] : '';

		$attributes = array(
			'url'       => $image,
			'alt'       => $title,
			'align'     => 'full',
			'minHeight' => $min_height,
		);

		$dim_class = 'has-background-dim-40';
		if ( 'dark' === $overlay ) {
			$dim_class = 'has-background-dim-80';
		}

		$markup  = '<!-- wp:cover ' . wp_json_encode( $attributes ) . " -->\n";
		$markup .= '<div class="wp-block-cover alignfull" style="min-height:' . $min_height . 'px">' . "\n";
		$markup .= '<span aria-hidden="true" class="wp-block-cover__background ' . $dim_class . ' has-background-dim"></span>' . "\n";

		if ( $image ) {
			$markup .= '<img class="wp-block-cover__image-background" alt="' . esc_attr( $title ) . '" src="' . esc_url( $image ) . '" data-object-fit="cover"/>' . "\n";
		}

		$markup .= '<div class="wp-block-cover__inner-container">' . "\n";

		if ( $title ) {
			$markup .= "<!-- wp:heading {\"textAlign\":\"center\",\"level\":1,\"textColor\":\"white\",\"fontSize\":\"extra-large\"} -->\n";
			$markup .= '<h1 class="has-text-align-center has-white-color has-text-color has-extra-large-font-size">' . esc_html( $title ) . '</h1>' . "\n";
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

		$markup .= "</div>\n";
		$markup .= "</div>\n";
		$markup .= "<!-- /wp:cover -->\n\n";

		return $markup;
	}

	/**
	 * Render a two-column section.
	 *
	 * @param array<string, mixed> $data Section data.
	 * @return string Block markup.
	 */
	public static function render_two_column( array $data ): string {
		$left    = isset( $data['left'] ) ? $data['left'] : array();
		$right   = isset( $data['right'] ) ? $data['right'] : array();
		$reverse = isset( $data['reverse'] ) && $data['reverse'];
		$title    = isset( $data['title'] ) ? $data['title'] : '';
		$subtitle = isset( $data['subtitle'] ) ? $data['subtitle'] : '';

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
		$markup .= '<div class="wp-block-columns alignfull has-white-background-color has-background" style="padding-top:80px;padding-bottom:80px;padding-left:40px;padding-right:40px">' . "\n";

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
				$markup .= '<h2 class="has-text-align-left">' . esc_html( $title ) . '</h2>' . "\n";
				$markup .= "<!-- /wp:heading -->\n";
			}
			$markup .= "</div>\n";
			$markup .= "<!-- /wp:column -->\n";
			$markup .= "</div>\n";
			$markup .= "<!-- /wp:columns -->\n";
		}

		if ( $reverse ) {
			$markup .= self::render_text_column( $right );
			$markup .= self::render_image_column( $left );
		} else {
			$markup .= self::render_image_column( $left );
			$markup .= self::render_text_column( $right );
		}

		$markup .= "</div>\n";
		$markup .= "<!-- /wp:columns -->\n\n";

		return $markup;
	}

	/**
	 * Render an image column.
	 *
	 * @param array{src?: string, image?: string, alt?: string} $data Column data.
	 * @return string Block markup.
	 */
	private static function render_image_column( array $data ): string {
		$src = isset( $data['src'] ) ? $data['src'] : ( isset( $data['image'] ) ? $data['image'] : '' );
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
	 * @return string Block markup.
	 */
	private static function render_text_column( array $data ): string {
		$heading    = isset( $data['heading'] ) ? $data['heading'] : ( isset( $data['title'] ) ? $data['title'] : '' );
		$text       = isset( $data['text'] ) ? $data['text'] : ( isset( $data['content'] ) ? $data['content'] : '' );
		$paragraphs = isset( $data['paragraphs'] ) ? $data['paragraphs'] : array();

		$markup  = "<!-- wp:column {\"verticalAlignment\":\"center\"} -->\n";
		$markup .= '<div class="wp-block-column is-vertically-aligned-center">' . "\n";

		if ( $heading ) {
			$markup .= "<!-- wp:heading {\"level\":2} -->\n";
			$markup .= '<h2>' . esc_html( $heading ) . '</h2>' . "\n";
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
		$columns = isset( $data['columns'] ) ? $data['columns'] : array();
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
		$markup .= '<div class="wp-block-columns alignfull has-white-background-color has-background" style="padding-top:80px;padding-bottom:40px;padding-left:40px;padding-right:40px">' . "\n";

		if ( $title || $subtitle ) {
			$markup .= "<!-- wp:column -->\n";
			$markup .= '<div class="wp-block-column">' . "\n";
			$markup .= "<!-- wp:paragraph {\"align\":\"center\",\"textColor\":\"black\",\"fontSize\":\"small\"} -->\n";
			$markup .= '<p class="has-text-align-center has-black-color has-text-color has-small-font-size">' . esc_html( $subtitle ) . '</p>' . "\n";
			$markup .= "<!-- /wp:paragraph -->\n";
			$markup .= "<!-- wp:heading {\"textAlign\":\"center\",\"level\":2} -->\n";
			$markup .= '<h2 class="has-text-align-center">' . esc_html( $title ) . '</h2>' . "\n";
			$markup .= "<!-- /wp:heading -->\n";
			$markup .= "</div>\n";
			$markup .= "<!-- /wp:column -->\n";
		}

		for ( $i = 0; $i < 3; $i++ ) {
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

		if ( $icon ) {
			$markup .= "<!-- wp:paragraph {\"align\":\"center\",\"fontSize\":\"extra-large\"} -->\n";
			$markup .= '<p class="has-text-align-center has-extra-large-font-size">' . esc_html( $icon ) . '</p>' . "\n";
			$markup .= "<!-- /wp:paragraph -->\n";
		}

		if ( $heading ) {
			$markup .= "<!-- wp:heading {\"textAlign\":\"center\",\"level\":3} -->\n";
			$markup .= '<h3 class="has-text-align-center">' . esc_html( $heading ) . '</h3>' . "\n";
			$markup .= "<!-- /wp:heading -->\n";
		}

		if ( $text ) {
			$markup .= "<!-- wp:paragraph {\"align\":\"center\"} -->\n";
			$markup .= '<p class="has-text-align-center">' . esc_html( $text ) . '</p>' . "\n";
			$markup .= "<!-- /wp:paragraph -->\n";
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
		$markup .= '<footer class="wp-block-group alignfull has-text-color has-background" style="background-color:#FDF6E3;color:#5D4E37;padding-top:60px;padding-bottom:40px;padding-left:40px;padding-right:40px;text-align:center">' . "\n";

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
		$markup .= '<div class="wp-block-columns alignfull has-background-dim-0 has-background-dim" style="column-gap:40px;row-gap:40px;padding-left:40px;padding-right:40px;text-align:center">' . "\n";

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

		$markup .= "<!-- wp:separator {\"align\":\"full\",\"color\":\"#E8DCC8\"} -->\n";
		$markup .= '<hr class="wp-block-separator has-text-color has-background has-background-color has-text-color has-background"/>' . "\n";
		$markup .= "<!-- /wp:separator -->\n";

		$markup .= "<!-- wp:paragraph {\"align\":\"center\",\"textColor\":\"#8B7355\"} -->\n";
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

		if ( isset( $data['title'] ) && isset( $data['content'] ) && empty( $type ) ) {
			$markup .= "<!-- wp:heading {\"textAlign\":\"center\",\"level\":3,\"textColor\":\"#8B7355\"} -->\n";
			$markup .= '<h3 class="has-text-align-center has-text-color" style="color:#8B7355">' . esc_html( $data['title'] ) . '</h3>' . "\n";
			$markup .= "<!-- /wp:heading -->\n";
			$markup .= "<!-- wp:paragraph {\"align\":\"center\",\"textColor\":\"#5D4E37\"} -->\n";
			$markup .= '<p class="has-text-align-center has-text-color" style="color:#5D4E37">' . nl2br( esc_html( $data['content'] ) ) . '</p>' . "\n";
			$markup .= "<!-- /wp:paragraph -->\n";
		} else {
			switch ( $type ) {
				case 'about':
					$text    = isset( $data['text'] ) ? $data['text'] : 'Company description.';
					$markup .= "<!-- wp:heading {\"textAlign\":\"center\",\"level\":3,\"textColor\":\"#8B7355\"} -->\n";
					$markup .= '<h3 class="has-text-align-center has-text-color" style="color:#8B7355">About</h3>' . "\n";
					$markup .= "<!-- /wp:heading -->\n";
					$markup .= "<!-- wp:paragraph {\"align\":\"center\",\"textColor\":\"#5D4E37\"} -->\n";
					$markup .= '<p class="has-text-align-center has-text-color" style="color:#5D4E37">' . esc_html( $text ) . '</p>' . "\n";
					$markup .= "<!-- /wp:paragraph -->\n";
					break;

				case 'links':
					$items   = isset( $data['items'] ) ? $data['items'] : array();
					$links   = isset( $data['links'] ) ? $data['links'] : array();
					$col_title = isset( $data['title'] ) ? $data['title'] : 'Quick Links';
					$markup .= "<!-- wp:heading {\"textAlign\":\"center\",\"level\":3,\"textColor\":\"#8B7355\"} -->\n";
					$markup .= '<h3 class="has-text-align-center has-text-color" style="color:#8B7355">' . esc_html( $col_title ) . '</h3>' . "\n";
					$markup .= "<!-- /wp:heading -->\n";
					$markup .= "<!-- wp:list {\"align\":\"center\",\"textColor\":\"#5D4E37\"} -->\n";
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
					$markup  .= "<!-- wp:heading {\"textAlign\":\"center\",\"level\":3,\"textColor\":\"#8B7355\"} -->\n";
					$markup  .= '<h3 class="has-text-align-center has-text-color" style="color:#8B7355">' . esc_html( $col_title ) . '</h3>' . "\n";
					$markup  .= "<!-- /wp:heading -->\n";
					$markup  .= "<!-- wp:list {\"align\":\"center\",\"textColor\":\"#5D4E37\"} -->\n";
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
					$markup      .= "<!-- wp:heading {\"textAlign\":\"center\",\"level\":3,\"textColor\":\"#8B7355\"} -->\n";
					$markup      .= '<h3 class="has-text-align-center has-text-color" style="color:#8B7355">Contact</h3>' . "\n";
					$markup      .= "<!-- /wp:heading -->\n";
					$markup      .= "<!-- wp:paragraph {\"align\":\"center\",\"textColor\":\"#5D4E37\"} -->\n";
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
					$text = isset( $data['text'] ) ? $data['text'] : '';
					if ( $text ) {
						$markup .= "<!-- wp:paragraph {\"align\":\"center\",\"textColor\":\"#5D4E37\"} -->\n";
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
		$block_name = isset( $data['block'] ) ? $data['block'] : '';

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

		if ( 'core/buttons' === $block_name ) {
			return self::render_buttons_block( $data );
		}

		if ( 'core/columns' === $block_name ) {
			return self::render_columns_block( $data );
		}

		if ( 'core/button' === $block_name ) {
			return self::render_single_button( $data );
		}

		if ( 'core/list' === $block_name ) {
			return self::render_list_block( $data );
		}

		$attrs = self::map_attributes( $data );
		unset( $attrs['innerHTML'] );

		$content = isset( $data['content'] ) ? $data['content'] : '';

		if ( isset( $data['innerContent'] ) && is_array( $data['innerContent'] ) ) {
			$content = implode( "\n", $data['innerContent'] );
		}

		$markup  = '<!-- wp:' . $block_name;
		if ( ! empty( $attrs ) ) {
			$markup .= ' ' . wp_json_encode( $attrs );
		}
		$markup .= " -->\n";

		$markup .= self::render_block_inner( $block_name, $data, $content );

		$markup .= "<!-- /wp:" . $block_name . " -->\n";

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
				$level = isset( $data['level'] ) ? (int) $data['level'] : 2;
				$tag   = 'h' . $level;
				return '<' . $tag . self::get_text_class( $data ) . '>' . esc_html( $content ) . '</' . $tag . '>';

			case 'core/image':
				$url = isset( $data['url'] ) ? $data['url'] : '';
				$alt = isset( $data['alt'] ) ? $data['alt'] : '';
				if ( isset( $data['link'] ) && $data['link'] ) {
					return '<figure class="wp-block-image"><a href="' . esc_url( $data['link'] ) . '"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"/></a></figure>';
				}
				return '<figure class="wp-block-image size-full"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"/></figure>';

			case 'core/cover':
				return self::render_cover_inner( $data );

			case 'core/group':
				return '<div class="wp-block-group__inner-container">' . $content . '</div>';

			case 'core/spacer':
				return '';

			case 'core/separator':
				$style = '';
				if ( isset( $data['color'] ) ) {
					$style = ' style="background-color:' . esc_attr( $data['color'] ) . '"';
				}
				return '<hr class="wp-block-separator has-text-color has-background has-' . esc_attr( isset( $data['color'] ) ? $data['color'] : 'gray' ) . '-background-color"' . $style . '/>';

			case 'core/quote':
				$cite = isset( $data['cite'] ) ? $data['cite'] : '';
				return '<blockquote class="wp-block-quote"><p>' . esc_html( $content ) . '</p>' . ( $cite ? '<cite>' . esc_html( $cite ) . '</cite>' : '' ) . '</blockquote>';

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
	private static function render_buttons_block( array $data ): string {
		$attrs = self::map_attributes( $data );
		unset( $attrs['innerHTML'] );

		$markup  = '<!-- wp:buttons';
		if ( ! empty( $attrs ) ) {
			$markup .= ' ' . wp_json_encode( $attrs );
		}
		$markup .= " -->\n";
		$markup .= '<div class="wp-block-buttons">' . "\n";

		$buttons = isset( $data['buttons'] ) ? $data['buttons'] : array();
		if ( empty( $buttons ) && isset( $data['children'] ) ) {
			$buttons = $data['children'];
		}

		foreach ( $buttons as $btn ) {
			$btn['block'] = 'core/button';
			$markup      .= self::render_block( $btn );
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

		$url    = isset( $data['url'] ) ? $data['url'] : ( isset( $data['link'] ) ? $data['link'] : '' );
		$text   = isset( $data['text'] ) ? $data['text'] : 'Button';
		$target = isset( $data['newTab'] ) && $data['newTab'] ? ' target="_blank" rel="noopener"' : '';

		$classes = array( 'wp-block-button__link' );
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

		$class_str = implode( ' ', $classes );

		$markup  = '<!-- wp:button --><div class="wp-block-button">' . "\n";
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
		$markup .= '<div class="wp-block-columns">' . "\n";

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
		$markup .= '<div class="wp-block-column">' . "\n";

		if ( isset( $data['children'] ) && is_array( $data['children'] ) ) {
			foreach ( $data['children'] as $child ) {
				$markup .= self::render_block( $child );
			}
		}

		$markup .= "</div>\n";
		$markup .= "<!-- /wp:column -->\n";

		return $markup;
	}
}
