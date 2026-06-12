<?php

declare(strict_types=1);

namespace OCA\UserOrcid\AppInfo;

use OCA\UserOrcid\Login\OrcidLogin;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'user_orcid';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerAlternativeLogin(OrcidLogin::class);
	}

	public function boot(IBootContext $context): void {
	}
}
