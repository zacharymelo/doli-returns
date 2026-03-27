# ReturnMgmt — Dolibarr Product Return Management

Custom Dolibarr module for managing product returns with refund, exchange, repair, and rejection workflows.

## Requirements

- Dolibarr **16.0+**
- PHP **7.0+**
- Modules enabled: **Third Parties**, **Products**, **Stocks**

## Install

### Upload via Dolibarr UI

1. Download `returnmgmt-x.y.z.zip` from [Releases](https://github.com/zacharymelo/doli-returns/releases) or build it (see below)
2. Go to **Home → Setup → Modules/Applications**
3. Click **Deploy/install an external app/module**
4. Upload the zip — top-level directory must be `returnmgmt/`
5. Activate the module and assign permissions

### Manual install

Copy the `module/` contents into your Dolibarr custom directory:

```
cp -r module/ /path/to/dolibarr/htdocs/custom/returnmgmt/
```

## Dev environment

```bash
docker compose up -d
```

Dolibarr runs at `http://localhost:8080` (admin/admin). The `module/` directory is bind-mounted to `/var/www/html/custom/returnmgmt` — edits are live.

## Module overview

### Workflow

```
DRAFT → PENDING → APPROVED → RECEIVED → PROCESSING → COMPLETED
                    ↘ REJECTED
```

### Create a return

**From a shipment card:** Click **Create Return** on any validated shipment. The form pre-fills the source shipment, linked order, and customer. Line items show qty ordered, qty shipped, qty already returned, and a return qty input with per-line warehouse and lot/serial selection.

**Standalone:** Navigate to Returns → New Return Request. Select a customer, then pick a shipment from the list.

### Stock movements

When a return is moved to **Received** status, the module creates positive stock movements (`MouvementStock::reception()`) for each return line into the selected warehouse. The stock movement log shows the return ref as the origin document.

### Credit notes

When a return is **Completed** with resolution type **Refund**, a **Create Credit Note** button appears. It traverses the link chain (shipment → order → invoice) and creates a credit note with the returned line items at original invoice pricing.

### Permissions

| ID | Permission |
|----|------------|
| 520001 | Read return requests |
| 520002 | Create/edit return requests |
| 520003 | Delete return requests |
| 520004 | Approve or reject return requests |
| 520005 | Complete/close return requests |

### Setup

**Home → Setup → ReturnMgmt Setup**

- Return window (days)
- Default receiving warehouse
- Auto-approve returns
- Require tracking number before receive
- Customer notifications on approve/complete
- Numbering model

## File structure

```
module/
├── admin/setup.php
├── ajax/
│   ├── customer_shipments.php
│   └── shipment_lines.php
├── class/
│   ├── actions_returnmgmt.class.php
│   ├── returnrequest.class.php
│   └── returnrequestline.class.php
├── core/
│   ├── modules/
│   │   ├── modReturnmgmt.class.php
│   │   └── returnmgmt/
│   │       ├── mod_returnmgmt_standard.php
│   │       └── modules_returnmgmt.php
│   └── triggers/
│       └── interface_99_modReturnmgmt_ReturnmgmtTrigger.class.php
├── langs/en_US/returnmgmt.lang
├── lib/returnmgmt.lib.php
├── sql/
│   ├── llx_returnmgmt_return.sql
│   ├── llx_returnmgmt_return.key.sql
│   ├── llx_returnmgmt_return_line.sql
│   ├── llx_returnmgmt_return_line.key.sql
│   ├── llx_returnmgmt_return_extrafields.sql
│   ├── llx_returnmgmt_return_extrafields.key.sql
│   └── update_1.2.0.sql
├── returnrequest_card.php
├── returnrequest_list.php
└── returnrequest_note.php
```

## Building the zip

The zip must have `returnmgmt/` as the top-level directory (not `module/`):

```bash
ln -sfn module returnmgmt
zip -r returnmgmt-1.2.0.zip returnmgmt/
rm returnmgmt
```

## Upgrading from 1.1.x

After uploading v1.2.0, disable and re-enable the module. This runs `_load_tables()` which executes `update_1.2.0.sql` to add new columns. Alternatively, run the migration SQL manually:

```sql
ALTER TABLE llx_returnmgmt_return ADD COLUMN fk_expedition INTEGER;
ALTER TABLE llx_returnmgmt_return ADD COLUMN label VARCHAR(255);
ALTER TABLE llx_returnmgmt_return ADD COLUMN date_return DATETIME;
ALTER TABLE llx_returnmgmt_return_line ADD COLUMN fk_expedition INTEGER;
ALTER TABLE llx_returnmgmt_return_line ADD COLUMN fk_expeditiondet INTEGER;
ALTER TABLE llx_returnmgmt_return_line ADD COLUMN fk_commandedet INTEGER;
ALTER TABLE llx_returnmgmt_return_line ADD COLUMN fk_entrepot INTEGER;
ALTER TABLE llx_returnmgmt_return_line ADD COLUMN comment TEXT;
```

## Changelog

### 1.2.0

- **Shipment-initiated returns** — "Create Return" button on validated shipment cards
- **Per-line qty tracking** — qty ordered, qty shipped, qty already returned with max enforcement
- **Per-line warehouse and lot/serial** selection on create form
- **Stock movements on receive** — positive stock entries via `MouvementStock::reception()` with origin tracking
- **Credit note creation** — traverses shipment → order → invoice links to create credit notes with original pricing
- **Updated list page** — columns: Ref, Label, Return Date, Customer, Note, Status with date range filters
- **Standalone create with shipment picker** — select customer → pick shipment → load lines

### 1.1.2

- Fix AJAX shipment lines query (wrong `expeditiondet_batch`/`element_element` joins)
- Fix module `remove()` calling `_init()` instead of `_remove()`
- Fix `init()` missing `delete_menus()` before `_init()`
- Fix zip package structure (top-level dir `returnmgmt/` not `module/`)

### 1.1.1

- Fix duplicate menu entry error on module re-enable

### 1.1.0

- Shipment line picker for return creation
- Card page error fixes

## License

GPL-3.0-or-later
