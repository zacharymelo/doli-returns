# Customer Returns -- Merchandise Return Management for Dolibarr

**Version 2.2.1** | [GitHub Repository](https://github.com/zacharymelo/doli-returns) | License: GPL-3.0

## Overview

Customer Returns adds a complete merchandise return workflow to Dolibarr. Create returns directly from shipments, track quantities per line, receive items back into specific warehouses and lots, and generate credit notes -- all without leaving your Dolibarr instance. The module appears in the left sidebar under **Products** for easy access.

## Features

### Create Returns from Shipments

Start a return by clicking "Create Return" on any completed shipment card. The system pulls in all shipped lines so you can choose exactly which items and quantities are being returned -- no need to re-enter product details.

### Per-Line Quantity Tracking

Select individual lines from the original shipment and specify the exact quantity being returned for each. Partial returns are fully supported. The system enforces maximums so you cannot return more than was shipped.

### Per-Line Warehouse and Lot Selection

When receiving returned items, choose the destination warehouse and lot/serial number for each line individually. This gives you precise control over where returned stock is placed in your inventory.

### Stock Movements on Receive

When a return is marked as received, stock movements are automatically created to add the returned quantities back into the selected warehouses. Your inventory levels update immediately with full traceability -- the stock movement log shows the return reference as the origin document.

### Credit Note Generation

Generate a credit note directly from a completed return. The system traverses the link chain (shipment to order to invoice) and creates a credit note with the returned line items at the original invoice pricing. Review and validate the credit note to complete the financial side of the return.

### 3-Status Lifecycle

Each return follows a simple lifecycle:

1. **Draft** -- The return has been created and can still be edited. Add or remove lines, adjust quantities, and select warehouses.
2. **Validated** -- The return is confirmed and locked for editing. It is now awaiting receipt of goods.
3. **Received** -- Items have been received, stock movements have been processed, and a credit note can be generated.

### Notes

Add internal notes to any return for record-keeping, communication with warehouse staff, or audit purposes.

### Menu Location

Customer Returns appears in the left sidebar under **Products**, alongside other product- and stock-related features. From there you can access the return list, create new returns, and manage existing ones.

## Requirements

| Requirement | Details |
|---|---|
| Dolibarr | Version 16 or higher |
| PHP | Version 7.0 or higher |
| **Required modules** | Third Parties, Products, Stock, Shipments |
| **Optional modules** | WarrantySvc (enables RMA-initiated returns) |

## Installation

1. Download the latest `.zip` file from the [GitHub Releases](https://github.com/zacharymelo/doli-returns/releases) page
2. Log in to your Dolibarr instance as an administrator
3. Navigate to **Home > Setup > Modules/Applications**
4. Click the **Deploy external module** button at the top of the page
5. Upload the `.zip` file you downloaded
6. Find "Customer Returns" in the module list and click the toggle to **enable** it
7. Click the gear icon to open the **Admin Setup** page and configure the module

## Configuration

After enabling the module, go to the admin setup page to configure:

- **Return Window (Days)** -- The number of days after shipment during which a return is allowed. Returns requested outside this window will be flagged.
- **Auto-Approve Toggle** -- When enabled, validated returns are automatically approved without requiring a separate approval step.
- **Require Tracking Number** -- When enabled, a tracking number must be entered before a return can be validated. Useful for tracking inbound packages from customers.
- **WarrantySvc Integration Toggle** -- When enabled, returns can be initiated from WarrantySvc service requests, linking the return to an RMA case for end-to-end traceability.

## Usage Guide

### Creating a Return from a Shipment

1. Navigate to a completed shipment (go to **Shipments** in the left menu and open the shipment)
2. Click the **Create Return** button on the shipment card
3. The system pre-fills the return form with all lines from the shipment, showing quantity ordered, quantity shipped, and quantity already returned
4. For each line, enter the quantity being returned. Set a line to zero to exclude it.
5. Save the return as a Draft

You can also create a return from the Returns menu by clicking "New Return Request," selecting a customer, then choosing a shipment from the list.

### Selecting Lines and Quantities

On the Draft return, review each line. Adjust return quantities as needed. For each line, select the destination warehouse where the returned item should be stocked, and optionally choose the lot or serial number. Once everything looks correct, validate the return.

### Receiving Items

1. Open a validated return
2. Confirm that the items have arrived at your facility
3. Click **Receive** to process the return
4. Stock movements are created automatically for each line, adding the returned quantities into the selected warehouses
5. The return moves to the Received status

### Credit Note Creation

Once a return is in the Received status, click the **Create Credit Note** button. The system generates a credit note pre-filled with the returned product lines at their original invoice prices. Review the credit note and validate it to issue the credit to the customer.

## Optional Integrations

### WarrantySvc

When the [WarrantySvc](https://github.com/zacharymelo/Dolibarr-Warranties) module is installed and the integration toggle is enabled in admin setup, returns can be initiated directly from service requests. This connects the return process to the RMA workflow so warranty-related returns are tracked from the initial service request through stock receipt and credit note. The linked service request appears in the return's "Linked Objects" block, and vice versa.

## Screenshots

**New Customer Return Form**
![New Customer Return](docs/screenshots/new-return-form.png)

## License

This module is licensed under the [GNU General Public License v3.0](https://www.gnu.org/licenses/gpl-3.0.html).
