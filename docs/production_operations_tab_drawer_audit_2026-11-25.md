# Operations tab — the arrow drawer: audit and gaps (2026-11-25)

**Update (M-19, same day): the user gave the go-ahead ("implement all the fixes") — every gap below was then implemented; see section 12 of `docs/production_execution_rules_draft.md` for what was done and where to check it in the UI. Code written, not tested (the user's rule), not Verified. The text below is the original research.**

**Status when written: research only — nothing was implemented.** The user said: *"Impliment the same drawer system in here, and there will be a few gaps also. Go ahead and document it and tell me before implementing anything. We will check out these gaps and we will implement all the changes together … No change has to be implemented yet in this round of research."* (M-18 in `docs/production_readiness_user_messages.md`).

How this audit was done: code reading, and **read-only SELECT queries** on the real database (nothing written). None of the new readiness / drawer code was executed and no tests were run (the user's rule). Where a statement comes from reading code and not from running it, it says so.

The example used throughout is the order from the user's screenshot: **Order #7 — ORD-2026-000006**, status `draft`, quantity 8.

---

## 1. What the tab does today ("Operations" tab, `vtab-operations`, `orders/show.blade.php` ≈ L1580–1871)

One flat table, one row per operation of the order, **all levels mixed**:

| Column | Today |
| :--- | :--- |
| SEQ | the row position (`$loop->iteration` 1, 2, 3 …) — not the operation's own sequence |
| Operation | a badge "product (Level n)" for sub-assembly operations, the name, the operation number, QC / subcontract / challan badges |
| Work center | name (or vendor for subcontract) |
| Machine | `$op->machine->name ?? 'Any'` |
| Operator | assigned operator + Assign / Reassign buttons |
| Planned setup / run | raw minutes: `{{ $op->setup_time_planned }}m / {{ $op->processing_time_planned }}m` |
| Actual setup / run | raw minutes |
| Produced / Scrap | `number_format(…, 2)` |
| Status | the **saved** status badge (Waiting / Ready / Running …) |
| Log | "Log progress" button (opens the log modal with that operation selected) |

Sorting: planned start (when scheduled) → sub-assembly operations before the finished-good ones → sequence. A draft order has no schedule, so the rows are ordered by sequence only: **that is why the screenshot interleaves the levels** (L3 op1, L2 op1, L3 op2, L2 op2 …).

## 2. The real example — Order #7 (read-only data)

| Level | Product made | Operations | Target | Resources |
| :--- | :--- | :--- | :--- | :--- |
| L1 | test-final-01 (the order) | ops 22, 23, 24 | 8 | op22 manual batch 31 · op23 machine 44 · op24 manual batch 34 |
| L2 | test-semifinal-01 | ops 25, 26, 27, 28 | **339** (needs 440, 101 from stock) | op25 manual batch 30 · machines 54, 45, 49 |
| L3 | test-semi-01 | ops 29, 30, 31, 32 | **7,357** (needs 7,458, 101 from stock) | op29 manual batch 30 · machines 54, 45, 49 |

* Who waits for whom (saved dependencies): op23←op22, op24←op23 (inside L1); op26←op25 … (inside L2); op30←op29 … (inside L3); and **cross-assembly: op22 waits for op28, op25 waits for op32.**
* So the tree is: **op22** (L1 op1) → drawer "makes test-semifinal-01" → ops 25–28; **op25** (the first of them) → drawer "makes test-semi-01" → ops 29–32.
* Operation names repeat on every level (op1 … op4 exist three times; operation number OP-010 exists three times).
* The big planned run times are real, not a bug: op29 = 7,357 units × 2.4 min = **17,656.8 min** (294 h 16 m 48 s). All of L3 ≈ 30,300 min ≈ 505 h; the whole order ≈ 31,700 min ≈ 529 h.
* All 11 saved operation statuses are `waiting` (the order is a draft, nothing was ever reconciled).

## 3. How the drawer would look on this tab (proposal, not built)

```
SEQ   OPERATION                                   WORK CENTER   RESOURCES ...                STATUS
 ▸ 1  op1  OP-010                                 Fabrication   test-batch-31 (manual batch)  Waiting for sub-assembly test-semifinal-01 · 0 of 4 done
   2  op12 OP-020                                 Fabrication   machine 44                    Waiting for Op 10
   3  op1 3 OP-030                                Fabrication   test-batch-34 (manual batch)  Waiting for Op 20

 ▾ 1  op1  OP-010                         (arrow clicked)
      Makes test-semifinal-01 — to make 339 (440 needed, 101 from stock) · 0 of 4 operations done
      ▸ 1.1  op1  OP-010   test-batch-30 (manual batch)   Waiting for sub-assembly test-semi-01 · 0 of 4 done
        1.2  op2  OP-020   machine 54                       Waiting for Op 10
        1.3  op3  OP-030 …
        1.4  op4  OP-040 …
            (arrow on 1.1 → "Makes test-semi-01 — to make 7,357 …" → 1.1.1 … 1.1.4)
```
Same arrow, same recursion and same "parent always waits" rule as the Component Plan and Readiness drawers (`OperationMaterialService::operationTree()`), with this tab's own columns inside the nested table.

---

## 4. Gaps

Recommendation first in every row. **Decisions are the user's** — nothing below is done.

### A. This tab itself (OT)

| ID | Gap | Evidence | Options |
| :--- | :--- | :--- | :--- |
| **OT-01** | **The work that can actually run is hidden.** A parent waits for its sub-assembly, so the operations that are ready / running now are exactly the ones inside the closed drawers. On an execution list, "closed by default" hides them. | by the rule (M-17) every parent with makers is waiting | **(a)** closed by default + a badge on the closed row ("2 ready inside", "1 running inside") + auto-open a drawer that contains a running operation · (b) always closed + only the "x of y done" badge · (c) always open on this tab |
| **OT-02** | **SEQ is just a counter and names repeat.** Rows read 1, 2, 3 … and "op1 / OP-010" appears on three levels. | screenshot; Order #7 data | **(a)** hierarchical numbers 1, 1.1, 1.1.1 (as the BOM tree tab already does: 1.1, 1.2) and the product made shown on the group header · (b) show each operation's own sequence |
| **OT-03** | **Sorting does not follow the process.** Planned start first, then sequence — on a draft the levels interleave. | screenshot | **(a)** top level by sequence, inside a drawer by sequence (planned start only as information) · (b) keep planned start |
| **OT-04** | **"Machine" shows "Any" for manual-batch operations** (and for really unassigned ones); heading still "Machine". Order #7: op25 and op29 run on batch 30, both show "Any"; the Component Plan tab already names the batch. | `show.blade.php` L1728; screenshot rows 1–2 | **(a)** same "Resources" column as the Readiness table (name + machine / manual batch + ready / busy / booked); "Not assigned" only when really so · (b) just add the batch name |
| **OT-05** | **Status says what is saved, not why.** "Waiting" does not say for what; on a draft all 11 operations show a stale `waiting`. | all 11 ops of Order #7 are `waiting` while the order is draft | **(a)** keep the badge and add the Operation Flow line under it ("Waiting for sub-assembly test-semifinal-01 · 0 of 4 done", "Waiting for Op 10"); for a draft show "Draft — not released" · (b) badge only |
| **OT-06** | **Log button and log modal ignore the waiting rule.** The button shows for every open internal operation of a released order, so an operator can open the modal for a waiting parent, fill it in, and only then be refused (the server now refuses). The modal's operation list holds every not-completed operation with labels like `OP-010 — op1` three times. The modal's own numbers show `0.00`. | `show.blade.php` L1855, L3678–3684, L3692+ | **(a)** disable the button with the reason as tooltip; the modal lists only operations that can take a log, labelled with product / level (e.g. "OP-010 — op1 · test-semi-01 (L3)"); numbers via `qty_format` · (b) leave and rely on the server message |
| **OT-07** | **Numbers and time are not formatted by the project's own rules.** Produced / Scrap uses `number_format(…, 2)` ("0.00 / 0.00"); planned run prints "17656.8m". The helper `format_duration()` exists (17656.8 → "294h 16m 48s"). | `show.blade.php` L1803, L1811, L1816–1819; `app/helpers.php:528` | **(a)** `qty_format` for quantities and `format_duration` for minutes on this tab (and the log modal) · (b) leave |
| **OT-08** | **The header note is now wrong.** "Operations must be processed sequentially." | lang `ops_sequential_note` | change to "An operation waits for the previous operation and for the operations that make its sub-assemblies" |
| **OT-09** | **Level badge and controls move with the row.** The "test-semi-01 (Level 3)" badge on every row becomes redundant inside a drawer; the Assign / Subcontract / challan controls move with each row. | `show.blade.php` L1636–1712, L1731–1797, L1849–1864 | badge → group header ("Makes …"); controls stay in each row, unchanged |
| **OT-10** | **Counters count every operation.** The Component Plan summary (`$cTotalOps` etc.), the production dashboard ("Active operations" shows the first 3 operations of the order — in the order's children-first sort, so sub-assembly operations come first) and the orders list. | `show.blade.php` L1906–1909; `dashboard.blade.php` L2680 | keep counting all, label "incl. sub-assembly operations"; dashboard: show top-level operations |

### B. The sub-assembly logic behind the drawer (SA)

| ID | Gap | Evidence | Options |
| :--- | :--- | :--- | :--- |
| **SA-01** | **ENGINE BUG (order-dependent): a requirement is lost when one sub-assembly is needed at two levels.** On Order #7, test-semi-02 is needed **32** by op22 (L1) and **18,645** by op26 (L2) = **18,677**; the store has 101; the order reserved **101** and created **no operations** (and no raw-material plan) for the other **18,576**. So there is no drawer and no request for it. | data above; code: `ProductionOrderEngineeringService::computeMultiLevelPlan` L712–727 builds the level batch as a copy, then for each child BOM reads `$entry['queued']` from that stale copy and sets `$pending[$childBomId]['queued'] = 0.0`. test-semifinal-01 (BOM 16) is processed first and queues 18,645 more for BOM 11 (test-semi-02); BOM 11 is next in the same batch, reads its old 32, then zeroes the queue — the 18,645 vanish. **From reading the code and comparing it with the data; I did not run the engine.** | **(a)** fix the engine (read the queue when the BOM is processed; process a BOM once all its requesting levels are done) and re-create Order #7 · (b) leave — not recommended: shortages disappear silently |
| **SA-02** | **Sub-assemblies taken from stock are not checked by the Material column.** (UR-01 judges direct materials only.) Order #7 plans test-semifinal-01 101, test-semi-02 101, test-semi-01 101 and test-semifinal-02 40 from stock — none issued, none checked. A parent can read Material "Ready" while those are not issued. | `OperationMaterialService::resolve`; UR-01 scope | the user gives the child-BOM rule later (as announced); until then it stays open — options then: (a) treat the stock part like a material that must be issued · (b) ignore |
| **SA-03** | **One sub-assembly made for two parents.** The makers are listed in the drawer of the first parent (lowest level, then sequence); the other drawer shows "Its operations are listed under OPxx". Both parents wait for all of them. | `OperationMaterialService::operationTree` | confirm, or list the makers in both |
| **SA-04** | **How the made quantity reaches the parent** after the makers complete (WIP transfer / issue to the order) and what limits the parent then — not audited in this round. The parent's quantity after completion still uses issued + store stock + produced − consumed, divided by the BOM quantity. | `ProductionReadinessService::evaluateDependencyDimension` (completed branch) | audit together with scheduling / execution |

### C. Other screens that list operations (OS)

| ID | Screen | Today | Suggestion |
| :--- | :--- | :--- | :--- |
| OS-01 | Component Plan — matrix | **done** (drawer) | — |
| OS-02 | Component Plan — hierarchical BOM tree tab | flat, all operations, children first | nest the same way, or leave |
| OS-03 | Readiness table | **done** (drawer) | — |
| OS-04 | Operations tab | this audit | section 3 |
| OS-05 | Scrap / Rework forms, progress-log lists | operation selects / rows labelled "Op OP-010 — op1" | add product / level to the label (same ambiguity as OT-06) |
| OS-06 | Subcontract tab, WIP tab | flat lists of external / WIP operations | leave |
| OS-07 | MES dashboard, My Operations, operation execution | flat — correct for operators (they need what can run) | add a tag "sub-assembly of OPxx"; show "Waiting for sub-assembly" instead of a Start button |
| OS-08 | Schedule detail, calendar, dispatch board, work-center view | flat, scheduled operations | belongs to the scheduling step (section D) |
| OS-09 | API / exports / print | not audited in detail | check when the changes are decided |

### D. Scheduling — next step, listed so nothing is forgotten (SD)

| ID | Gap | Evidence |
| :--- | :--- | :--- |
| SD-01 | The scheduler may plan a parent to start **before** its makers finish: for every predecessor with overlap / queue threshold it uses "start + first transfer batch" instead of the finish. UR-07 says finish-to-start only. | `SchedulingService::calculateEarliestStartFromPredecessors` L2019–2110 (code reading) |
| SD-02 | Only the makers' **last** operation is stored as the parent's predecessor (op22←op28, op25←op32); the parent's earliest start should be after the latest finish of **all** makers. | dependency rows of Order #7 |
| SD-03 | Placing and prioritising the makers ahead of the parent (PX-22) — the user will explain; and the capacity those makers need (Order #7 ≈ 529 h) | M-06, M-17 |

### E. Leftovers of the work already done (PW)

| ID | Gap |
| :--- | :--- |
| PW-01 | The Component Plan drawer now shows sub-assemblies twice: the old "Sub-assemblies" list under Material Inputs and the new "Sub-assembly operations" block. |
| PW-02 | Draft orders: stale saved statuses and the all-red Readiness table (G-R3 / G-R4) are still open. |
| PW-03 | Page load now recalculates readiness and the drawer trees; `OperationMaterialService::resolve()` is called many times per operation. For Order #7 (11 operations) that is roughly a hundred and fifty calls, each with several queries — **an estimate from reading the code, not measured**. A per-request cache would remove most of it. |
| PW-04 | New wording exists in the English language file only (other languages fall back to English). |
| PW-05 | The tests I changed for the new rules were not run (the user's rule). |

---

## 5. Changes this would lead to (nothing done)

If the user agrees: partial `operations-row` for this tab (recursive, like `operation-tracking-row`), the tree already passed by the controller (`$opTree`), Resources / Operation Flow / formatting reuse from the Readiness table, log button + modal list (OT-06), lang keys, and — separately — the engine fix SA-01 in `ProductionOrderEngineeringService::computeMultiLevelPlan`.

## 6. Decisions needed from the user

1. **OT-01** — drawer default on this tab: auto-open when something inside is running, with a "ready inside" badge (recommended) / always closed / always open.
2. **OT-02** — numbering 1, 1.1, 1.1.1 (recommended) or own sequence.
3. **OT-06** — disable the Log button with the reason and list only operations that can take a log (recommended) or leave it to the server message.
4. **OT-04 / OT-05 / OT-07** — use the new words (Resources, Operation Flow line) and the number / time formats on this tab too (recommended).
5. **SA-01** — fix the engine in the same batch (recommended) and re-create Order #7.
6. **SA-02** — rule for sub-assemblies taken from stock: later, with the child-BOM rules.
7. **OS-02 / OS-05 / OS-07** — which of the other screens to include now.
