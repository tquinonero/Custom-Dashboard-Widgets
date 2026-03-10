<?php
/**
 * CLI command category: Transients.
 *
 * @package CDW
 */

return array(
	'category' => 'Transients',
	'commands' => array(
		array(
			'name'        => 'transient list',
			'description' => 'List all transients',
		),
		array(
			'name'        => 'transient delete <name>',
			'description' => 'Delete a transient',
		),
	),
);
