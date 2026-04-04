# Changelog

## [2.2.2] - 2026-04-04

### Fixed
- Fix AJAX shipment list "undefined" lines — use dol_buildpath() instead of hardcoded /custom/ path

## [2.2.1] - 2026-04-03

### Fixed
- Fix phpcs violations — docblocks, string concats, comment style

## [2.2.0] - 2026-04-02

### Added
- Debug diagnostic endpoint with settings toggle

### Fixed
- Prefix 18 generic lang keys with CReturn
- Fix phpcs violations

## [2.1.0] - 2026-03-26

### Added
- Shipment selection UX — clickable refs, dates, status, product line summaries, auto-select from SR

## [2.0.7] - 2026-03-26

### Fixed
- Use warrantysvc_svcrequest as sourcetype in element_element for SR linking

## [2.0.6] - 2026-03-25

### Fixed
- Remove paddingright from menu icon to align with core headings

## [2.0.5] - 2026-03-25

### Fixed
- Match sidebar pattern — add List child entry, fix heading level

## [2.0.4] - 2026-03-25

### Fixed
- Add dollyrevert icon prefix to Customer Returns sidebar heading

## [2.0.3] - 2026-03-25

### Fixed
- Move menu from standalone top nav to Products sidebar after Receptions

## [2.0.2] - 2026-03-25

### Fixed
- Add $module property
- Fix transnoentitiesaliases fatal error on card view

## [2.0.1] - 2026-03-25

### Fixed
- Add missing require_once for DolEditor and Societe classes in card.php

## [2.0.0] - 2026-03-25

### Changed
- Rename returnmgmt to customerreturn
- Simplify to 3-status lifecycle, strip RMA fields

## [1.2.0] - 2026-03-25

### Added
- Shipment-initiated returns with per-line qty tracking
- Stock movements on receive
- Credit note creation

## [1.1.2] - 2026-03-25

### Fixed
- AJAX shipment lines query (wrong joins)
- Module init/remove lifecycle methods

## [1.1.1] - 2026-03-25

### Fixed
- Clean menu entries on init/remove to prevent duplicate error on re-enable

## [1.1.0] - 2026-03-25

### Added
- Shipment line picker for returns

### Fixed
- card.php errors
