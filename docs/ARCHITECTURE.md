# Returns & Warranty -- Integration Architecture

How the **Customer Returns** module (`customerreturn`) and the **WarrantySvc / RMA** module
(`warrantysvc`) fit together, who owns stock, and where lot identity lives.

Scope: the seam between the two modules. Each module's own internals are in its `docs/TECHNICAL.md`.

- Customer Returns: `doli-returns/docs/TECHNICAL.md`
- WarrantySvc: `RMA-Module/docs/TECHNICAL.md`

Verified against **Dolibarr 22.0.4**.

---

## The two modules

| | WarrantySvc (`warrantysvc`) | Customer Returns (`customerreturn`) |
|---|---|---|
| Owns | The RMA workflow — the case, the warranty, the service history | The inbound goods movement and the credit note |
| Key object | `SvcRequest` (`llx_svc_request`) | `CustomerReturn` (`llx_customer_return`) |
| Tracks | `serial_number` (the unit the case is about) | One line per returned unit, each with `serial_number` |
| Moves stock | Only via core `Reception` on Path B (non-functional on v22) | **Yes** — `validate()` adds, `reopen()` reverses |

The warranty module drives the workflow; the returns module executes the stock side of it.

---

## Inbound paths

There are two ways returned goods can come back in. They are **alternatives, not complements** —
`WARRANTYSVC_USE_CUSTOMERRETURN` selects between them.

### Path A — Customer Return (toggle on, supported)

```
SvcRequest  --creates-->  CustomerReturn  --validate()-->  MouvementStock::reception()
                                          --reopen()---->  MouvementStock::livraison()
```

- Entered from the SR card (`createcustomerreturn`), which links the two via `element_element`
  (`warrantysvc_svcrequest` -> `customerreturn_customerreturn`).
- Stock movements carry `origintype = 'customerreturn'`, `fk_origin = <return id>`.
- **Lot-aware.** Both directions name the serial. See *Lot identity* below.
- On validate, the WarrantySvc trigger picks up `CUSTOMERRETURN_CUSTOMERRETURN_VALIDATE`, sets
  `date_return_received` on the SR and advances Await Return -> In Progress.

### Path B — core Reception (toggle off, **non-functional on v22**)

```
SvcRequest  --createReturnReception()-->  Reception  --validateReception()-->  Reception::valid()
```

This path does not work on Dolibarr 22 and should not be relied on. Two independent reasons:

1. **The INSERT fails.** `createReturnReception()` inserts into `llx_receptiondet_batch` using
   `fk_commandefourndet`. That column was renamed to `fk_elementdet` (plus `element_type`) in v22, so
   the statement errors with `Unknown column 'fk_commandefourndet' in 'INSERT INTO'`.

2. **Even fixed, it would move no stock.** Every stock path in core's `Reception` inner-joins the
   supplier-order line table:

   ```sql
   FROM commande_fournisseurdet as cd, receptiondet_batch as ed
   WHERE ed.fk_reception = <id> AND cd.rowid = ed.fk_elementdet
   ```

   (`reception.class.php` lines 618 `valid`, 1129 `delete`, 1733 `setClosed`, 1892, 2027.)
   `createReturnReception()` deliberately builds lines with **no source PO**, so those lines match no
   `commande_fournisseurdet` row and are skipped by all of them. The whole block is additionally gated
   on `STOCK_CALCULATE_ON_RECEPTION`.

   A customer return has no purchase order by nature, so this is structural, not a bug to patch.

3. It also never sets `batch`, so it is lot-blind even in principle.

> **Consequence:** with the toggle off, an RMA has no working route to put returned stock back.
> Keep `WARRANTYSVC_USE_CUSTOMERRETURN` enabled.

### Double-add risk

Nothing prevents an SR from having **both** a linked `CustomerReturn` and an `fk_reception`. The SR card
renders the customer return if present and falls back to the reception otherwise, so the second one is
invisible in the UI while still existing in the database. If Path B is ever repaired, the two paths must
become mutually exclusive per SR.

---

## Lot identity

This is the load-bearing invariant of the integration.

The RMA can track a unit **in** and a unit **out** separately, since they are sometimes different
physical units (a repaired unit swapped for a refurbished one):

| Field | Meaning | Populated in production? |
|---|---|---|
| `svc_request.serial_number` | The unit the case is about | **Yes** — this is the working field |
| `svc_request.serial_in` | Serial the customer sent back (swap flows) | No — NULL on every SR |
| `svc_request.serial_out` | Serial shipped back to the customer (swap flows) | No — NULL on every SR |

In practice the chain that carries lot identity is:

```
svc_request.serial_number  ->  customer_return_line.serial_number  ->  stock_mouvement.batch
```

`serial_in` / `serial_out` are reserved for cross-ship swap resolutions and are currently unused, so do
not treat them as the source of truth. Likewise `fk_warehouse_return` is NULL on every SR; the
destination warehouse comes from the `WARRANTYSVC_WAREHOUSE_RETURN` global.

Warranty coverage is bound to a serial (`SvcWarranty::fetchBySerial()`), so the serial on a record *is*
the claim to a specific physical unit. Any stock operation that moves "one of this product" rather than
"this serial" silently rewrites which unit the history describes.

The returns module therefore names the lot on **both** directions, and refuses rather than approximating:

- `validate()` -> `MouvementStock::reception()` with the line's `serial_number`.
- `reopen()` -> `MouvementStock::livraison()` with the same serial, after checking it is still on hand.
- If the serial has already shipped back out, reopen is **refused**. The unit has physically left; the
  movement is not reversible without claiming stock that is not there.

Details and the param-order footgun are in `doli-returns/docs/TECHNICAL.md` -> *Stock Integrity*.

---

## Who may write stock movements

| `origintype` | Written by | Reversed by |
|---|---|---|
| `customerreturn` | `CustomerReturn::validate()` | `CustomerReturn::reopen()` |
| `shipping` | core `Expedition` | core |
| `reception` | core `Reception` | core (unreachable for RMA returns — see Path B) |

The returns module is the **sole** author of `origintype = 'customerreturn'` movements. That is what
makes its balance invariant complete: summing those movements for a given `fk_origin` fully accounts for
the stock that return is responsible for. WarrantySvc never writes them, and never calls
`CustomerReturn::validate()/reopen()/delete()` programmatically — it only fetches a return for display
and links by URL.

---

## Linking

`element_element` rows use **prefixed** element types for custom modules:

| Source | Target | Meaning |
|---|---|---|
| `warrantysvc_svcrequest` | `customerreturn_customerreturn` | RMA case -> its return |
| `shipping` | `customerreturn_customerreturn` | Outbound shipment -> the return against it |
| `commande` | `warrantysvc_svcrequest` | Sales order -> RMA case |

Both modules match on the prefixed **and** bare forms when cleaning up, because older rows used the bare
type. `CustomerReturn::delete()` removes both.

---

## Triggers across the seam

| Trigger | Fired by | Handled by | Effect |
|---|---|---|---|
| `CUSTOMERRETURN_CUSTOMERRETURN_VALIDATE` | Returns | WarrantySvc | Sets `date_return_received`, advances SR Await Return -> In Progress (requires warrantysvc >= 1.32.5; before that `setInProgress()` rejected the transition and the trigger discarded the error) |
| `CUSTOMERRETURN_CUSTOMERRETURN_REOPEN` | Returns | **nobody** | See below |

**Known asymmetry.** WarrantySvc advances the SR when a return is validated, but has no `REOPEN`
handler. Reopening a return reverses the stock while leaving the SR advanced, still carrying a
`date_return_received` for goods that are no longer in the warehouse. The returns module's lot guard
narrows how often this is reachable (reopen is refused once the unit has shipped back out) but does not
close it. Fixing it means adding the inverse handler in WarrantySvc.

---

## Production state (verified 2026-08-31)

| Check | Value |
|---|---|
| `WARRANTYSVC_USE_CUSTOMERRETURN` | `1` — Path A active |
| `WARRANTYSVC_WAREHOUSE_RETURN` | `1` (Front Office) |
| `WARRANTYSVC_WARRANTY_REQUIRES_LOTS` | `1` |
| `svc_request.fk_reception` | NULL on all 10 SRs — **Path B has never been used** |
| `STOCK_DISALLOW_NEGATIVE_TRANSFER` | unset — core does not block negative lots |

Path B being dormant is why its breakage has never surfaced in day-to-day use.

---

## Configuration

| Constant | Module | Effect |
|---|---|---|
| `WARRANTYSVC_USE_CUSTOMERRETURN` | WarrantySvc | Selects Path A over Path B. **Keep enabled** — Path B is non-functional on v22. |
| `WARRANTYSVC_WAREHOUSE_REFURB` | WarrantySvc | Source warehouse for replacement units |
| `CUSTOMERRETURN_DEFAULT_WAREHOUSE` | Returns | Fallback destination when a line names none |
| `CUSTOMERRETURN_DEBUG_MODE` | Returns | Enables `ajax/debug.php`, including `?mode=stock` reconciliation |
| `STOCK_DISALLOW_NEGATIVE_TRANSFER` | Dolibarr core | Off by default. When off, core does **not** block a lot going negative, which is why the returns module checks lot availability itself. |

---

## Diagnosing the seam

With `CUSTOMERRETURN_DEBUG_MODE` on:

| Question | Where |
|---|---|
| What is this return linked to, and what stock does it hold? | `ajax/debug.php?mode=object&id=<id>` |
| Is any stock stranded by a deleted return? | `ajax/debug.php?mode=stock` |
| All cross-module links | `ajax/debug.php?mode=links` |

`?mode=stock` reports the **historical** net of orphaned movements alongside each affected batch's
**current** qty. Movement history is immutable, so a non-zero historical net stays on the books forever;
only the current qty distinguishes a live problem from one already corrected.
