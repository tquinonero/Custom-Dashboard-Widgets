<?php
/**
 * CLI command category: Cron.
 *
 * @package CDW
 */

return array(
	'category' => 'Cron',
	'commands' => array(
		array(
			'name'        => 'cron list',
			'description' => 'List scheduled cron events',
		),
		array(
			'name'        => 'cron run <hook>',
			'description' => 'Run a cron hook immediately',
		),
	),
);
