<?php

declare(strict_types=1);

return [
	'ocs' => [
		// Current user's ORCID
		['name' => 'api#getOrcid',    'url' => '/api/v1/orcid',  'verb' => 'GET'],
		['name' => 'api#deleteOrcid', 'url' => '/api/v1/orcid',  'verb' => 'DELETE'],
		// Admin: ORCID API client credentials
		['name' => 'api#getClient',   'url' => '/api/v1/client', 'verb' => 'GET'],
		['name' => 'api#setClient',   'url' => '/api/v1/client', 'verb' => 'POST'],
	],
	'routes' => [
		// OAuth redirect target (registered at orcid.org)
		['name' => 'callback#receive', 'url' => '/callback', 'verb' => 'GET'],
		// Log in with ORCID (alternative login on the login page)
		['name' => 'login#login',      'url' => '/login',    'verb' => 'GET'],
		// Inter-server API (Bearer files_sharding_shared_secret; master = source of truth)
		['name' => 'internal#getOrcid',      'url' => '/internal/orcid',        'verb' => 'GET'],
		['name' => 'internal#setOrcid',      'url' => '/internal/orcid',        'verb' => 'POST'],
		['name' => 'internal#deleteOrcid',   'url' => '/internal/orcid/delete', 'verb' => 'POST'],
		['name' => 'internal#userFromOrcid', 'url' => '/internal/user',         'verb' => 'GET'],
		['name' => 'internal#getClient',     'url' => '/internal/client',       'verb' => 'GET'],
	],
];
