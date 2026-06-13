# user_orcid — ORCID iDs for Nextcloud accounts

Users connect their Nextcloud account to their
[ORCID iD](https://orcid.org) through ORCID OAuth. The verified mapping is
available to other apps for cross-service user identification, in both
directions: user → ORCID and ORCID → user (used e.g. by files_zenodo).

**Author:** Frederik Orellana, Technical University of Denmark (fror@dtu.dk)
(NC port of the ownCloud 7 app by Lars Næsbye Christensen, DeIC)
**License:** AGPL-3.0

---

## How it works

- **Personal settings → Personal info**: a Connect button opens the ORCID
  authorize page in a popup (`scope=/authenticate`). ORCID redirects back to
  `/index.php/apps/user_orcid/callback`, the app exchanges the code for a
  token and stores the **verified** iD as user preference
  `user_orcid/orcid`. Disconnect removes it.
- The callback requires a logged-in session — connecting an iD is an action
  on an existing account, not a login method. There is deliberately no API
  to set an iD directly: it only enters the system through OAuth.
- One-to-one: an iD already connected to another account is rejected.

## Admin configuration

Settings → Administration → Additional settings: Client ID and secret from
[orcid.org/developer-tools](https://orcid.org/developer-tools). The base URL
can be pointed at https://sandbox.orcid.org for testing.

**Redirect URIs:** ORCID validates the redirect against the list registered
for the client app. Register **every node's** redirect URI there (the admin
page shows the local one) — the master *and* each silo, e.g.
`https://cloud.example.org/index.php/apps/user_orcid/callback` and
`https://silo1.example.org/index.php/apps/user_orcid/callback`. A single
client app (configured on the master only) is enough; silos fetch the
credentials from the master, but each silo still serves the OAuth flow from
its own origin, so its callback URI must be registered.

App config keys (`oc_appconfig`, app `user_orcid`): `clientAppID`,
`clientSecret`, `baseUrl` — set on the master; silos fetch and cache them.

## API for other apps

```php
if (class_exists(\OCA\UserOrcid\Lib::class)) {
    $orcid = \OCA\UserOrcid\Lib::getOrcid($uid);          // '' when not connected
    $uid   = \OCA\UserOrcid\Lib::getUserFromOrcid($orcid); // '' when unknown
}
```

(Or inject `OCA\UserOrcid\Service\OrcidService`.) files_picocms uses this for
the `%orcid%` placeholder on personal pages.

## OCS API

Base: `/ocs/v2.php/apps/user_orcid/api/v1` (header `OCS-APIREQUEST: true`).

| Method | Path | Description |
|--------|------|-------------|
| GET | `/orcid` | Current user's iD: `{"orcid": "0000-…"}` |
| DELETE | `/orcid` | Disconnect |
| GET | `/client` | Admin: client config (no secret) + this node's `redirectUri` |
| POST | `/client` | Admin: `clientAppID`, `clientSecret` (empty = unchanged), `baseUrl` |

## files_sharding integration

The master holds the authoritative mapping (only it can answer ORCID→user
across silos). Silos store a local copy, forward every mutation, fall through
to the master on lookups, and fetch the API client credentials from the
master when they have none locally. Internal endpoints (Bearer
`files_sharding_shared_secret`, plain JSON at
`/index.php/apps/user_orcid/internal/…`):

| Method | Path | Params | Returns |
|--------|------|--------|---------|
| GET | `/internal/orcid` | `user` | `{"orcid"}` |
| POST | `/internal/orcid` | `user`, `orcid` | `{"msg"}` / 409 on conflict |
| POST | `/internal/orcid/delete` | `user` | `{"msg"}` |
| GET | `/internal/user` | `orcid` | `{"user"}` |
| GET | `/internal/client` | — | `{clientAppID, clientSecret, baseUrl}` |

All sharding calls are guarded — the app works standalone.

## Logging in with ORCID

A deliberately low-key "Log in with ORCID" link under the login form
(`IAlternativeLogin`) starts the same OAuth flow with `state=login`: the
callback resolves the **verified** iD to the connected account and logs the
user in — the stable identity across a researcher's institutional moves.

- Unknown iD → friendly error telling the user to log in normally first and
  connect the iD in personal settings. Nothing is created implicitly.
- files_sharding: a silo restarts the flow on the master; the master resolves
  the home silo and hands over with the same one-time-token exchange the
  institutional login uses (`/apps/files_sharding/login?token=…`).
- Note: like the institutional exchange, this path does not go through
  Nextcloud server-side 2FA.
