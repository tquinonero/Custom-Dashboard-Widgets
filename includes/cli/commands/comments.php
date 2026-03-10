<?php
/**
 * CLI command category: Comments.
 *
 * @package CDW
 */

return array(
	'category' => 'Comments',
	'commands' => array(
		array(
			'name'        => 'comment list [pending|approved|spam]',
			'description' => 'List comments (default: pending)',
		),
		array(
			'name'        => 'comment approve <id>',
			'description' => 'Approve a comment',
		),
		array(
			'name'        => 'comment spam <id>',
			'description' => 'Mark a comment as spam',
		),
		array(
			'name'        => 'comment delete <id> --force',
			'description' => 'Permanently delete a comment',
		),
	),
);
