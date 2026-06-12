<?php

declare(strict_types=1);

namespace OCA\UserOrcid\Controller;

use OCA\UserOrcid\Service\OrcidService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;

/**
 * Entry point for logging in with ORCID: redirects to the orcid.org
 * authorize page with state=login; the callback resolves the verified iD
 * to an account. Linked from the login page as an alternative login.
 */
class LoginController extends Controller {
	public function __construct(
		string                $appName,
		IRequest              $request,
		private OrcidService  $orcidService,
		private IUserSession  $userSession,
		private IURLGenerator $urlGenerator,
	) {
		parent::__construct($appName, $request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	public function login(): Response {
		if ($this->userSession->getUser() !== null) {
			return new RedirectResponse($this->urlGenerator->linkToDefaultPageUrl());
		}
		$url = $this->orcidService->authorizeUrl('login');
		if ($url === '') {
			return new TemplateResponse('user_orcid', 'callback', [
				'status' => 'error',
				'detail' => 'ORCID login is not available on this server.',
			], TemplateResponse::RENDER_AS_GUEST);
		}
		return new RedirectResponse($url);
	}
}
