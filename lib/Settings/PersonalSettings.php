<?php

declare(strict_types=1);

namespace OCA\UserOrcid\Settings;

use OCA\UserOrcid\Service\OrcidService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IUserSession;
use OCP\Settings\ISettings;
use OCP\Util;

class PersonalSettings implements ISettings {
	public function __construct(
		private OrcidService $orcidService,
		private IUserSession $userSession,
	) {
	}

	public function getForm(): TemplateResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';

		Util::addScript('user_orcid', 'personal');
		Util::addStyle('user_orcid', 'style');

		return new TemplateResponse('user_orcid', 'personal', [
			'orcid'         => $this->orcidService->getOrcid($uid),
			'authorize_url' => $this->orcidService->authorizeUrl(),
		]);
	}

	public function getSection(): string {
		return 'personal-info';
	}

	public function getPriority(): int {
		return 60;
	}
}
