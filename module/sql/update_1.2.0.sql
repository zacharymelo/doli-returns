-- ReturnMgmt v1.2.0 migration
-- Run on existing installs to add new columns

ALTER TABLE llx_returnmgmt_return ADD COLUMN fk_expedition INTEGER AFTER exchange_serial_number;
ALTER TABLE llx_returnmgmt_return ADD COLUMN label VARCHAR(255) AFTER fk_expedition;
ALTER TABLE llx_returnmgmt_return ADD COLUMN date_return DATETIME AFTER label;

ALTER TABLE llx_returnmgmt_return ADD INDEX idx_returnmgmt_return_fk_expedition (fk_expedition);

ALTER TABLE llx_returnmgmt_return_line ADD COLUMN fk_expedition INTEGER AFTER serial_number;
ALTER TABLE llx_returnmgmt_return_line ADD COLUMN fk_expeditiondet INTEGER AFTER fk_expedition;
ALTER TABLE llx_returnmgmt_return_line ADD COLUMN fk_commandedet INTEGER AFTER fk_expeditiondet;
ALTER TABLE llx_returnmgmt_return_line ADD COLUMN fk_entrepot INTEGER AFTER fk_commandedet;
ALTER TABLE llx_returnmgmt_return_line ADD COLUMN comment TEXT AFTER fk_entrepot;

ALTER TABLE llx_returnmgmt_return_line ADD INDEX idx_returnmgmt_return_line_fk_expeditiondet (fk_expeditiondet);
