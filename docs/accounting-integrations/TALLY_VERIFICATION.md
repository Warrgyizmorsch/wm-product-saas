# Tally verification checklist

Nothing in this kit has run against a real TallyPrime yet. The automated tests use XML written from Tally's published samples, so they prove our logic and not Tally's behaviour. Use a **test company** for all of this.

Set `"auto_sync_minutes": 0` on the connection while testing.

## Checks that protect your books

Do these first. If one fails, don't use the kit on real data until it's fixed.

| # | Check | How | Why it matters |
|---|---|---|---|
| 1 | **Re-sending a voucher with the same REMOTEID doesn't duplicate it** | Post the same voucher XML to Tally twice, then look at the Day Book | If the relay crashes after Tally accepts a voucher, the job is sent again. Tally's official pages don't document this behaviour. It comes from a community answer only |
| 2 | **Debit and credit land on the right side** | Push one payment from the ERP and compare it in Tally. Pull one voucher and compare it in the ERP | Tally's doc says `ISDEEMEDPOSITIVE=Yes` means debit, and its sample pairs that with a negative amount. The kit follows the sample. A mistake here reverses every entry |
| 3 | **A pushed voucher isn't imported back** | Push a voucher, run an inbound sync, and check no second journal appears | Otherwise every entry doubles. The kit checks REMOTEID, then number and date |
| 4 | **Trial balances agree** | After a one-month sync in each direction, compare the two trial balances | The overall proof that the mapping is right |

## Checks on the format

| # | Check | If it fails |
|---|---|---|
| 5 | Pulling groups returns each group with `NAME`, `GUID` and `PARENT` | Adjust `TallyXmlBuilder::pullCollection` and the parser. The collection request is standard practice but isn't in Tally's samples word for word |
| 6 | Pulling ledgers returns `NAME`, `GUID` and `PARENT` | Same as above |
| 7 | The Day Book pull respects the date range and returns `GUID` and ledger lines | Check the `SVFROMDATE` format. We send `1-Apr-2026`, from Tally's example |
| 8 | The Day Book pull includes vouchers with no ledger lines of their own, such as item invoices with amounts under inventory | Adjust `TallyXmlParser::lines` |
| 9 | Pushed vouchers are accepted | Set `voucher_entries_tag` to `ALLLEDGERENTRIES.LIST`. Tally's sample uses `LEDGERENTRIES.LIST` |
| 10 | Pushing a ledger that already exists is treated as success | The kit looks for "already exists" or "duplicate" in Tally's error. Adjust `TallySyncService::completePush` to Tally's real wording |
| 11 | Predefined group names match `TallyGroupMap` | Add any spelling differences to the map, or a ledger fails with `unmapped_group` |
| 12 | Non-English or special characters survive both ways | Check the relay's encoding handling |

## Checks on behaviour

| # | Check | Expected |
|---|---|---|
| 13 | Alter a voucher's amount in Tally, then sync | ERP reverses the old entry and posts the new amount |
| 14 | Cancel a voucher in Tally, then sync | ERP reverses it, if Tally exports `ISCANCELLED` |
| 15 | Delete a voucher in Tally, then sync | A `missing_in_source` conflict appears, and nothing is reversed |
| 16 | Open the wrong company, or none, then sync | Tally returns an error or nothing. Vouchers aren't reversed, though a different company's data would raise `missing_in_source` conflicts. Check `external_company` is honoured |
| 17 | Stop Tally, then run the relay | Jobs retry three times, then show as failed. The relay keeps running |
| 18 | Run a real month's volume | Note the time taken and whether PHP limits were hit |

## Useful for capturing real samples

Post a job's XML straight to Tally to see the raw reply:

```powershell
Invoke-WebRequest -Uri http://localhost:9000 -Method Post -ContentType 'text/xml; charset=utf-8' -InFile request.xml -OutFile reply.xml
```

Tally also writes a log named `Tally.imp` in its install folder when importing.

Please save real replies (with a test company's data only) as test fixtures in `tests/Feature/Accounting`, replacing the hand-written XML. That turns this checklist into permanent tests.

## Sources

- [Tally XML integration](https://help.tallysolutions.com/xml-integration/)
- [Tally sample XML](https://help.tallysolutions.com/sample-xml/)
- [Understanding Tally XML tags](https://help.tallysolutions.com/understanding-tally-xml-tags/)
- [Tally import and export FAQ](https://help.tallysolutions.com/docs/faqs/faq/faq-import-and-export.htm)
