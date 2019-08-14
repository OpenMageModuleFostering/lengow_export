<?php

/**
 * Lengow sync model system config source shipping carrier
 *
 * @category    Lengow
 * @package     Lengow_Sync
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Sync_Model_System_Config_Source_Shipping_Carrier extends Mage_Core_Model_Config_Data
{

    public function toOptionArray()
    {
        $carriers = Mage::getModel('shipping/config')->getActiveCarriers();
        $select = array();
        foreach ($carriers as $code => $model) {
            $title = Mage::getStoreConfig('carriers/' . $code . '/title');
            $select[$code] = empty($title) ? $code : $title;
        }
        return $select;
    }

    public function toSelectArray()
    {
        $carriers = Mage::getModel('shipping/config')->getActiveCarriers();
        $select = array();
        foreach ($carriers as $code => $model) {
            $select[$code] = Mage::getStoreConfig('carriers/' . $code . '/title');
        }
        return $select;
    }
}
