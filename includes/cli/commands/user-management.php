<?php
/**
 * CLI command category: User Management.
 *
 * @package CDW
 */

return array(
	'category' => 'User Management',
	'commands' => array(
		array(
			'name'        => 'user get <username|id>',
			'description' => 'Get user details',
		),
		array(
			'name'        => 'user list',
			'description' => 'List all users',
		),
		array(
			'name'        => 'user create <user> <email> <role>',
			'description' => 'Create user',
		),
		array(
			'name'        => 'user role <user> <role>',
			'description' => 'Change user role',
		),
		array(
			'name'        => 'user delete <id>',
			'description' => 'Delete a user',
		),
	),
);
