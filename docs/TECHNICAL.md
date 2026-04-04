# Customer Return -- Technical Reference

Module version: **2.2.1** | Module number: **510100** | Family: `crm` | Position: `90`

Requires: Dolibarr >= 16.0, PHP >= 7.0, modules `modSociete`, `modProduct`, `modStock`.

Picto: `dollyrevert`

---

## Pages & URL Parameters

### `customerreturn_card.php` -- Card (create / view / edit)

Main page for creating, viewing, editing, validating, closing, reopening, and deleting a customer return. Also handles credit note generation.

| Parameter | Type | Context | Description |
|---|---|---|---|
| `id` | int | GET | Load return by rowid |
| `ref` | string | GET | Load return by reference |
| `action` | string | GET/POST | Current action (see below) |
| `cancel` | string | POST | Cancel current action |
| `confirm` | string | POST | Confirmation flag (`yes`) |
| `backtopage` | string | GET/POST | URL to redirect to on cancel |
| `fk_soc` | int | POST | Customer (third party) ID |
| `fk_expedition` | int | GET/POST | Source shipment ID (triggers Entry A flow) |
| `fk_warehouse` | int | POST | Default warehouse ID |
| `label` | string | POST | Return label |
| `return_datemonth`, `return_dateday`, `return_dateyear` | int | POST | Return date components |
| `note_public` | restricthtml | POST | Public note |
| `note_private` | restricthtml | POST | Private note |
| `toselect[]` | array of int | POST | Selected shipment line IDs |
| `return_qty_{lineId}` | int | POST | Quantity to return per line |
| `fk_product_{lineId}` | int | POST | Product ID per line (hidden) |
| `serial_number_{lineId}` | string | POST | Batch/serial per line (hidden) |
| `product_label_{lineId}` | string | POST | Product description per line (hidden) |
| `fk_commandedet_{lineId}` | int | POST | Source order line ID per line (hidden) |
| `fk_entrepot_{lineId}` | int | POST | Warehouse per line |
| `comment_{lineId}` | restricthtml | POST | Comment per line |
| `from_svcrequest` | int | GET/POST | Optional warrantysvc service request ID (cross-module integration) |

**Actions handled:**

| Action value | Trigger | Permission | Description |
|---|---|---|---|
| `create` | -- | write | Show creation form |
| `add` | POST | write | Insert new return + lines from selected shipment lines |
| `edit` | GET | write | Show edit form (header only; lines are read-only after creation) |
| `update` | POST | write | Save edited header fields |
| `confirm_validate` | POST (confirm=yes) | validate | DRAFT -> VALIDATED; creates stock movements |
| `confirm_close` | POST (confirm=yes) | close | VALIDATED -> CLOSED |
| `confirm_reopen` | POST (confirm=yes) | validate | VALIDATED -> DRAFT |
| `confirm_createcreditnote` | POST (confirm=yes) | close | Create credit note from linked invoice |
| `confirm_delete` | POST (confirm=yes) | delete | Delete return + lines |
| `validate` | GET | -- | Show validate confirmation dialog |
| `close` | GET | -- | Show close confirmation dialog |
| `reopen` | GET | -- | Show reopen confirmation dialog |
| `delete` | GET | -- | Show delete confirmation dialog |
| `createcreditnote` | GET | -- | Show credit note confirmation dialog |

**Creation flows:**

- **Entry A** (`fk_expedition` provided): Shipment is pre-selected. Server-side renders the expeditiondet lines table with qty ordered, qty shipped, qty already returned, return qty input, warehouse selector, comment, and lot/serial. Checkboxes and qty inputs are synced via inline JavaScript.
- **Entry B** (no `fk_expedition`): Customer selector only. AJAX loads shipment list via `ajax/customer_shipments.php`. User clicks a shipment row to redirect back to Entry A.

**Hook contexts initialized:** `customerreturncard`, `globalcard`

---

### `customerreturn_list.php` -- List

Paginated, sortable, filterable list of all customer returns.

| Parameter | Type | Context | Description |
|---|---|---|---|
| `action` | string | POST | Set to `list` in form |
| `sortfield` | string | GET/POST | Sort column (default: `t.ref`) |
| `sortorder` | string | GET/POST | Sort direction (default: `DESC`) |
| `page` | int | GET/POST | Page number (0-based) |
| `limit` | int | GET/POST | Rows per page (default: global `$conf->liste_limit`) |
| `search_ref` | string | GET/POST | Filter by ref (natural search) |
| `search_label` | string | GET/POST | Filter by label (natural search) |
| `search_company` | string | GET/POST | Filter by customer name (natural search) |
| `search_note_public` | string | GET/POST | Filter by public note (natural search) |
| `search_status` | intcomma | GET/POST | Filter by status; supports comma-separated list |
| `search_date_start*` | int | POST | Date range start (month/day/year components) |
| `search_date_end*` | int | POST | Date range end (month/day/year components) |
| `button_removefilter` | string | POST | Clear all filters |

**Columns displayed:** Ref (linked), Label, Return Date, Customer (linked), Note (public, truncated), Status (badge).

**Permission required:** `customerreturn > customerreturn > read`

---

### `customerreturn_note.php` -- Notes Tab

View/edit public and private notes on an existing return.

| Parameter | Type | Context | Description |
|---|---|---|---|
| `id` | int | GET | Return ID |
| `ref` | string | GET | Return reference |
| `action` | string | POST | `update_note_public` or `update_note_private` |
| `note_public` | restricthtml | POST | Updated public note |
| `note_private` | restricthtml | POST | Updated private note |

Uses Dolibarr's standard `notes.tpl.php` template.

---

### `admin/setup.php` -- Module Configuration

Admin-only setup page.

| Parameter | Type | Context | Description |
|---|---|---|---|
| `action` | string | POST | `update` to save settings |
| `CUSTOMERRETURN_WAREHOUSE_DEFAULT` | int | POST | Default receiving warehouse ID |
| `CUSTOMERRETURN_DEBUG_MODE` | string | POST | Enable/disable debug endpoint (`1` or absent) |

Also displays the numbering model table, scanning `core/modules/customerreturn/mod_customerreturn_*.php`.

---

### `ajax/customer_shipments.php` -- AJAX: Customer Shipments

Returns JSON array of validated shipments (status >= 1) for a given customer.

| Parameter | Type | Context | Description |
|---|---|---|---|
| `socid` | int | GET | Customer third-party ID (required) |

**Response fields per shipment:** `id`, `ref`, `url`, `date`, `status_label`, `nb_lines`, `lines_summary`.

**Permission required:** `customerreturn > customerreturn > read`

---

### `ajax/shipment_lines.php` -- AJAX: Shipment Lines

Returns JSON array of shipment line items for a specific shipment, including quantities already returned.

| Parameter | Type | Context | Description |
|---|---|---|---|
| `expedition_id` | int | GET | Shipment ID (required) |

**Response fields per line:** `line_id`, `fk_product`, `product_ref`, `product_label`, `qty_ordered`, `qty_shipped`, `qty_already_returned`, `qty_returnable`, `serial_number`, `fk_commandedet`.

Already-returned quantities exclude returns in DRAFT status.

**Permission required:** `customerreturn > customerreturn > read`

---

### `ajax/debug.php` -- Debug Diagnostics

Admin-only diagnostic endpoint gated by `CUSTOMERRETURN_DEBUG_MODE` constant. Returns plain text.

| Parameter | Type | Context | Description |
|---|---|---|---|
| `mode` | string | GET | Diagnostic mode (default: `overview`) |
| `id` | int | GET | Return ID (for `mode=object`) |
| `q` | string | GET | Read-only SQL query (for `mode=sql`, SELECT only) |

**Modes:** `overview`, `object`, `links`, `settings`, `classes`, `sql`, `triggers`, `hooks`, `all`.

---

## Classes & Methods

### `CustomerReturn` (`class/customerreturn.class.php`)

Extends `CommonObject`. Main business object.

**Properties (DB-mapped):**

| Property | Type | Description |
|---|---|---|
| `ref` | string | Unique reference (e.g., `RT-2604-0001`) |
| `entity` | int | Multi-company entity |
| `fk_soc` | int | Customer third-party ID |
| `fk_commande` | int/null | Linked sales order ID |
| `fk_expedition` | int/null | Source shipment ID |
| `fk_project` | int/null | Linked project ID |
| `fk_warehouse` | int/null | Default warehouse ID |
| `label` | string/null | Free-text label |
| `return_date` | timestamp/null | Return date |
| `status` | int | 0=Draft, 1=Validated, 2=Closed |
| `note_private` | string/null | Private note |
| `note_public` | string/null | Public note |
| `date_creation` | timestamp | Creation timestamp |
| `date_validation` | timestamp/null | Validation timestamp |
| `date_closed` | timestamp/null | Close timestamp |
| `fk_user_creat` | int | Creator user ID |
| `fk_user_valid` | int/null | Validator user ID |
| `fk_user_close` | int/null | Closer user ID |
| `fk_user_modif` | int/null | Last modifier user ID |
| `import_key` | string/null | Import batch key |
| `model_pdf` | string/null | PDF template name |
| `last_main_doc` | string/null | Path to last generated document |
| `lines` | array | Array of `CustomerReturnLine` objects |

**Constants:**

| Constant | Value | Description |
|---|---|---|
| `STATUS_DRAFT` | 0 | Draft |
| `STATUS_VALIDATED` | 1 | Validated (stock received) |
| `STATUS_CLOSED` | 2 | Closed |

**Object metadata:**

| Property | Value |
|---|---|
| `TRIGGER_PREFIX` | `CUSTOMERRETURN` |
| `module` | `customerreturn` |
| `element` | `customerreturn` |
| `table_element` | `customer_return` |
| `table_element_line` | `customer_return_line` |
| `class_element_line` | `CustomerReturnLine` |
| `fk_element` | `fk_customer_return` |
| `table_ref_field` | `ref` |

**Methods:**

| Method | Signature | Description |
|---|---|---|
| `create` | `($user, $notrigger = 0): int` | Insert new return (DRAFT). Auto-generates ref via numbering module. Returns new ID or -1. Fires `CUSTOMERRETURN_CUSTOMERRETURN_CREATE`. |
| `fetch` | `($id, $ref = ''): int` | Load from DB by ID or ref. Auto-calls `fetchLines()`. Returns 1/0/-1. |
| `fetchLines` | `(): int` | Load all lines ordered by `rang ASC, rowid ASC`. |
| `update` | `($user, $notrigger = 0): int` | Update header fields. Fires `CUSTOMERRETURN_CUSTOMERRETURN_MODIFY`. |
| `delete` | `($user, $notrigger = 0): int` | Delete return + lines + extrafields + element_element links. Fires `CUSTOMERRETURN_CUSTOMERRETURN_DELETE`. |
| `validate` | `($user, $notrigger = 0): int` | DRAFT -> VALIDATED. Creates `MouvementStock::reception()` for each line with a product and warehouse. Fires `CUSTOMERRETURN_CUSTOMERRETURN_VALIDATE`. |
| `close` | `($user, $notrigger = 0): int` | VALIDATED -> CLOSED. Fires `CUSTOMERRETURN_CUSTOMERRETURN_CLOSE`. |
| `reopen` | `($user, $notrigger = 0): int` | VALIDATED -> DRAFT. Fires `CUSTOMERRETURN_CUSTOMERRETURN_REOPEN`. |
| `getNextNumRef` | `(): string` | Delegates to the configured numbering module (`CUSTOMERRETURN_ADDON`). |
| `getNomUrl` | `($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1): string` | Return HTML link to card page. |
| `getLibStatut` | `($mode = 0): string` | Return status badge HTML for current status. |
| `LibStatut` | `static ($status, $mode = 0): string` | Return status badge HTML for any given status value. |
| `addLine` | `($fk_product, $qty, $description, $serial_number, $subprice, $tva_tx, $fk_expedition, $fk_expeditiondet, $fk_commandedet, $fk_entrepot, $comment): int` | Add a line. Returns line ID or -1. |
| `updateLine` | `($lineid, $fk_product, $qty, $description, $serial_number, $subprice, $tva_tx): int` | Update an existing line. |
| `deleteLine` | `($lineid): int` | Delete a line by ID. |
| `getLinkedInvoice` | `(): int` | Traverse `element_element` (expedition -> commande -> facture) to find the linked invoice. Returns invoice ID or 0. |

---

### `CustomerReturnLine` (`class/customerreturnline.class.php`)

Extends `CommonObjectLine`. Line item for a customer return.

**Properties:**

| Property | Type | Description |
|---|---|---|
| `fk_customer_return` | int | Parent return ID |
| `fk_product` | int/null | Product ID |
| `description` | string/null | Line description |
| `qty` | float | Quantity returned |
| `serial_number` | string/null | Lot/batch/serial number |
| `fk_expedition` | int/null | Source shipment ID |
| `fk_expeditiondet` | int/null | Source shipment line ID |
| `fk_commandedet` | int/null | Source order line ID |
| `fk_entrepot` | int/null | Destination warehouse ID |
| `comment` | string/null | Line comment |
| `subprice` | float | Unit price |
| `total_ht` | float | Line total excl. tax |
| `tva_tx` | float | Tax rate |
| `rang` | int | Sort order |

**Object metadata:**

| Property | Value |
|---|---|
| `element` | `customerreturnline` |
| `table_element` | `customer_return_line` |
| `fk_element` | `fk_customer_return` |

**Methods:**

| Method | Returns | Description |
|---|---|---|
| `insert()` | int (line ID or -1) | Insert line into DB. Sets `date_creation`. |
| `update()` | int (1 or -1) | Update line fields (product, qty, description, serial, warehouse, comment, subprice, total_ht, tva_tx, rang). |
| `delete()` | int (1 or -1) | Delete line by `rowid`. |

---

### `ActionsCustomerreturn` (`class/actions_customerreturn.class.php`)

Hook actions class. Registered on contexts: `elementproperties`, `productcard`, `commonobject`, `expeditioncard`.

**Methods:**

| Method | Hook Point | Description |
|---|---|---|
| `getElementProperties` | `elementproperties` | Registers `customerreturn` element type so Dolibarr resolves it in linked objects and "Link to" dropdowns. Handles both `customerreturn` and `customerreturn_customerreturn` element types. |
| `showLinkToObjectBlock` | `commonobject` | Injects `customerreturn` into the "Link to..." dropdown on any native card page. Provides SQL to list returns filtered by company. |
| `formObjectOptions` | `commonobject` | On sales order creation (`commande` + `action=create`), injects hidden `origin` / `originid` fields when `customerreturn_source_id` is present, enabling SO creation from a return. |
| `doActions` | various | Stub for custom action handling (currently no-op). |
| `addMoreActionsButtons` | `expeditioncard` | Adds "Create Return" button on shipment cards (status >= 1). Links to `customerreturn_card.php?action=create&fk_expedition={id}`. |

---

## Hooks

Registered in module descriptor `module_parts['hooks']`:

| Context | Description |
|---|---|
| `elementproperties` | Element type resolution for linked objects |
| `productcard` | Product card page |
| `commonobject` | All CommonObject pages (Link to dropdown, formObjectOptions) |
| `expeditioncard` | Shipment card page (Create Return button) |

Additionally, `customerreturn_card.php` initializes hooks for contexts `customerreturncard` and `globalcard`.

---

## Triggers

**File:** `core/triggers/interface_99_modCustomerreturn_CustomerreturnTrigger.class.php`

**Class:** `InterfaceCustomerreturnTrigger` (extends `DolibarrTriggers`)

| Event Code | Description |
|---|---|
| `CUSTOMERRETURN_CUSTOMERRETURN_CREATE` | Fired after a return is created. Logs to syslog. |
| `CUSTOMERRETURN_CUSTOMERRETURN_VALIDATE` | Fired after a return is validated. Logs to syslog. |
| `CUSTOMERRETURN_CUSTOMERRETURN_CLOSE` | Fired after a return is closed. Logs to syslog. |
| `CUSTOMERRETURN_CUSTOMERRETURN_REOPEN` | Fired after a return is reopened. Logs to syslog. |
| `ORDER_CREATE` | Listens for native sales order creation. If `origin` is `customerreturn_customerreturn`, calls `_linkOrderToReturn()` to insert a bidirectional link in `llx_element_element` (sourcetype=`customerreturn`, targettype=`commande`). |

---

## Database Schema

### `llx_customer_return`

Main return header table.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `rowid` | INTEGER | NO | auto_increment | Primary key |
| `ref` | VARCHAR(30) | NO | -- | Unique reference |
| `entity` | INTEGER | NO | 1 | Multi-company entity |
| `fk_soc` | INTEGER | NO | -- | Customer third-party ID |
| `fk_commande` | INTEGER | YES | NULL | Linked sales order |
| `fk_expedition` | INTEGER | YES | NULL | Source shipment |
| `fk_project` | INTEGER | YES | NULL | Linked project |
| `fk_warehouse` | INTEGER | YES | NULL | Default warehouse |
| `label` | VARCHAR(255) | YES | NULL | Free-text label |
| `return_date` | DATETIME | YES | NULL | Return date |
| `status` | SMALLINT | NO | 0 | 0=Draft, 1=Validated, 2=Closed |
| `note_private` | TEXT | YES | NULL | Private note |
| `note_public` | TEXT | YES | NULL | Public note |
| `date_creation` | DATETIME | NO | -- | Creation timestamp |
| `date_validation` | DATETIME | YES | NULL | Validation timestamp |
| `date_closed` | DATETIME | YES | NULL | Close timestamp |
| `tms` | TIMESTAMP | YES | CURRENT_TIMESTAMP (auto-update) | Last modification |
| `fk_user_creat` | INTEGER | YES | NULL | Creator user |
| `fk_user_valid` | INTEGER | YES | NULL | Validator user |
| `fk_user_close` | INTEGER | YES | NULL | Closer user |
| `fk_user_modif` | INTEGER | YES | NULL | Last modifier user |
| `import_key` | VARCHAR(14) | YES | NULL | Import batch key |
| `model_pdf` | VARCHAR(255) | YES | NULL | PDF template |
| `last_main_doc` | VARCHAR(255) | YES | NULL | Last generated doc path |

**Indexes (`llx_customer_return.key.sql`):**

| Index | Type | Columns |
|---|---|---|
| `uk_customer_return_ref` | UNIQUE | `ref, entity` |
| `idx_customer_return_fk_soc` | INDEX | `fk_soc` |
| `idx_customer_return_status` | INDEX | `status` |
| `idx_customer_return_fk_expedition` | INDEX | `fk_expedition` |
| `idx_customer_return_fk_commande` | INDEX | `fk_commande` |
| `idx_customer_return_fk_project` | INDEX | `fk_project` |

---

### `llx_customer_return_line`

Return line items.

| Column | Type | Nullable | Default | Description |
|---|---|---|---|---|
| `rowid` | INTEGER | NO | auto_increment | Primary key |
| `fk_customer_return` | INTEGER | NO | -- | Parent return ID |
| `fk_product` | INTEGER | YES | NULL | Product ID |
| `description` | TEXT | YES | NULL | Line description |
| `qty` | DOUBLE | NO | 1 | Quantity returned |
| `serial_number` | VARCHAR(128) | YES | NULL | Lot/batch/serial |
| `fk_expedition` | INTEGER | YES | NULL | Source shipment ID |
| `fk_expeditiondet` | INTEGER | YES | NULL | Source shipment line ID |
| `fk_commandedet` | INTEGER | YES | NULL | Source order line ID |
| `fk_entrepot` | INTEGER | YES | NULL | Destination warehouse |
| `comment` | TEXT | YES | NULL | Line comment |
| `subprice` | DOUBLE(24,8) | YES | 0 | Unit price |
| `total_ht` | DOUBLE(24,8) | YES | 0 | Total excl. tax |
| `tva_tx` | DOUBLE(7,4) | YES | 0 | Tax rate |
| `rang` | INTEGER | YES | 0 | Sort order |
| `date_creation` | DATETIME | YES | NULL | Creation timestamp |
| `tms` | TIMESTAMP | YES | CURRENT_TIMESTAMP (auto-update) | Last modification |
| `import_key` | VARCHAR(14) | YES | NULL | Import batch key |

**Indexes (`llx_customer_return_line.key.sql`):**

| Index | Columns |
|---|---|
| `idx_customer_return_line_fk` | `fk_customer_return` |
| `idx_customer_return_line_fk_product` | `fk_product` |
| `idx_customer_return_line_fk_expeditiondet` | `fk_expeditiondet` |

---

### `llx_customer_return_extrafields`

Standard Dolibarr extrafields table for customer returns.

| Column | Type | Description |
|---|---|---|
| `rowid` | INTEGER | Primary key (auto_increment) |
| `tms` | TIMESTAMP | Auto-update timestamp |
| `fk_object` | INTEGER | FK to `llx_customer_return.rowid` |
| `import_key` | VARCHAR(14) | Import batch key |

**Index:** `idx_customer_return_extrafields_fk_object` on `fk_object`.

---

## Numbering Models

### Abstract base: `ModeleNumRefCustomerreturn` (`core/modules/customerreturn/modules_customerreturn.php`)

Extends `CommonNumRefGenerator`. Defines two abstract methods: `getNextValue($objsoc, $object)` and `getExample()`.

Also defines `ModelePDFCustomerreturn` (extends `CommonDocGenerator`) for future PDF generation models.

### `mod_customerreturn_standard` (`core/modules/customerreturn/mod_customerreturn_standard.php`)

| Property | Value |
|---|---|
| `name` | `standard` |
| `version` | `1.0.0` |
| `prefix` | `RT` |

**Format:** `RT-YYMM-NNNN`

Example: `RT-2604-0001`

**`getNextValue()` logic:**
1. Derives `YYMM` from `$object->date_creation` or `dol_now()`.
2. Queries `MAX()` of the numeric suffix from existing refs matching `RT-{YYMM}-%` within the current entity.
3. Returns `RT-{YYMM}-{max+1}` zero-padded to 4 digits.

---

## Permissions

All permissions use `rights_class = 'customerreturn'` with object `customerreturn`.

| ID | Key (`[4].[5]`) | Type | Description |
|---|---|---|---|
| 510101 | `customerreturn.read` | r | Read customer returns |
| 510102 | `customerreturn.write` | w | Create/edit customer returns |
| 510103 | `customerreturn.delete` | d | Delete customer returns |
| 510104 | `customerreturn.validate` | d | Validate customer returns |
| 510105 | `customerreturn.close` | d | Close customer returns |

**Usage in code:** `$user->hasRight('customerreturn', 'customerreturn', 'read|write|delete|validate|close')`

---

## Language Keys

**File:** `langs/en_US/customerreturn.lang`

| Key | Value |
|---|---|
| `Module510100Name` | Customer Return |
| `Module510100Desc` | Customer merchandise return management with stock movement tracking and credit note generation |
| `Returns` | Returns |
| `CustomerReturnList` | Customer Returns |
| `NewCustomerReturn` | New Customer Return |
| `CustomerReturn` | Customer Return |
| `CustomerReturns` | Customer Returns |
| `LinkToCustomerReturn` | Link to Customer Return |
| `StatusDraft` | Draft |
| `StatusValidated` | Validated |
| `StatusClosed` | Closed |
| `CReturnDraft` | Draft |
| `CReturnValidated` | Validated |
| `CReturnClosed` | Closed |
| `CReturnWarehouse` | Warehouse |
| `CReturnCustomer` | Customer |
| `CReturnProduct` | Product |
| `CReturnSerialNumber` | Serial Number |
| `CReturnValidate` | Validate |
| `CReturnClose` | Close |
| `CReturnReopen` | Reopen |
| `ValidateCustomerReturn` | Validate Customer Return |
| `ConfirmValidateCustomerReturn` | Are you sure you want to validate this customer return? Stock movements will be created. |
| `CloseCustomerReturn` | Close Customer Return |
| `ConfirmCloseCustomerReturn` | Are you sure you want to close this customer return? |
| `ReopenCustomerReturn` | Reopen Customer Return |
| `ConfirmReopenCustomerReturn` | Are you sure you want to reopen this customer return to draft? |
| `DeleteCustomerReturn` | Delete Customer Return |
| `ConfirmDeleteCustomerReturn` | Are you sure you want to delete this customer return? |
| `ErrorCustomerReturnNotDraft` | Customer return is not in draft status |
| `ErrorCustomerReturnNotValidated` | Customer return is not in validated status |
| `ErrorFailedToGetNextRef` | Failed to generate next reference number |
| `CustomerReturnSetup` | Customer Return Setup |
| `DefaultWarehouse` | Default Receiving Warehouse |
| `NumberingModel` | Numbering Model |
| `SelectCustomerFirst` | Select Customer First |
| `CReturnShipment` | Shipment |
| `ShipmentRef` | Shipment Ref |
| `OrderRef` | Order Ref |
| `CReturnOrder` | Order |
| `QtyOrdered` | Qty Ordered |
| `QtyShipped` | Qty Shipped |
| `QtyAlreadyReturned` | Qty Already Returned |
| `ReturnQty` | Return Qty |
| `SelectItemsToReturn` | Select |
| `NoShipmentsFound` | No shipments found for this customer |
| `SelectAtLeastOneLine` | Please select at least one item to return |
| `CReturnDescription` | Description |
| `CReturnComment` | Comment |
| `CReturnLotSerial` | Lot/Serial |
| `CreateReturnFromShipment` | Create Return |
| `ReturnValidated` | Return %s validated |
| `SelectShipment` | Select a shipment |
| `CustomerShipments` | Shipments for this customer |
| `SelectShipmentToContinue` | Select a shipment to continue |
| `CreateCreditNote` | Create Credit Note |
| `ConfirmCreateCreditNote` | Confirm the creation of a credit note? |
| `ProductsInInvoice` | Products in invoice %s |
| `CreditNoteCreated` | Credit note %s created |
| `NoInvoiceLinked` | No invoice linked to this return |
| `ReturnDate` | Return Date |
| `CReturnLabel` | Label |
| `CReturnNotePublic` | Note (public) |
| `CReturnNotePrivate` | Note (private) |
| `Permission510101a` | Read customer returns |
| `Permission510102a` | Create/edit customer returns |
| `Permission510103a` | Delete customer returns |
| `Permission510104a` | Validate customer returns |
| `Permission510105a` | Close customer returns |
| `DebugMode` | Debug Mode |
| `DebugModeDesc` | Exposes diagnostic endpoint at /custom/customerreturn/ajax/debug.php for troubleshooting. Admin-only. |

---

## Configuration Constants

| Constant | Type | Default | Description |
|---|---|---|---|
| `CUSTOMERRETURN_ADDON` | chaine | `mod_customerreturn_standard` | Active numbering module class name |
| `CUSTOMERRETURN_WAREHOUSE_DEFAULT` | chaine | (none) | Default warehouse ID for receiving returned stock |
| `CUSTOMERRETURN_DEBUG_MODE` | chaine | `0` | Enable debug endpoint (`1` = enabled) |

---

## Module Descriptor

**File:** `core/modules/modCustomerreturn.class.php`

**Class:** `modCustomerreturn` (extends `DolibarrModules`)

| Property | Value |
|---|---|
| `numero` | 510100 |
| `family` | crm |
| `module_position` | 90 |
| `version` | 2.2.1 |
| `picto` | dollyrevert |
| `config_page_url` | `setup.php@customerreturn` |
| `depends` | `modSociete`, `modProduct`, `modStock` |
| `phpmin` | 7.0 |
| `need_dolibarr_version` | 16.0 |
| `langfiles` | `customerreturn@customerreturn` |
| `dirs` | `/customerreturn/temp` |

**Menus (under Products sidebar, `fk_mainmenu=products`):**

| Position | Key | Left Menu | URL | Level | Permission |
|---|---|---|---|---|---|
| 2700 | `CustomerReturns` | `customerreturns` | `/customerreturn/customerreturn_list.php` | 0 | read |
| 2701 | `NewCustomerReturn` | `customerreturn_new` | `/customerreturn/customerreturn_card.php?action=create` | child | write |
| 2702 | `List` | `customerreturn_listall` | `/customerreturn/customerreturn_list.php` | child | read |

**`init()` behavior:** Loads SQL tables from `/customerreturn/sql/`, deletes old menus to avoid duplicates, then calls `_init()`.

**`remove()` behavior:** Calls `_remove()` with default options.

---

## Library Functions

**File:** `lib/customerreturn.lib.php`

### `customerreturn_prepare_head($object)`

Builds the tab array for a CustomerReturn card page.

**Parameters:** `CustomerReturn $object` -- the fetched return object.

**Returns:** `array` -- array of tabs.

**Tabs generated:**

| Index | Tab ID | Label | URL |
|---|---|---|---|
| 0 | `card` | Card | `customerreturn_card.php?id={id}` |
| 1 | `note` | Notes (with badge if notes exist) | `customerreturn_note.php?id={id}` |

After the built-in tabs, calls `complete_head_from_modules()` to allow other modules to inject additional tabs via the `customerreturn@customerreturn` context.
