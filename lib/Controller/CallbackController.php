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
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class CallbackController extends Controller {
	public function __construct(
		string                  $appName,
		IRequest                $request,
		private OrcidService    $orcidService,
		private IUserSession    $userSession,
		private IUserManager    $userManager,
		private IURLGenerator   $urlGenerator,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * OAuth redirect target for both flows, distinguished by the OAuth
	 * state parameter:
	 *
	 * - state=connect (default): attach the verified iD to the logged-in
	 *   account. Without a session (e.g. it expired at orcid.org) we bounce
	 *   through the login page and back — the code survives the round-trip.
	 * - state=login: resolve the verified iD to an account and log in,
	 *   locally or via the owner's silo (files_sharding token exchange).
	 */
	#[PublicPage]
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function receive(string $code = '', string $error = '', string $state = ''): Response {
		$uid = $this->userSession->getUser()?->getUID() ?? '';

		if ($error !== '' || $code === '') {
			return $this->result('error', 'ORCID authorization was cancelled or failed.');
		}

		if ($state === 'login') {
			return $this->handleLogin($code, $uid);
		}

		// ── Connect flow ──────────────────────────────────────────────────────
		if ($uid === '') {
			$login = $this->urlGenerator->linkToRoute('core.login.showLoginForm', [
				'redirect_url' => $this->request->getRequestUri(),
			]);
			return new RedirectResponse($login);
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

	private function handleLogin(string $code, string $sessionUid): Response {
		if ($sessionUid !== '') {
			return new RedirectResponse($this->urlGenerator->linkToDefaultPageUrl());
		}
		$token = $this->orcidService->exchangeCode($code);
		if ($token === null) {
			return $this->result('error', 'Could not verify the ORCID authorization.');
		}
		$target = $this->orcidService->resolveLoginTarget($token['orcid']);
		switch ($target['action']) {
			case 'redirect':
			case 'master':
				return new RedirectResponse($target['url']);
			case 'local':
				return $this->loginLocally($target['uid']);
			default:
				return $this->result('error',
					'No account is connected to the ORCID iD ' . $token['orcid'] . '. '
					. 'Log in with your regular account first and connect your iD in your personal settings.');
		}
	}

	private function loginLocally(string $uid): Response {
		$user = $this->userManager->get($uid);
		if ($user === null || !$user->isEnabled()) {
			return $this->result('error', 'This account is not available.');
		}
		/** @var \OC\User\Session $userSession */
		$userSession = $this->userSession;
		$userSession->completeLogin($user, ['loginName' => $uid, 'password' => '']);
		// No password argument: a passwordless session token. An empty-string
		// password would fail NC's periodic token revalidation and log the
		// user out after five minutes.
		$userSession->createSessionToken($this->request, $uid, $uid);
		$this->logger->info('user_orcid: logged in ' . $uid . ' via ORCID');
		return new RedirectResponse($this->urlGenerator->linkToDefaultPageUrl());
	}

	private function result(string $status, string $detail): TemplateResponse {
		return new TemplateResponse('user_orcid', 'callback', [
			'status' => $status,
			'detail' => $detail,
		], TemplateResponse::RENDER_AS_GUEST);
	}
}
