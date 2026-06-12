<?php

declare(strict_types=1);

namespace OCA\UserOrcid\Login;

use OCP\Authentication\IAlternativeLogin;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Util;

/**
 * Discreet "Log in with ORCID" entry under the login form. Deliberately
 * low-key: institutional login is the primary flow; ORCID is the stable
 * fallback identity across a researcher's institutional moves.
 */
class OrcidLogin implements IAlternativeLogin {
	public function __construct(
		private IL10N         $l,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getLabel(): string {
		return $this->l->t('Log in with ORCID');
	}

	public function getLink(): string {
		return $this->urlGenerator->linkToRoute('user_orcid.login.login');
	}

	public function getClass(): string {
		return 'orcid-alt-login';
	}

	public function load(): void {
		Util::addStyle('user_orcid', 'login');
	}
}
