<?php
/**
 * CLI command category: Database.
 *
 * @package CDW
 */

return array(
	'category' => 'Database',
	'commands' => array(
		array(
			'name'        => 'db size',
			'description' => 'Show database size',
		),
		array(
			'name'        => 'db tables',
			'description' => 'List all tables',
		),
		array(
			'name'        => 'search-replace <old> <new>',
			'description' => 'Search and replace in the database',
		),
	),
);
