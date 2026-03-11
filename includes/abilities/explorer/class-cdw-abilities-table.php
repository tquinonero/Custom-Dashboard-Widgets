<?php
/**
 * Abilities list table for CDW Abilities Explorer.
 *
 * Extends WP_List_Table to display registered CDW abilities.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Abilities list table class.
 *
 * @package CDW
 */
class CDW_Abilities_Table extends WP_List_Table {

	/**
	 * Abilities data.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $abilities = array();

	/**
	 * Constructor.
	 *
	 * @param array<int, array<string, mixed>> $abilities Abilities data.
	 */
	public function __construct( array $abilities ) {
		parent::__construct(
			array(
				'singular' => 'ability',
				'plural'   => 'abilities',
				'ajax'     => false,
			)
		);

		$this->abilities = $abilities;
	}

	/**
	 * Defines table columns.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'name'        => __( 'Name', 'cdw' ),
			'label'       => __( 'Label', 'cdw' ),
			'description' => __( 'Description', 'cdw' ),
			'readonly'    => __( 'Read-only', 'cdw' ),
		);
	}

	/**
	 * Defines sortable columns.
	 *
	 * @return array<string, array<int, string|bool>>
	 */
	public function get_sortable_columns(): array {
		return array(
			'name'  => array( 'name', false ),
			'label' => array( 'label', false ),
		);
	}

	/**
	 * Defines bulk actions (none needed).
	 *
	 * @return array<string, string>
	 */
	public function get_bulk_actions(): array {
		return array();
	}

	/**
	 * Prepares the items for display.
	 */
	public function prepare_items(): void {
		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);

		$per_page = 20;
		$page     = $this->get_pagenum();

		$abilities = $this->abilities;

		$orderby = ! empty( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : 'name';
		$order   = ! empty( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'asc';

		usort(
			$abilities,
			function ( $a, $b ) use ( $orderby, $order ) {
				$a_val = $a[ $orderby ] ?? '';
				$b_val = $b[ $orderby ] ?? '';

				$result = is_string( $a_val ) ? strcasecmp( $a_val, $b_val ) : ( $a_val <=> $b_val );

				return 'asc' === $order ? $result : -$result;
			}
		);

		$search = ! empty( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';

		if ( $search ) {
			$abilities = array_filter(
				$abilities,
				function ( $ability ) use ( $search ) {
					$search_lower = strtolower( $search );
					return str_contains( strtolower( $ability['name'] ?? '' ), $search_lower )
						|| str_contains( strtolower( $ability['label'] ?? '' ), $search_lower )
						|| str_contains( strtolower( $ability['description'] ?? '' ), $search_lower );
				}
			);
		}

		$total_items = count( $abilities );
		$abilities   = array_slice( $abilities, ( $page - 1 ) * $per_page, $per_page );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);

		$this->items = $abilities;
	}

	/**
	 * Renders the default column.
	 *
	 * @param array<string, mixed> $item        Current item.
	 * @param string               $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}

	/**
	 * Renders the name column with row actions.
	 *
	 * @param array<string, mixed> $item Current item.
	 * @return string
	 */
	public function column_name( $item ): string {
		$ability_name = esc_attr( $item['name'] );
		$page         = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';

		$actions = array(
			'view' => sprintf(
				'<a href="?page=%s&action=view&ability=%s">%s</a>',
				esc_attr( $page ),
				rawurlencode( $item['name'] ),
				__( 'View', 'cdw' )
			),
			'test' => sprintf(
				'<a href="?page=%s&action=test&ability=%s">%s</a>',
				esc_attr( $page ),
				rawurlencode( $item['name'] ),
				__( 'Test', 'cdw' )
			),
		);

		return sprintf(
			'<strong><code>%s</code></strong>%s',
			$ability_name,
			$this->row_actions( $actions )
		);
	}

	/**
	 * Renders the read-only column.
	 *
	 * @param array<string, mixed> $item Current item.
	 * @return string
	 */
	public function column_readonly( $item ): string {
		if ( ! empty( $item['readonly'] ) ) {
			return '<span class="dashicons dashicons-yes-alt" style="color: #2e7d32;"></span>';
		}
		return '<span class="dashicons dashicons-dismiss" style="color: #c62828;"></span>';
	}

	/**
	 * Renders the message when no items are found.
	 */
	public function no_items(): void {
		esc_html_e( 'No CDW abilities found.', 'cdw' );
	}
}
