<?php

declare(strict_types=1);

namespace OCA\UserOrcid\Settings;

use OCA\UserOrcid\Service\OrcidService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;
use OCP\Util;

class AdminSettings implements ISettings {
	public function __construct(
		private OrcidService $orcidService,
	) {
	}

	public function getForm(): TemplateResponse {
		Util::addScript('user_orcid', 'admin');
		Util::addStyle('user_orcid', 'style');

		$client = $this->orcidService->getClientConfig();

		return new TemplateResponse('user_orcid', 'admin', [
			'client_app_id' => $client['clientAppID'],
			'base_url'      => $client['baseUrl'],
			'redirect_uri'  => $this->orcidService->redirectUri(),
		]);
	}

	public function getSection(): string {
		return 'additional';
	}

	public function getPriority(): int {
		return 60;
	}
}
