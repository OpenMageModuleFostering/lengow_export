<?php

/**
 * Lengow tracker model system config source identifiant
 *
 * @category    Lengow
 * @package     Lengow_Tracker
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Tracker_Model_System_Config_Source_Identifiant extends Mage_Core_Model_Config_Data {

    public function toOptionArray()
    {
        return array(
            array('value' => 'sku', 'label' => Mage::helper('adminhtml')->__('Sku')),
            array('value' => 'entity_id', 'label' => Mage::helper('adminhtml')->__('ID product')),
        );
    }
}
