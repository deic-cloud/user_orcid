<?php
/** @var \OCP\IL10N $l */
/** @var array $_ */
$ok = $_['status'] === 'ok';
?>
<div class="orcid-callback" style="text-align:center;padding:40px;font-family:sans-serif;">
	<?php if ($ok): ?>
	<h2><?php p($l->t('ORCID connected')); ?></h2>
	<p><a href="https://orcid.org/<?php p($_['detail']); ?>"><?php p($_['detail']); ?></a></p>
	<p><?php p($l->t('You can close this window.')); ?></p>
	<?php else: ?>
	<h2><?php p($l->t('ORCID connection failed')); ?></h2>
	<p><?php p($_['detail']); ?></p>
	<?php endif; ?>
</div>
<script nonce="<?php p($_['cspNonce'] ?? \OC::$server->get(\OCP\Security\CSP\IContentSecurityPolicyNonceManager::class)->getNonce()); ?>">
	if (window.opener) {
		try { window.opener.location.reload(); } catch (e) {}
		<?php if ($ok): ?>setTimeout(function () { window.close(); }, 1500);<?php endif; ?>
	}
</script>
