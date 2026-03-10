<?php
/**
 * CLI command category: Media.
 *
 * @package CDW
 */

return array(
	'category' => 'Media',
	'commands' => array(
		array(
			'name'        => 'media list',
			'description' => 'List recent media attachments (20)',
		),
		array(
			'name'        => 'media list <count>',
			'description' => 'List N most recent media attachments',
		),
	),
);
