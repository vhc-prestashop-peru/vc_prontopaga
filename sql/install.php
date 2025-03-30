<?php
/**
* 2007-2025 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2025 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

$sql = array();

// Tabla para los métodos de pago disponibles
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'vc_prontopaga_methods` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `method_id` INT(11) NOT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `name` CHAR(30) NOT NULL,
    `method` VARCHAR(50) NOT NULL,
    `currency` VARCHAR(10) NOT NULL,
    `logo` VARCHAR(200) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

// Tabla para almacenar las transacciones generadas
$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'vc_prontopaga_transactions` (
    `id_transaction` INT(11) NOT NULL AUTO_INCREMENT,
    `id_cart` INT(11) NOT NULL,
    `id_customer` INT(11) NOT NULL,
    `status` CHAR(12),
    `payment_method` VARCHAR(64),
    `country` VARCHAR(5),
    `currency` VARCHAR(5),
    `amount` DECIMAL(20,6),
    `order` VARCHAR(64),
    `url_pay` TEXT,
    `uid` VARCHAR(64),
    `reference` VARCHAR(64),
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_transaction`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

// Ejecutar todas las queries
foreach ($sql as $query) {
    if (!Db::getInstance()->execute($query)) {
        return false;
    }
}

return true;