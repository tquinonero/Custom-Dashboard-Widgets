<?php
/**
 * CLI command category: Site.
 *
 * @package CDW
 */

return array(
	'category' => 'Site',
	'commands' => array(
		array(
			'name'        => 'site info',
			'description' => 'Show site info',
		),
		array(
			'name'        => 'site settings',
			'description' => 'Show WordPress settings',
		),
		array(
			'name'        => 'site status',
			'description' => 'Show site status',
		),
		array(
			'name'        => 'site empty',
			'description' => 'Optimize database',
		),
	),
);
