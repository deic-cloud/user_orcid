<?php

declare(strict_types=1);

namespace OCA\UserOrcid;

use OCA\UserOrcid\Service\OrcidService;

/**
 * Static facade for other apps (files_picocms placeholders, files_zenodo
 * cross-service identification). Guard call sites with
 * class_exists(\OCA\UserOrcid\Lib::class) so apps stay independently
 * installable.
 */
class Lib {
	public static function getOrcid(string $uid): string {
		try {
			return \OCP\Server::get(OrcidService::class)->getOrcid($uid);
		} catch (\Throwable) {
			return '';
		}
	}

	public static function getUserFromOrcid(string $orcid): string {
		try {
			return \OCP\Server::get(OrcidService::class)->userFromOrcid($orcid);
		} catch (\Throwable) {
			return '';
		}
	}
}
