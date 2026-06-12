<?php
/** @var \OCP\IL10N $l */
/** @var array $_ */
$orcid        = $_['orcid'];
$authorizeUrl = $_['authorize_url'];
?>
<div class="section" id="orcidPersonalSettings"
     data-authorize-url="<?php p($authorizeUrl); ?>">
	<h2>
		<img class="orcid-logo" src="<?php p(\OC::$WEBROOT); ?>/apps/user_orcid/img/orcid.png" alt="" />
		ORCID
	</h2>
	<p class="orcid-hint">
		<?php p($l->t('Connect your account to your ORCID iD — a persistent identifier for researchers.')); ?>
		<a href="https://orcid.org/about" target="_blank" rel="noopener"><?php p($l->t("What's this?")); ?></a>
	</p>

	<div id="orcidConnected"<?php if ($orcid === '') echo ' style="display:none"'; ?>>
		<p>
			<?php p($l->t('Connected iD:')); ?>
			<a id="orcidLink" href="https://orcid.org/<?php p($orcid); ?>" target="_blank" rel="noopener"><?php p($orcid); ?></a>
		</p>
		<button id="orcidDisconnect"><?php p($l->t('Disconnect')); ?></button>
	</div>

	<div id="orcidNotConnected"<?php if ($orcid !== '') echo ' style="display:none"'; ?>>
		<?php if ($authorizeUrl !== ''): ?>
		<button id="orcidConnect" class="primary"><?php p($l->t('Connect your ORCID iD')); ?></button>
		<?php else: ?>
		<p><em><?php p($l->t('Not available — the administrator has not configured the ORCID API client yet.')); ?></em></p>
		<?php endif; ?>
	</div>
	<span id="orcidMsg" class="msg"></span>
</div>
