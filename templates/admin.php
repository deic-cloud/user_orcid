<?php
/** @var \OCP\IL10N $l */
/** @var array $_ */
?>
<div class="section" id="orcidAdminSettings">
	<h2>ORCID</h2>
	<p><?php p($l->t('API client credentials from')); ?>
		<a href="https://orcid.org/developer-tools" target="_blank" rel="noopener">orcid.org/developer-tools</a>.
		<?php p($l->t('Register this redirect URI there:')); ?>
		<code><?php p($_['redirect_uri']); ?></code>
	</p>
	<div class="orcid-row">
		<label for="orcidClientId"><?php p($l->t('Client ID')); ?></label>
		<input type="text" id="orcidClientId" value="<?php p($_['client_app_id']); ?>" placeholder="APP-XXXXXXXXXXXXXXXX" />
	</div>
	<div class="orcid-row">
		<label for="orcidClientSecret"><?php p($l->t('Client secret')); ?></label>
		<input type="password" id="orcidClientSecret" value="" placeholder="<?php p($l->t('(unchanged if left empty)')); ?>" autocomplete="new-password" />
	</div>
	<div class="orcid-row">
		<label for="orcidBaseUrl"><?php p($l->t('ORCID base URL')); ?></label>
		<input type="text" id="orcidBaseUrl" value="<?php p($_['base_url']); ?>" placeholder="https://orcid.org" />
		<em><?php p($l->t('Use https://sandbox.orcid.org for testing with sandbox credentials.')); ?></em>
	</div>
	<button id="orcidClientSave"><?php p($l->t('Save')); ?></button>
	<span id="orcidAdminMsg" class="msg"></span>
</div>
