# Postman kit: Accounting Integration API

| File | Who gets it |
|---|---|
| `Accounting-Integration-Admin.postman_collection.json` | Your team only. Has login, connections, token minting |
| `Accounting-Integration-Admin.postman_environment.json` | Your team only |
| `Accounting-Integration-Partner.postman_collection.json` | Safe to share with integrators. Has no login or admin calls |
| `Accounting-Integration-Partner.postman_environment.json` | Safe to share while it's still blank |

The environment files are blank templates with no tokens or passwords.

## Test it yourself (Admin)

1. In Postman, choose **Import** and select the two Admin files. Pick the environment at the top right.
2. Fill in the environment's **current value** fields:

   | Variable | Value |
   |---|---|
   | `base_url` | For example `https://erp.example.com` |
   | `tenant_slug` | The tenant's slug. Login needs it too |
   | `admin_email`, `admin_password` | A user in that tenant, preferably the service user below |
   | `voucher_date` | A date in an open fiscal period, as `YYYY-MM-DD` |
   | `allow_writes` | `true` on a test tenant only |

3. Run **Log in**, **Create connection** and **Mint connector token**, in that order. Each one saves its result for the next.
4. Work through the remaining folders, then run **8. Security checks**.
5. When finished, run **Revoke connector token** if you no longer need it, then **Log out**.

The sample requests create ledgers named "Sample ... (Postman)" and post a payment of 1,000, so never run them on real books.

## Share it with a partner

1. Create a dedicated service user with only `accounting.integrations.view` and `accounting.integrations.sync`. Give it `manage` for the moment you mint, then remove it.
2. Log in as that user and mint a token. A token acts as whoever mints it, so a token minted by an owner has the owner's permissions on that connection.
3. Send the two Partner files, with `base_url`, `tenant_slug` and `connection_id`, by any normal route.
4. Send the token separately through a password manager or other secret-sharing tool. Never send it by email or chat, and never in the same message as the files.

Give each partner their own connection and token, so you can cut off one without affecting the others.

## Security built into the kit

| Protection | What it does |
|---|---|
| HTTPS only | Blocks a request if `base_url` isn't `https://`. Plain `http` is allowed only for `localhost`, `127.0.0.1`, `[::1]` and `.test` hosts |
| Writes off by default | Blocks anything that changes data until `allow_writes` is `true` |
| No stored secrets | Tokens and passwords are secret-type variables, blank in the files |
| Password cleared | **Log in** clears `admin_password` from the environment after it succeeds |
| Separate tokens | Partners use a connector token limited to one connection and this API |
| Security checks folder | Confirms the API refuses missing or invalid tokens, other connections, other tenants and reused idempotency keys |

## Security rules for anyone using it

- Keep tokens in **current value** only, and don't store them in a shared Postman workspace.
- Never export or commit an environment after filling it in.
- Response panels and the Postman Console can show tokens, so take care with screenshots and screen sharing.
- If a token may have leaked, revoke it, or pause the connection with **Pause connection**, then check the sync log.
- Tokens don't expire on their own, so revoke them when a person or machine no longer needs access.
- Keep the Tally HTTP port closed to the internet. Postman talks to this API, never directly to Tally.

## Errors you might see

| Result | Likely cause |
|---|---|
| `[Blocked]` in the Postman Console | The kit stopped the request. The message says which setting to change |
| `404` on Log in | `tenant_slug` doesn't match an active tenant |
| `401` on Log in, with the right password | The user isn't in that tenant |
| `401` on other calls | Token missing, revoked or invalid |
| `402` | The tenant's subscription has lapsed |
| `403` | The user lacks the permission, or the token is for another connection |
| `404` | Wrong connection id or tenant slug |
| `409` | The same `Idempotency-Key` was sent with different data |
| `429` | Over the limit of 600 requests a minute, or 5 login attempts a minute |

A request can return `201` while some of its records failed, so always read `failed_count` and each record's `outcome` and `error_code`. The Send requests include a test for this.

Idempotency keys are new on every click here, so each send is a new request. A real connector must store its key and reuse it when retrying, or a retry won't be recognised as a repeat.
