<?php
/**
 * CLI command category: Maintenance.
 *
 * @package CDW
 */

return array(
	'category' => 'Maintenance',
	'commands' => array(
		array(
			'name'        => 'maintenance status',
			'description' => 'Show maintenance mode status',
		),
		array(
			'name'        => 'maintenance enable',
			'description' => 'Enable maintenance mode',
		),
		array(
			'name'        => 'maintenance disable',
			'description' => 'Disable maintenance mode',
		),
	),
);
