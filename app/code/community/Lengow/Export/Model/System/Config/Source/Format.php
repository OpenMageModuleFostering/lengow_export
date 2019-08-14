<?php

/**
 * Lengow export model systems config source format
 *
 * @category    Lengow
 * @package     Lengow_Export
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Export_Model_System_Config_Source_Format extends Mage_Core_Model_Config_Data
{

    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'csv',
                'label' => 'csv'
            ),
            array(
                'value' => 'xml',
                'label' => 'xml'
            ),
            array(
                'value' => 'json',
                'label' => 'json'
            ),
            array(
                'value' => 'yaml',
                'label' => 'yaml'
            ),
        );
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
