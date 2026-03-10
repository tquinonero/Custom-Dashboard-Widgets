<?php
/**
 * CLI command category: Options.
 *
 * @package CDW
 */

return array(
	'category' => 'Options',
	'commands' => array(
		array(
			'name'        => 'option get <name>',
			'description' => 'Get option value',
		),
		array(
			'name'        => 'option list',
			'description' => 'List CDW options',
		),
		array(
			'name'        => 'option set <name> <value>',
			'description' => 'Set option value',
		),
		array(
			'name'        => 'option delete <name>',
			'description' => 'Delete an option',
		),
	),
);
