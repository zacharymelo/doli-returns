ALTER TABLE llx_customer_return ADD UNIQUE INDEX uk_customer_return_ref (ref, entity);
ALTER TABLE llx_customer_return ADD INDEX idx_customer_return_fk_soc (fk_soc);
ALTER TABLE llx_customer_return ADD INDEX idx_customer_return_status (status);
ALTER TABLE llx_customer_return ADD INDEX idx_customer_return_fk_expedition (fk_expedition);
ALTER TABLE llx_customer_return ADD INDEX idx_customer_return_fk_commande (fk_commande);
ALTER TABLE llx_customer_return ADD INDEX idx_customer_return_fk_project (fk_project);
