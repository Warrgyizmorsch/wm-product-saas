# Accounting integration kit

Two-way sync between this ERP's Accounting module and external books. Built so far: a product-neutral REST API, and a Tally connector.

**Status: not yet tested against a real TallyPrime.** Automated tests use XML written from Tally's published samples. Work through [TALLY_VERIFICATION.md](TALLY_VERIFICATION.md) before using this on real books.

- [API reference (OpenAPI)](openapi.yaml)
- [Postman kit](postman/README.md)
- [Tally verification checklist](TALLY_VERIFICATION.md)
- [Relay script](agent/tally-relay.ps1)

## Product support

| Product | Status | How it connects |
|---|---|---|
| TallyPrime | Built, unverified on a real install | Relay on the customer's network, or XML files |
| Any other system | Built | The REST API in this document |
| Busy | Not built | Files only. No public API or XML format found |
| Marg | Not built | Needs documentation from Marg |

**Busy:** its FAQs describe XML and Excel import/export run by hand from the Administration menu, and export is not available in the Basic edition. No XML element names are published, so a connector has to be built from real export files. Excel voucher import uses a format you define yourself, which makes it the safest route into Busy.

**Marg:** Marg sells an API gateway, described as covering orders, bills, stock and outstandings. We found no public reference for it, and nothing says whether vouchers can be pushed in. Ask Marg for the documentation before planning this.

## How it works

```
 Tally <--- localhost:9000 ---> Relay ---- outbound HTTPS ----> ERP integration API
 (customer's network)                                                    |
                                                  Mapping, posting, log, conflicts
```

- **The ERP never calls Tally.** Tally's docs describe no authentication or TLS on its HTTP port, so never expose that port to the internet. The relay makes outbound calls only.
- **The relay has no business logic.** It collects XML jobs, passes them to Tally and returns the raw reply. All mapping is on the server.
- **Everything is logged.** Each sync creates a batch, and each record in it has an outcome you can read through the API.

### What syncs

| Data | Tally to ERP | ERP to Tally |
|---|---|---|
| Ledger groups | Yes | No. Ledgers go under Tally's predefined groups |
| Ledgers | Yes | Only those used by vouchers being pushed |
| Parties | As ledgers | As ledgers, from customers, vendors and so on |
| Payment, receipt, contra, journal | Yes | Yes |
| Sales, purchase, credit and debit notes | As accounting entries | As accounting entries |

### Known limits

- **No stock or invoices.** Item invoices arrive as ledger entries only. No Sales or Purchase document or stock movement is created.
- **GST reports may not include imported sales or purchases.** Check any return that reads Sales or Purchase documents rather than journals before relying on it.
- **Imported parties are plain ledgers.** They are not linked to customers or vendors, so they don't appear in customer outstanding or bill-wise reports.
- **Auto-posted journals go to Tally as Journal vouchers.** An ERP sales invoice's journal becomes a Journal, not a Sales voucher.
- **Opening balances don't sync.** Enter them on both sides.
- **Edits to ERP-origin vouchers made in Tally aren't pulled back.** The ERP owns what it pushed.
- **Narration or number changes alone don't update a posted entry.** Only the date, type, ledgers and amounts count as a change.
- **Tally syncs are processed inside the web request.** A very large month could hit PHP's time or upload limits, so raise `post_max_size`, `upload_max_filesize` and `max_execution_time` if it does.

## Set up a sync

Requests need the `X-Tenant` header and a bearer token. Get a first token from `POST /api/auth/login`, which needs `X-Tenant` too unless you call it on the tenant's own domain. An unknown or suspended tenant returns `404 Tenant not found`, and a user who isn't in that tenant gets `401`, even with the right password.

### 1. Create a service user

Create a dedicated user in the tenant with a role holding:

| Permission | Allows |
|---|---|
| `accounting.integrations.view` | Read connections, the log, conflicts and the feed |
| `accounting.integrations.sync` | Send batches and run the relay |

Don't give it `accounting.integrations.manage`, `accounting.integrations.resolve` or `accounting.journals.approve`. Otherwise it could post past maker-checker when the tenant allows approvers to post directly. Tenant owners and company admins hold all four integration permissions by default.

A token acts as whoever mints it, so **mint the connector token while signed in as this user**. That user needs `manage` for the moment it mints. Remove it afterwards.

### 2. Create the connection

```http
POST /api/v1/accounting/integrations/connections
Authorization: Bearer <token>
X-Tenant: acme
Content-Type: application/json

{
  "name": "Tally head office",
  "product": "tally",
  "external_company": "Acme Traders",
  "settings": { "sync_from": "2026-04-01", "export_from": "2026-04-01", "auto_sync_minutes": 15 }
}
```

`external_company` must match the company name in Tally exactly.

| Setting | Default | Meaning |
|---|---|---|
| `direction` | `both` | `both`, `inbound` or `outbound` |
| `sync_from` | 30 days ago | Start date of the first pull |
| `export_from` | Everything | Oldest journal to push. **Set this**, or all history goes out |
| `rescan_days` | 45 | How far back later pulls re-read, to catch edits |
| `auto_sync_minutes` | 15 | How often the relay's poll starts a cycle. `0` means manual only |
| `voucher_type_map` | None | Custom Tally types, such as `{"Bank Payment HDFC": "payment"}` |
| `voucher_entries_tag` | `LEDGERENTRIES.LIST` | Change to `ALLLEDGERENTRIES.LIST` if Tally needs it |

### 3. Mint a connector token

```http
POST /api/v1/accounting/integrations/connections/1/tokens
{ "name": "Head office relay" }
```

Copy the token from the response, because it isn't shown again. It works for this connection and this API only, and is refused everywhere else in the ERP.

### 4. Turn on Tally's HTTP interface

In TallyPrime, go to **F1 (Help) > Settings > Advanced Configuration** and enable the HTTP server (default port 9000). Keep the company open, and keep the port closed to the internet.

### 5. Run the relay

On a Windows machine that can reach Tally, save the token to a file only the relay's account can read, then run:

```powershell
.\tally-relay.ps1 -ErpUrl https://erp.example.com -TenantSlug acme -ConnectionId 1 -TokenFile C:\ProgramData\erp-relay\token.txt
```

Add `-Once` to run a single cycle, which is useful for the first test. For ongoing use, run it from Task Scheduler at startup.

### 6. Start a sync and check it

```http
POST /api/v1/accounting/integrations/connections/1/tally/sync
{ "direction": "inbound", "from": "2026-10-01", "to": "2026-10-31" }
```

Then read `GET .../connections/1/batches` and `GET .../connections/1/conflicts`. **Start with one month, inbound only, on a test company.**

## Without the relay: XML files

| Step | Call |
|---|---|
| Upload a Tally export | `POST .../tally/import-file` with `entity` (`ledger-groups`, `ledgers` or `vouchers`) and `file`. Upload in that order |
| Download ledgers for Tally | `GET .../tally/export-file?entity=ledgers` |
| Download vouchers for Tally | `GET .../tally/export-file?entity=vouchers` |
| Confirm vouchers were imported | `POST .../feed/vouchers/ack`, using the ids in the `X-Accounting-Local-Ids` header |

Tally's help warns that importing the same voucher file twice can duplicate vouchers. Confirm each file with the ack call so it isn't offered again.

## Connect any other system

**Sending data in.** Post ledger groups, then ledgers, then vouchers to `POST .../connections/{id}/{entity}/batch`, up to 500 items at a time, with an `Idempotency-Key` header:

```json
{
  "items": [{
    "external_id": "YOUR-STABLE-ID-123",
    "voucher_type": "payment",
    "date": "2026-11-04",
    "number": "PAY-77",
    "narration": "November rent",
    "lines": [
      { "ledger_external_id": "L-RENT", "debit": 1000, "credit": 0 },
      { "ledger_external_id": "L-BANK", "debit": 0, "credit": 1000 }
    ]
  }]
}
```

- `voucher_type` is one of `payment`, `receipt`, `contra`, `journal`, `sales`, `purchase`, `credit_note` or `debit_note`.
- A line identifies its ledger by `ledger_external_id`, `ledger_name` or `account_code`.
- Send `{"external_id": "...", "is_cancelled": true}` to cancel a voucher.

**Reading data out.** `GET .../feed/{entity}` returns what you haven't received, then `POST .../feed/{entity}/ack` confirms it. Records stay in the feed until acknowledged, so a crash on your side repeats data instead of losing it. Reversed journals and their reversals both appear, because both count in the ledger.

## How duplicates are prevented

| Layer | What it does |
|---|---|
| `Idempotency-Key` | Same key and body returns the original result. Same key with a different body returns `409` |
| External ID | One ref per connection, entity and external id, with a unique index. A resend is `unchanged` |
| Journal key | A unique key on the journal, as a backstop if two syncs race |
| Loop guard | Imported journals are never exported to the same connection, and pushed vouchers are ignored when they come back |

## Record outcomes

| Outcome | Meaning |
|---|---|
| `created`, `updated` | Applied |
| `unchanged` | Already up to date |
| `pending_approval` | Saved and waiting for a checker, because of the tenant's maker-checker settings. Not in the ledger yet |
| `failed` | Not applied. See `error_code`. Fix the cause and call `POST .../batches/{id}/retry` |
| `conflict` | A person has to decide |

| `error_code` | Fix |
|---|---|
| `unmapped_ledger` | Sync ledgers, or add one with `POST .../mappings` |
| `unmapped_group` | Sync groups first, or send `type` |
| `unmapped_voucher_type` | Add it to `voucher_type_map` |
| `unbalanced` | Fix the voucher at the source |
| `posting_failed` | Usually a closed period or no fiscal year for that date. The message says which |
| `invalid_record` | A required field is missing |

## Conflicts

The sync never overwrites these on its own. List them with `GET .../conflicts`, then decide:

```http
POST .../conflicts/{id}/resolve
{ "resolution": "apply_incoming" }
```

`keep_local` keeps this ERP's version and stops raising that incoming version. `apply_incoming` applies the source's version. Resolving needs `accounting.integrations.resolve`.

| Reason | Situation | `apply_incoming` does |
|---|---|---|
| `changed_on_both_sides` | Ledger changed here and in the source | Updates the ledger |
| `account_type_change` | Source has a different account type, or the name matches a group account | Changes the type if the account has no postings, otherwise stays open. For a name match, creates a new account |
| `system_account_change` | Source would rename a system account | Refused. System accounts never change |
| `cannot_reverse_existing` | Voucher changed, but the old entry can't be reversed (closed period or bank-reconciled) | Tries again. Reopen the period or unmatch first |
| `existing_awaiting_approval` | Source changed while the old version waits for approval | Stays open. Approve or reject it first |
| `reversed_locally` | Reversed here, still in the source | Posts it again |
| `rejected_locally` | Rejected by a checker, still in the source | Posts it again, subject to approval rules |
| `missing_in_source` | Gone from Tally's Day Book. Tally may have deleted it, or the wrong company may be open | Reverses it here |

Changed vouchers are reversed on their original date and posted again, because journals are never edited.
