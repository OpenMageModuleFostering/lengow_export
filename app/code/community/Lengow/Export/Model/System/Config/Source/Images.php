<?php

/**
 * Lengow export model system config source count images
 *
 * @category    Lengow
 * @package     Lengow_Export
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Export_Model_System_Config_Source_Images extends Mage_Core_Model_Config_Data
{

    public function toOptionArray()
    {
        $array = array();
        for ($i = 5; $i <= 20; $i++) {
            $array[] = array(
                'value' => $i,
                'label' => $i
            );
        }
        return $array;
    }

    public function toSelectArray()
    {
        $select = array();
        foreach ($this->toOptionArray() as $option) {
            $select[$option['value']] = $option['label'];
        }
        return $select;
    }
}
