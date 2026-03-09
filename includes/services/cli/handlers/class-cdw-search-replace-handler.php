<?php
/**
 * Search-Replace command handler for CDW CLI service.
 *
 * @package CDW
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CDW_PLUGIN_DIR . 'includes/services/cli/handlers/abstract-cdw-handler.php';

/**
 * Handles search-replace commands across database tables.
 */
class CDW_Search_Replace_Handler extends CDW_Abstract_Handler {

	/**
	 * Execute search-replace command.
	 *
	 * @param string            $subcmd   Subcommand (not used - search-replace takes args directly).
	 * @param array<int,string> $args    Positional arguments [old, new].
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed> Result array.
	 */
	public function execute( string $subcmd, array $args, array $raw_args = array() ): array {
		return $this->handle_search_replace( $args, $raw_args );
	}

	/**
	 * Get help text for search-replace command.
	 *
	 * @return array<string,mixed>
	 */
	public function get_help(): array {
		return array(
			'output'  => "Usage: search-replace <old> <new> [--dry-run] [--force]\n  old  - Text to search for\n  new  - Replacement text\n  --dry-run - Preview changes without applying",
			'success' => false,
		);
	}

	/**
	 * Check if subcommand requires --force flag.
	 *
	 * @param string $subcmd The subcommand to check.
	 * @return bool
	 */
	public function requires_force( string $subcmd ): bool {
		return true;
	}

	/**
	 * Handle search-replace.
	 *
	 * @param array<int,string> $args    Positional arguments [old, new].
	 * @param array<int,string> $raw_args Full raw args including flags.
	 * @return array<string,mixed>
	 */
	private function handle_search_replace( array $args, array $raw_args ): array {
		if ( count( $args ) < 2 ) {
			return $this->failure( "Usage: search-replace <old> <new> [--dry-run] [--force]\n  old  - Text to search for\n  new  - Replacement text\n  --dry-run - Preview changes without applying" );
		}

		$old = wp_unslash( $args[0] );
		$new = wp_unslash( $args[1] );
		$dry_run = $this->has_dry_run_flag( $raw_args );

		global $wpdb;
		$tables = $wpdb->get_results(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ),
			ARRAY_N
		);

		$output  = "Search & Replace: '$old' -> '$new'\n";
		$output .= $dry_run ? "(DRY RUN - no changes made)\n" : "(APPLYING CHANGES)\n";

		$count = 0;
		foreach ( $tables as $table ) {
			$table_name         = $table[0];
			$table_name_escaped = preg_replace( '/[^a-zA-Z0-9_]/', '', $table_name );
			if ( empty( $table_name_escaped ) ) {
				continue;
			}
			$columns = $wpdb->get_results( "SHOW COLUMNS FROM `$table_name_escaped`", ARRAY_N ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_name_escaped is sanitized via preg_replace to alphanumeric+underscore only.

			foreach ( $columns as $column ) {
				$col_name         = $column[0];
				$col_name_escaped = preg_replace( '/[^a-zA-Z0-9_]/', '', $col_name );
				if ( empty( $col_name_escaped ) ) {
					continue;
				}
				$col_type = $column[1];

				if ( stripos( $col_type, 'char' ) === false && stripos( $col_type, 'text' ) === false ) {
					continue;
				}

				if ( $dry_run ) {
					$result = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT COUNT(*) as cnt FROM `$table_name_escaped` WHERE `$col_name_escaped` LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- backtick-quoted identifiers sanitized via preg_replace.
							'%' . $wpdb->esc_like( $old ) . '%'
						)
					);
					if ( $result && $result[0]->cnt > 0 ) {
						$output .= "  $table_name.$col_name: {$result[0]->cnt} matches\n";
						$count  += $result[0]->cnt;
					}
				} else {
					$pk_col = null;
					foreach ( $columns as $col_def ) {
						if ( strtoupper( $col_def[3] ) === 'PRI' ) {
							$pk_col = preg_replace( '/[^a-zA-Z0-9_]/', '', $col_def[0] );
							break;
						}
					}

					if ( ! $pk_col ) {
						$affected = (int) $wpdb->query(
							$wpdb->prepare(
								"UPDATE `$table_name_escaped` SET `$col_name_escaped` = REPLACE(`$col_name_escaped`, %s, %s) WHERE `$col_name_escaped` LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- backtick-quoted identifiers sanitized via preg_replace.
								$old,
								$new,
								'%' . $wpdb->esc_like( $old ) . '%'
							)
						);
						if ( $affected > 0 ) {
							$output .= "  $table_name.$col_name: $affected changes (no PK, bulk)\n";
							$count  += $affected;
						}
						continue;
					}

					$rows = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT `$pk_col`, `$col_name_escaped` FROM `$table_name_escaped` WHERE `$col_name_escaped` LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- backtick-quoted identifiers sanitized via preg_replace.
							'%' . $wpdb->esc_like( $old ) . '%'
						),
						ARRAY_N
					);

					$changed = 0;
					foreach ( (array) $rows as $row ) {
						$pk_val   = $row[0];
						$original = $row[1];
						$replaced = $this->replace_in_value( $original, $old, $new );
						if ( $replaced !== $original ) {
							$wpdb->update(
								$table_name_escaped,
								array( $col_name_escaped => $replaced ),
								array( $pk_col => $pk_val ),
								array( '%s' ),
								array( '%s' )
							);
							++$changed;
						}
					}

					if ( $changed > 0 ) {
						$output .= "  $table_name.$col_name: $changed changes\n";
						$count  += $changed;
					}
				}
			}
		}

		$output .= "Total: $count replacements";
		return $this->success( $output );
	}

	/**
	 * Replace a string within a value, handling serialized data.
	 *
	 * @param mixed  $value The value to process.
	 * @param string $old   String to search for.
	 * @param string $new   Replacement string.
	 * @return mixed The processed value.
	 */
	private function replace_in_value( $value, $old, $new ) {
		if ( is_serialized( $value ) ) {
			$data = unserialize( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- required for DB search-replace of serialized WP data.
			return serialize( $this->replace_in_data( $data, $old, $new ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- required for DB search-replace of serialized WP data.
		}
		return $this->replace_in_data( $value, $old, $new );
	}

	/**
	 * Recursively replace strings in data.
	 *
	 * @param mixed  $data Data to process.
	 * @param string $old  String to search for.
	 * @param string $new  Replacement string.
	 * @return mixed Processed data.
	 */
	private function replace_in_data( $data, $old, $new ) {
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[ $key ] = $this->replace_in_data( $value, $old, $new );
			}
		} elseif ( is_object( $data ) ) {
			$obj_vars = get_object_vars( $data );
			foreach ( $obj_vars as $key => $value ) {
				$data->$key = $this->replace_in_data( $value, $old, $new );
			}
		} elseif ( is_string( $data ) ) {
			$data = str_replace( $old, $new, $data );
		}
		return $data;
	}
}
