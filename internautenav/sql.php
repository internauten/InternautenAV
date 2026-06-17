<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class InternautenavSql
{
    public static function installSchema($dbPrefix, $engine, $verificationTable, $logTable, $uploadTable)
    {
        $queries = self::getInstallQueries($dbPrefix, $engine, $verificationTable, $logTable, $uploadTable);
        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return self::runInstallMigrations($dbPrefix, $uploadTable);
    }

    public static function uninstallSchema($dbPrefix, $verificationTable, $logTable, $uploadTable)
    {
        $queries = self::getUninstallQueries($dbPrefix, $verificationTable, $logTable, $uploadTable);
        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    public static function tableExists($fullTableName)
    {
        $exists = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = \'' . pSQL((string) $fullTableName) . '\''
        );

        return $exists > 0;
    }

    private static function columnExists($fullTableName, $columnName)
    {
        $exists = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = \'' . pSQL((string) $fullTableName) . '\'
               AND COLUMN_NAME = \'' . pSQL((string) $columnName) . '\''
        );

        return $exists > 0;
    }

    private static function indexExists($fullTableName, $indexName)
    {
        $exists = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = \'' . pSQL((string) $fullTableName) . '\'
               AND INDEX_NAME = \'' . pSQL((string) $indexName) . '\''
        );

        return $exists > 0;
    }

    private static function runInstallMigrations($dbPrefix, $uploadTable)
    {
        $fullUploadTable = (string) $dbPrefix . (string) $uploadTable;
        if (!self::tableExists($fullUploadTable)) {
            return false;
        }

        if (self::columnExists($fullUploadTable, 'id_cart')) {
            if (!Db::getInstance()->execute(
                'ALTER TABLE `' . bqSQL($fullUploadTable) . '`
                 MODIFY `id_cart` INT(10) UNSIGNED NULL'
            )) {
                return false;
            }
        }

        if (!self::indexExists($fullUploadTable, 'idx_id_customer')) {
            if (!Db::getInstance()->execute(
                'ALTER TABLE `' . bqSQL($fullUploadTable) . '`
                 ADD INDEX `idx_id_customer` (`id_customer`)'
            )) {
                return false;
            }
        }

        return true;
    }

    private static function getInstallQueries($dbPrefix, $engine, $verificationTable, $logTable, $uploadTable)
    {
        $verification = bqSQL((string) $dbPrefix . (string) $verificationTable);
        $log = bqSQL((string) $dbPrefix . (string) $logTable);
        $upload = bqSQL((string) $dbPrefix . (string) $uploadTable);

        return [
            'CREATE TABLE IF NOT EXISTS `' . $verification . '` (
                `id_customer` INT(10) UNSIGNED NOT NULL,
                `is_verified` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                `doc_type` VARCHAR(16) NOT NULL,
                `birth_date` DATE NULL,
                `firstname` VARCHAR(64) NULL,
                `lastname` VARCHAR(64) NULL,
                `verified_at` DATETIME NOT NULL,
                PRIMARY KEY (`id_customer`)
            ) ENGINE=' . pSQL((string) $engine) . ' DEFAULT CHARSET=utf8mb4;',
            'CREATE TABLE IF NOT EXISTS `' . $log . '` (
                `id_internautenav_verification_log` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `customer_reference` VARCHAR(64) NOT NULL,
                `id_customer` INT(10) UNSIGNED NULL,
                `id_guest` INT(10) UNSIGNED NULL,
                `id_cart` INT(10) UNSIGNED NULL,
                `doc_type` VARCHAR(16) NOT NULL,
                `result` TINYINT(1) UNSIGNED NOT NULL,
                `result_message` VARCHAR(255) NULL,
                `checked_at` DATETIME NOT NULL,
                PRIMARY KEY (`id_internautenav_verification_log`),
                KEY `idx_customer_reference` (`customer_reference`),
                KEY `idx_checked_at` (`checked_at`)
            ) ENGINE=' . pSQL((string) $engine) . ' DEFAULT CHARSET=utf8mb4;',
            'CREATE TABLE IF NOT EXISTS `' . $upload . '` (
                `id_internautenav_uploaded_document` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_cart` INT(10) UNSIGNED NULL,
                `id_order` INT(10) UNSIGNED NULL,
                `id_customer` INT(10) UNSIGNED NULL,
                `doc_type` VARCHAR(16) NOT NULL,
                `file_name` VARCHAR(255) NOT NULL,
                `original_name` VARCHAR(255) NOT NULL,
                `mime_type` VARCHAR(100) NOT NULL,
                `file_size` INT(10) UNSIGNED NOT NULL,
                `created_at` DATETIME NOT NULL,
                `attached_at` DATETIME NULL,
                PRIMARY KEY (`id_internautenav_uploaded_document`),
                KEY `idx_id_customer` (`id_customer`),
                KEY `idx_id_order` (`id_order`),
                KEY `idx_created_at` (`created_at`)
            ) ENGINE=' . pSQL((string) $engine) . ' DEFAULT CHARSET=utf8mb4;',
        ];
    }

    private static function getUninstallQueries($dbPrefix, $verificationTable, $logTable, $uploadTable)
    {
        $verification = bqSQL((string) $dbPrefix . (string) $verificationTable);
        $log = bqSQL((string) $dbPrefix . (string) $logTable);
        $upload = bqSQL((string) $dbPrefix . (string) $uploadTable);

        return [
            'DROP TABLE IF EXISTS `' . $verification . '`;',
            'DROP TABLE IF EXISTS `' . $log . '`;',
            'DROP TABLE IF EXISTS `' . $upload . '`;',
        ];
    }
}
