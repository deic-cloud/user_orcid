/* global OC, t */

(function () {
	'use strict';

	const OCS = (OC.webroot || '') + '/ocs/v2.php/apps/user_orcid/api/v1';

	document.addEventListener('DOMContentLoaded', () => {
		const section = document.getElementById('orcidPersonalSettings');
		if (!section) return;

		document.getElementById('orcidConnect')?.addEventListener('click', () => {
			const url = section.dataset.authorizeUrl;
			if (!url) return;
			window.open(url, '_blank',
				'toolbar=no, scrollbars=yes, width=620, height=720');
		});

		document.getElementById('orcidDisconnect')?.addEventListener('click', async () => {
			const res = await fetch(OCS + '/orcid?format=json', {
				method: 'DELETE',
				headers: { 'OCS-APIREQUEST': 'true', 'requesttoken': OC.requestToken },
			});
			const data = await res.json();
			if (data?.ocs?.meta?.status === 'ok') {
				document.getElementById('orcidConnected').style.display = 'none';
				document.getElementById('orcidNotConnected').style.display = '';
			} else {
				document.getElementById('orcidMsg').textContent =
					t('user_orcid', 'Could not disconnect.');
			}
		});
	});
})();
