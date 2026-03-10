<?php
/**
 * CLI command category: Block Patterns.
 *
 * @package CDW
 */

return array(
	'category' => 'Block Patterns',
	'commands' => array(
		array(
			'name'        => 'block-patterns list',
			'description' => 'List all registered block patterns',
		),
		array(
			'name'        => 'block-patterns list <category>',
			'description' => 'List block patterns in a category',
		),
	),
);
