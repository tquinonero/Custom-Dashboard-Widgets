<?php
/**
 * CLI command category: Skills.
 *
 * @package CDW
 */

return array(
	'category' => 'Skills',
	'commands' => array(
		array(
			'name'        => 'skill list',
			'description' => 'List all agent skill docs available in installed plugins',
		),
		array(
			'name'        => 'skill get <plugin-slug> <skill-name>',
			'description' => 'Read a plugin skill overview (SKILL.md)',
		),
		array(
			'name'        => 'skill get <plugin-slug> <skill-name> <file>',
			'description' => 'Read a specific file within a plugin skill (e.g. instructions/attributes.md)',
		),
	),
);
