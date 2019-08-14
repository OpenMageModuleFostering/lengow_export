<?php

/**
 * Lengow export model source attributes
 *
 * @category    Lengow
 * @package     Lengow_Export
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Export_Model_Source_Attributes
{

    public function toOptionArray()
    {
        $attributes = Mage::getSingleton('lenexport/convert_parser_product')->getExternalAttributes();
        array_unshift($attributes, array(
            'value' => 'none',
            'label' => Mage::helper('adminhtml')->__('Select attribut to map')
        ));
        return $attributes;
    }
}
