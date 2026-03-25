CREATE TABLE llx_returnmgmt_return_line(
    rowid                  INTEGER       AUTO_INCREMENT PRIMARY KEY,
    fk_returnmgmt_return   INTEGER       NOT NULL,
    fk_product             INTEGER,
    description            TEXT,
    qty                    DOUBLE        NOT NULL DEFAULT 1,
    serial_number          VARCHAR(128),
    subprice               DOUBLE(24,8)  DEFAULT 0,
    total_ht               DOUBLE(24,8)  DEFAULT 0,
    tva_tx                 DOUBLE(7,4)   DEFAULT 0,
    rang                   INTEGER       DEFAULT 0,
    date_creation          DATETIME,
    tms                    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    import_key             VARCHAR(14)
) ENGINE=innodb;
