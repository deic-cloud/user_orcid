/* global OC, t */

(function () {
	'use strict';

	const OCS = (OC.webroot || '') + '/ocs/v2.php/apps/user_orcid/api/v1';

	document.addEventListener('DOMContentLoaded', () => {
		document.getElementById('orcidClientSave')?.addEventListener('click', async () => {
			const msg = document.getElementById('orcidAdminMsg');
			msg.textContent = t('user_orcid', 'Saving…');
			const body = new URLSearchParams({
				clientAppID:  document.getElementById('orcidClientId').value.trim(),
				clientSecret: document.getElementById('orcidClientSecret').value,
				baseUrl:      document.getElementById('orcidBaseUrl').value.trim(),
				format: 'json',
			});
			const res = await fetch(OCS + '/client', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					'OCS-APIREQUEST': 'true',
					'requesttoken': OC.requestToken,
				},
				body,
			});
			const data = await res.json();
			msg.textContent = data?.ocs?.meta?.status === 'ok'
				? t('user_orcid', 'Saved')
				: t('user_orcid', 'Save failed');
			setTimeout(() => { msg.textContent = ''; }, 3000);
		});
	});
})();
