<?php

declare(strict_types=1);

namespace OCA\UserOrcid\Controller;

use OCA\UserOrcid\Service\OrcidService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;

/**
 * Inter-server endpoints called by silos through files_sharding's
 * InterServerClient. The master holds the authoritative uid↔ORCID mapping.
 * Authentication: Bearer token matching files_sharding_shared_secret.
 */
class InternalController extends Controller {
	public function __construct(
		string               $appName,
		IRequest             $request,
		private OrcidService $orcidService,
		private IConfig      $config,
	) {
		parent::__construct($appName, $request);
	}

	private function checkSecret(): bool {
		$secret = (string)$this->config->getSystemValue('files_sharding_shared_secret', '');
		if ($secret === '') {
			return false;
		}
		return $this->request->getHeader('Authorization') === 'Bearer ' . $secret;
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function getOrcid(string $user): JSONResponse {
		if (!$this->checkSecret()) {
			return new JSONResponse(['error' => 'Unauthorized'], 401);
		}
		return new JSONResponse(['orcid' => $this->orcidService->getOrcid($user)]);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function setOrcid(string $user, string $orcid): JSONResponse {
		if (!$this->checkSecret()) {
			return new JSONResponse(['error' => 'Unauthorized'], 401);
		}
		$res = $this->orcidService->setOrcid($user, $orcid);
		if ($res === OrcidService::SET_CONFLICT) {
			return new JSONResponse(['error' => 'ORCID already connected to another account'], 409);
		}
		if ($res !== OrcidService::SET_OK) {
			return new JSONResponse(['error' => 'Invalid ORCID'], 400);
		}
		return new JSONResponse(['msg' => 'Saved']);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function deleteOrcid(string $user): JSONResponse {
		if (!$this->checkSecret()) {
			return new JSONResponse(['error' => 'Unauthorized'], 401);
		}
		$this->orcidService->removeOrcid($user);
		return new JSONResponse(['msg' => 'Removed']);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function userFromOrcid(string $orcid): JSONResponse {
		if (!$this->checkSecret()) {
			return new JSONResponse(['error' => 'Unauthorized'], 401);
		}
		return new JSONResponse(['user' => $this->orcidService->userFromOrcid($orcid)]);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	public function getClient(): JSONResponse {
		if (!$this->checkSecret()) {
			return new JSONResponse(['error' => 'Unauthorized'], 401);
		}
		return new JSONResponse($this->orcidService->getClientConfig(true));
	}
}
