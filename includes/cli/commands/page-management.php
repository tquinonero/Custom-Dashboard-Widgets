<?php
/**
 * CLI command category: Page Management.
 *
 * @package CDW
 */

return array(
	'category' => 'Page Management',
	'commands' => array(
		array(
			'name'        => 'page create <title>',
			'description' => 'Create a draft page',
		),
		array(
			'name'        => 'page create <title> --publish',
			'description' => 'Create and publish a page',
		),
	),
);
