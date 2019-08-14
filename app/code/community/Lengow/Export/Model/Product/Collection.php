<?php

/**
 * Lengow export model product collection
 *
 * @category    Lengow
 * @package     Lengow_Export
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Export_Model_Product_Collection extends Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection
{

    public function isEnabledFlat()
    {
        return false;
    }

    public function getCollection()
    {
        return $this;
    }

    /**
     * Initialize resources
     *
     */
    protected function _construct()
    {
        $this->_init('lenexport/catalog_product');
        $this->_initTables();
    }
}
