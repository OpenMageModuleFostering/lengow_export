<?php

/**
 * Lengow tracker model system config source tracker
 *
 * @category    Lengow
 * @package     Lengow_Tracker
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Tracker_Model_System_Config_Source_Tracker extends Mage_Core_Model_Config_Data {

    public function toOptionArray()
    {
        if ((int)Mage::getStoreConfig('lentracker/general/version2')) {
            return array(
                array('value' => 'none', 'label' => Mage::helper('adminhtml')->__('Aucun')),
                array('value' => 'simpletag', 'label' => Mage::helper('adminhtml')->__('SimpleTag')),
                array('value' => 'tagcapsule', 'label' => Mage::helper('adminhtml')->__('TagCapsule')),
            );
        } else {
            return array(
                array('value' => 'none', 'label' => Mage::helper('adminhtml')->__('Aucun')),
                array('value' => 'simpletag', 'label' => Mage::helper('adminhtml')->__('SimpleTag'))
            );
        }
    }
}
