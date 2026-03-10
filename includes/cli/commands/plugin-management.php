<?php
/**
 * CLI command category: Plugin Management.
 *
 * @package CDW
 */

return array(
	'category' => 'Plugin Management',
	'commands' => array(
		array(
			'name'        => 'plugin list',
			'description' => 'List all plugins',
		),
		array(
			'name'        => 'plugin status <slug>',
			'description' => 'Show plugin status',
		),
		array(
			'name'        => 'plugin install <slug>',
			'description' => 'Install a plugin',
		),
		array(
			'name'        => 'plugin activate <slug>',
			'description' => 'Activate a plugin',
		),
		array(
			'name'        => 'plugin deactivate <slug>',
			'description' => 'Deactivate a plugin',
		),
		array(
			'name'        => 'plugin update <slug>',
			'description' => 'Update a plugin',
		),
		array(
			'name'        => 'plugin update --all',
			'description' => 'Update all plugins',
		),
		array(
			'name'        => 'plugin delete <slug>',
			'description' => 'Delete a plugin (requires --force)',
		),
	),
);
