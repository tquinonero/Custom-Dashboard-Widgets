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
	 * @param array $data Section data.
	 * @return string Block markup.
	 */
	public static function render_cover( $data ) {
		$title      = isset( $data['title'] ) ? $data['title'] : '';
		$subtitle   = isset( $data['subtitle'] ) ? $data['subtitle'] : '';
		$image      = isset( $data['image'] ) ? $data['image'] : '';
		$min_height = isset( $data['minHeight'] ) ? (int) $data['minHeight'] : 600;

		$attributes = array(
			'url'       => $image,
			'alt'       => $title,
			'align'     => 'full',
			'minHeight' => $min_height,
		);

		$markup  = '<!-- wp:cover ' . wp_json_encode( $attributes ) . " -->\n";
		$markup .= '<div class="wp-block-cover alignfull" style="min-height:' . $min_height . 'px">' . "\n";
		$markup .= '<span aria-hidden="true" class="wp-block-cover__background has-background-dim-40 has-background-dim"></span>' . "\n";

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

		$markup .= "</div>\n";
		$markup .= "</div>\n";
		$markup .= "<!-- /wp:cover -->\n\n";

		return $markup;
	}

	/**
	 * Render a two-column section.
	 *
	 * @param array $data Section data.
	 * @return string Block markup.
	 */
	public static function render_two_column( $data ) {
		$left    = isset( $data['left'] ) ? $data['left'] : array();
		$right   = isset( $data['right'] ) ? $data['right'] : array();
		$reverse = isset( $data['reverse'] ) && $data['reverse'];

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
	 * @param array $data Column data.
	 * @return string Block markup.
	 */
	private static function render_image_column( $data ) {
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
	 * @param array $data Column data.
	 * @return string Block markup.
	 */
	private static function render_text_column( $data ) {
		$heading    = isset( $data['heading'] ) ? $data['heading'] : '';
		$text       = isset( $data['text'] ) ? $data['text'] : '';
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
	 * @param array $data Section data.
	 * @return string Block markup.
	 */
	public static function render_three_column( $data ) {
		$columns = isset( $data['columns'] ) ? $data['columns'] : array();

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
	 * @param array $data Column data.
	 * @return string Block markup.
	 */
	private static function render_feature_column( $data ) {
		$heading = isset( $data['heading'] ) ? $data['heading'] : '';
		$text    = isset( $data['text'] ) ? $data['text'] : '';

		$markup  = "<!-- wp:column -->\n";
		$markup .= '<div class="wp-block-column">' . "\n";

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
	 * @param array $data Section data.
	 * @return string Block markup.
	 */
	public static function render_footer( $data ) {
		$columns = isset( $data['columns'] ) ? $data['columns'] : array();

		$markup  = "<!-- wp:group {\"tagName\":\"footer\",\"align\":\"full\",\"style\":{\"spacing\":{\"padding\":{\"top\":\"60px\",\"bottom\":\"40px\"}},\"color\":{\"background\":\"#FDF6E3\",\"text\":\"#5D4E37\"}}} -->\n";
		$markup .= '<footer class="wp-block-group alignfull has-text-color has-background" style="background-color:#FDF6E3;color:#5D4E37;padding-top:60px;padding-bottom:40px">' . "\n";

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
		$markup .= '<div class="wp-block-columns alignfull has-background-dim-0 has-background-dim" style="column-gap:40px;row-gap:40px;padding-left:40px;padding-right:40px">' . "\n";

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
	 * @param array $data Column data.
	 * @return string Block markup.
	 */
	private static function render_footer_column( $data ) {
		$type = isset( $data['type'] ) ? $data['type'] : 'text';

		$markup  = "<!-- wp:column -->\n";
		$markup .= '<div class="wp-block-column">' . "\n";

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
				$markup .= "<!-- wp:heading {\"textAlign\":\"center\",\"level\":3,\"textColor\":\"#8B7355\"} -->\n";
				$markup .= '<h3 class="has-text-align-center has-text-color" style="color:#8B7355">Quick Links</h3>' . "\n";
				$markup .= "<!-- /wp:heading -->\n";
				$markup .= "<!-- wp:list {\"align\":\"center\",\"textColor\":\"#5D4E37\"} -->\n";
				$markup .= '<ul class="has-text-align-center has-text-color" style="color:#5D4E37">';
				foreach ( $items as $item ) {
					$markup .= '<li><a href="#">' . esc_html( $item ) . '</a></li>';
				}
				$markup .= "</ul>\n";
				$markup .= "<!-- /wp:list -->\n";
				break;

			case 'social':
				$networks = isset( $data['networks'] ) ? $data['networks'] : array();
				$markup  .= "<!-- wp:heading {\"textAlign\":\"center\",\"level\":3,\"textColor\":\"#8B7355\"} -->\n";
				$markup  .= '<h3 class="has-text-align-center has-text-color" style="color:#8B7355">Connect</h3>' . "\n";
				$markup  .= "<!-- /wp:heading -->\n";
				$markup  .= "<!-- wp:list {\"align\":\"center\",\"textColor\":\"#5D4E37\"} -->\n";
				$markup  .= '<ul class="has-text-align-center has-text-color" style="color:#5D4E37">';
				foreach ( $networks as $network ) {
					$markup .= '<li><a href="#">' . esc_html( ucfirst( $network ) ) . '</a></li>';
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
					$contact_text .= esc_html( $email );
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
					$markup .= '<p class="has-text-align-center has-text-color" style="color:#5D4E37">' . esc_html( $text ) . '</p>' . "\n";
					$markup .= "<!-- /wp:paragraph -->\n";
				}
		}

		$markup .= "</div>\n";
		$markup .= "<!-- /wp:column -->\n";

		return $markup;
	}

	/**
	 * Render all sections and return complete page content.
	 *
	 * @param array $sections Array of section data.
	 * @return string Complete block markup.
	 */
	public static function render_sections( $sections ) {
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
			}
		}

		return $markup;
	}
}
