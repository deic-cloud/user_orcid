<?php

declare(strict_types=1);

namespace OCA\UserOrcid\Controller;

use OCA\UserOrcid\Service\OrcidService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

class CallbackController extends Controller {
	public function __construct(
		string               $appName,
		IRequest             $request,
		private OrcidService  $orcidService,
		private IUserSession  $userSession,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * OAuth redirect target. Connecting an ORCID is an action on an existing
	 * account, not a login method — without a session (e.g. it expired while
	 * the user was at orcid.org) we bounce through the login page and back;
	 * the authorization code survives the round-trip.
	 */
	#[PublicPage]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function receive(string $code = '', string $error = ''): Response {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		if ($uid === '') {
			$login = $this->urlGenerator->linkToRoute('core.login.showLoginForm', [
				'redirect_url' => $this->request->getRequestUri(),
			]);
			return new RedirectResponse($login);
		}
		if ($error !== '' || $code === '') {
			return $this->result('error', 'ORCID authorization was cancelled or failed.');
		}
		$token = $this->orcidService->exchangeCode($code);
		if ($token === null) {
			return $this->result('error', 'Could not verify the ORCID authorization.');
		}
		$res = $this->orcidService->setOrcid($uid, $token['orcid']);
		if ($res === OrcidService::SET_CONFLICT) {
			return $this->result('error', 'This ORCID iD (' . $token['orcid'] . ') is already connected to another account.');
		}
		if ($res !== OrcidService::SET_OK) {
			return $this->result('error', 'Could not store the ORCID iD.');
		}
		return $this->result('ok', $token['orcid']);
	}

	private function result(string $status, string $detail): TemplateResponse {
		$response = new TemplateResponse('user_orcid', 'callback', [
			'status' => $status,
			'detail' => $detail,
		], TemplateResponse::RENDER_AS_GUEST);
		return $response;
	}
}
