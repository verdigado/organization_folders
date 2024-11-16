<?php

return [
	'resources' => [
	],
	'routes' => [
		/* Resources */
		['name' => 'resource#show', 'url' => '/resources/{resourceId}', 'verb' => 'GET'],
		['name' => 'resource#create', 'url' => '/resources/{resourceId}', 'verb' => 'POST'],
		['name' => 'resource#update', 'url' => '/resources/{resourceId}', 'verb' => 'PUT'],
	],
];