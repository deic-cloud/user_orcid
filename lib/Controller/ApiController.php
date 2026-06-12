<?php

declare(strict_types=1);

namespace OCA\UserOrcid\Controller;

use OCA\UserOrcid\Service\OrcidService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;

class ApiController extends OCSController {
	public function __construct(
		string               $appName,
		IRequest             $request,
		private OrcidService $orcidService,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function getOrcid(): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		return new DataResponse(['orcid' => $this->orcidService->getOrcid($uid)]);
	}

	/**
	 * Disconnect the ORCID iD. There is deliberately no OCS setter: an iD
	 * only enters the system through the verified OAuth callback.
	 */
	#[NoAdminRequired]
	public function deleteOrcid(): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$this->orcidService->removeOrcid($uid);
		return new DataResponse(['msg' => 'Disconnected']);
	}

	// Admin-only (no #[NoAdminRequired])

	public function getClient(): DataResponse {
		$client = $this->orcidService->getClientConfig();
		$client['redirectUri'] = $this->orcidService->redirectUri();
		return new DataResponse($client);
	}

	public function setClient(string $clientAppID, string $clientSecret = '', string $baseUrl = ''): DataResponse {
		$this->orcidService->setClientConfig($clientAppID, $clientSecret, $baseUrl);
		return new DataResponse(['msg' => 'Saved']);
	}
}
