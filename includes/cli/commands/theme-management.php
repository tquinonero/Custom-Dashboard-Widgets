<?php
/**
 * CLI command category: Theme Management.
 *
 * @package CDW
 */

return array(
	'category' => 'Theme Management',
	'commands' => array(
		array(
			'name'        => 'theme info',
			'description' => 'Show active theme details',
		),
		array(
			'name'        => 'theme list',
			'description' => 'List all themes',
		),
		array(
			'name'        => 'theme status <slug>',
			'description' => 'Show theme status',
		),
		array(
			'name'        => 'theme activate <slug>',
			'description' => 'Activate a theme',
		),
		array(
			'name'        => 'theme install <slug>',
			'description' => 'Install a theme',
		),
		array(
			'name'        => 'theme update <slug>',
			'description' => 'Update a theme',
		),
		array(
			'name'        => 'theme update --all',
			'description' => 'Update all themes',
		),
		array(
			'name'        => 'theme delete <slug>',
			'description' => 'Delete a theme',
		),
	),
);
