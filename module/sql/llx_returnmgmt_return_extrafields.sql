CREATE TABLE llx_returnmgmt_return_extrafields(
    rowid      INTEGER AUTO_INCREMENT PRIMARY KEY,
    tms        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fk_object  INTEGER NOT NULL,
    import_key VARCHAR(14)
) ENGINE=innodb;
