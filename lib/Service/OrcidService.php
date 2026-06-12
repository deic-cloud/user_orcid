<?php

declare(strict_types=1);

namespace OCA\UserOrcid\Service;

use OCP\Http\Client\IClientService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * uid ↔ ORCID iD mapping, verified through ORCID OAuth.
 *
 * Storage: user preference user_orcid/orcid on the user's node. In a
 * files_sharding setup the master holds the authoritative copy (it is the
 * only node that can answer orcid→uid lookups across silos); silos forward
 * every mutation and fall through to the master on lookups. All sharding
 * calls are guarded — the app works standalone.
 */
class OrcidService {
	public const SET_OK       = 0;
	public const SET_CONFLICT = 1;
	public const SET_FAILED   = 2;

	public function __construct(
		private IConfig         $config,
		private IDBConnection   $db,
		private IClientService  $clientService,
		private IURLGenerator   $urlGenerator,
		private LoggerInterface $logger,
	) {
	}

	// ── Mapping ───────────────────────────────────────────────────────────────

	/** The user's ORCID iD ('' when not connected). */
	public function getOrcid(string $uid): string {
		$orcid = $this->config->getUserValue($uid, 'user_orcid', 'orcid', '');
		if ($orcid === '' && !$this->isMaster()) {
			$data = $this->masterGet('internal/orcid', ['user' => $uid]);
			$orcid = (string)($data['orcid'] ?? '');
		}
		return $orcid;
	}

	/**
	 * Store a verified ORCID iD for a user. Rejects an iD already connected
	 * to a different account (the mapping must stay one-to-one).
	 */
	public function setOrcid(string $uid, string $orcid): int {
		$orcid = trim($orcid);
		if ($orcid !== '' && !preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/', $orcid)) {
			return self::SET_FAILED;
		}
		if ($orcid !== '') {
			$existing = $this->userFromOrcid($orcid);
			if ($existing !== '' && $existing !== $uid) {
				return self::SET_CONFLICT;
			}
		}
		$this->config->setUserValue($uid, 'user_orcid', 'orcid', $orcid);
		if (!$this->isMaster()) {
			$action = $orcid === '' ? 'internal/orcid/delete' : 'internal/orcid';
			$result = $this->masterPost($action, ['user' => $uid, 'orcid' => $orcid]);
			if ($this->shardingConfigured() && $result === null) {
				$this->logger->error('user_orcid: failed to sync ORCID mapping to master for ' . $uid);
			}
		}
		return self::SET_OK;
	}

	public function removeOrcid(string $uid): void {
		$this->setOrcid($uid, '');
	}

	/** The uid connected to an ORCID iD ('' when none). */
	public function userFromOrcid(string $orcid): string {
		$orcid = trim($orcid);
		if ($orcid === '') {
			return '';
		}
		if (!$this->isMaster() && $this->shardingConfigured()) {
			$data = $this->masterGet('internal/user', ['orcid' => $orcid]);
			return (string)($data['user'] ?? '');
		}
		return $this->dbUserFromOrcid($orcid);
	}

	private function dbUserFromOrcid(string $orcid): string {
		$qb = $this->db->getQueryBuilder();
		$qb->select('userid')
		   ->from('preferences')
		   ->where($qb->expr()->eq('appid',       $qb->createNamedParameter('user_orcid')))
		   ->andWhere($qb->expr()->eq('configkey',   $qb->createNamedParameter('orcid')))
		   ->andWhere($qb->expr()->eq('configvalue', $qb->createNamedParameter($orcid)));
		$result = $qb->executeQuery();
		$rows   = $result->fetchAll();
		$result->closeCursor();
		if (count($rows) > 1) {
			$this->logger->error('user_orcid: duplicate entries for ORCID ' . $orcid);
		}
		return $rows ? (string)$rows[0]['userid'] : '';
	}

	// ── ORCID OAuth ───────────────────────────────────────────────────────────

	/** @return array{clientAppID: string, clientSecret: string, baseUrl: string} */
	public function getClientConfig(bool $includeSecret = false): array {
		$id     = $this->config->getAppValue('user_orcid', 'clientAppID', '');
		$secret = $this->config->getAppValue('user_orcid', 'clientSecret', '');
		$base   = $this->config->getAppValue('user_orcid', 'baseUrl', '') ?: 'https://orcid.org';
		// Silos without local credentials fetch them from the master (and cache)
		if ($id === '' && !$this->isMaster() && $this->shardingConfigured()) {
			$data = $this->masterGet('internal/client', []);
			if (!empty($data['clientAppID'])) {
				$id     = (string)$data['clientAppID'];
				$secret = (string)($data['clientSecret'] ?? '');
				$base   = (string)($data['baseUrl'] ?? '') ?: $base;
				$this->config->setAppValue('user_orcid', 'clientAppID', $id);
				$this->config->setAppValue('user_orcid', 'clientSecret', $secret);
				$this->config->setAppValue('user_orcid', 'baseUrl', $base);
			}
		}
		return [
			'clientAppID'  => $id,
			'clientSecret' => $includeSecret ? $secret : '',
			'baseUrl'      => rtrim($base, '/'),
		];
	}

	public function setClientConfig(string $clientAppID, string $clientSecret, string $baseUrl = ''): void {
		$this->config->setAppValue('user_orcid', 'clientAppID', trim($clientAppID));
		if ($clientSecret !== '') {
			$this->config->setAppValue('user_orcid', 'clientSecret', trim($clientSecret));
		}
		$this->config->setAppValue('user_orcid', 'baseUrl', rtrim(trim($baseUrl), '/'));
	}

	/** This node's OAuth redirect URI (register it at orcid.org developer tools). */
	public function redirectUri(): string {
		return $this->urlGenerator->linkToRouteAbsolute('user_orcid.callback.receive');
	}

	/** The orcid.org authorize URL the personal settings button opens. */
	public function authorizeUrl(): string {
		$client = $this->getClientConfig();
		if ($client['clientAppID'] === '') {
			return '';
		}
		return $client['baseUrl'] . '/oauth/authorize?' . http_build_query([
			'client_id'     => $client['clientAppID'],
			'response_type' => 'code',
			'scope'         => '/authenticate',
			'redirect_uri'  => $this->redirectUri(),
		]);
	}

	/**
	 * Exchange an OAuth authorization code for the (verified) ORCID iD.
	 * @return array{orcid: string, name: string}|null
	 */
	public function exchangeCode(string $code): ?array {
		$client = $this->getClientConfig(true);
		if ($client['clientAppID'] === '') {
			$this->logger->error('user_orcid: no API client configured');
			return null;
		}
		try {
			$response = $this->clientService->newClient()->post(
				$client['baseUrl'] . '/oauth/token',
				[
					'form_params' => [
						'client_id'     => $client['clientAppID'],
						'client_secret' => $client['clientSecret'],
						'grant_type'    => 'authorization_code',
						'code'          => $code,
						'redirect_uri'  => $this->redirectUri(),
					],
					'headers' => ['Accept' => 'application/json'],
					'timeout' => 15,
				]
			);
			$data = json_decode((string)$response->getBody(), true);
			if (!is_array($data) || empty($data['orcid'])) {
				$this->logger->error('user_orcid: token response without orcid: ' . substr((string)$response->getBody(), 0, 300));
				return null;
			}
			return ['orcid' => (string)$data['orcid'], 'name' => (string)($data['name'] ?? '')];
		} catch (\Throwable $e) {
			$this->logger->error('user_orcid: token exchange failed: ' . $e->getMessage());
			return null;
		}
	}

	// ── files_sharding glue (all guarded — app works standalone) ─────────────

	private function isMaster(): bool {
		if (!$this->shardingConfigured()) {
			return true; // standalone behaves like the source of truth
		}
		$val = $this->config->getSystemValue('files_sharding_master', false);
		return $val === true || $val === 1 || $val === '1' || $val === 'true';
	}

	private function shardingConfigured(): bool {
		return class_exists(\OCA\FilesSharding\Service\InterServerClient::class)
			&& (string)$this->config->getSystemValue('files_sharding_shared_secret', '') !== '';
	}

	private function masterBase(): string {
		$url = rtrim((string)$this->config->getSystemValue('files_sharding_master_internal_url', ''), '/');
		if ($url === '') {
			$url = rtrim((string)$this->config->getSystemValue('files_sharding_master_url', ''), '/');
		}
		return $url;
	}

	private function masterGet(string $path, array $query): array {
		if (!$this->shardingConfigured() || $this->masterBase() === '') {
			return [];
		}
		try {
			$client = \OCP\Server::get(\OCA\FilesSharding\Service\InterServerClient::class);
			$data   = $client->getDirect($this->masterBase(), $path, $query, 'user_orcid');
			return is_array($data) ? $data : [];
		} catch (\Throwable) {
			return [];
		}
	}

	private function masterPost(string $path, array $body): ?array {
		if (!$this->shardingConfigured() || $this->masterBase() === '') {
			return null;
		}
		try {
			$client = \OCP\Server::get(\OCA\FilesSharding\Service\InterServerClient::class);
			return $client->postDirect($this->masterBase(), $path, $body, 'user_orcid');
		} catch (\Throwable) {
			return null;
		}
	}
}
