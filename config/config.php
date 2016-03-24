<?php

return [
	/*
	|--------------------------------------------------------------------------
	| Instruments Driver
	|--------------------------------------------------------------------------
	|
	| Supported: "statsd", "log" and "null".
	|
	*/

	'driver' => array_get($_ENV, 'INSTRUMENTS_DRIVER', 'null'),

	'application' => null,

	'statsd' => [
		'host' => array_get($_ENV, 'STATSD_HOST', '127.0.0.1'),

		'port' => array_get($_ENV, 'STATSD_PORT', 8125),

		'timeout' => null,

		'throwConnectionExceptions' => array_get($_ENV, 'APP_DEBUG'),
	],
];
