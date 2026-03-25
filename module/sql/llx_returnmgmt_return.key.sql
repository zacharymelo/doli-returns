ALTER TABLE llx_returnmgmt_return ADD UNIQUE INDEX uk_returnmgmt_return_ref (ref, entity);
ALTER TABLE llx_returnmgmt_return ADD INDEX idx_returnmgmt_return_fk_soc (fk_soc);
ALTER TABLE llx_returnmgmt_return ADD INDEX idx_returnmgmt_return_fk_product (fk_product);
ALTER TABLE llx_returnmgmt_return ADD INDEX idx_returnmgmt_return_status (status);
ALTER TABLE llx_returnmgmt_return ADD INDEX idx_returnmgmt_return_serial (serial_number);
ALTER TABLE llx_returnmgmt_return ADD INDEX idx_returnmgmt_return_fk_user_assigned (fk_user_assigned);
