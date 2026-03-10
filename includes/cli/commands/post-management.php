<?php
/**
 * CLI command category: Post Management.
 *
 * @package CDW
 */

return array(
	'category' => 'Post Management',
	'commands' => array(
		array(
			'name'        => 'post create <title>',
			'description' => 'Create a draft post',
		),
		array(
			'name'        => 'post create <title> --publish',
			'description' => 'Create and publish a post',
		),
		array(
			'name'        => 'post get <id>',
			'description' => 'Get post details',
		),
		array(
			'name'        => 'post list [<type>]',
			'description' => 'List posts',
		),
		array(
			'name'        => 'post count [<type>]',
			'description' => 'Count posts by status',
		),
		array(
			'name'        => 'post status <id> <status>',
			'description' => 'Change post status',
		),
		array(
			'name'        => 'post delete <id>',
			'description' => 'Delete a post',
		),
	),
);
