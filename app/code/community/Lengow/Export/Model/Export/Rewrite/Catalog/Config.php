<?php

/**
 * Lengow export model rewrite catalog config
 *
 * @category    Lengow
 * @package     Lengow_Export
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Export_Model_Rewrite_Catalog_Config extends Mage_Catalog_Model_Config
{

    /**
     * Get attribute by code for entity type
     *
     * @param   mixed $entityType
     * @param   mixed $code
     * @return  Mage_Eav_Model_Entity_Attribute_Abstract
     */
    public function getAttribute($entityType, $code)
    {
        $attribute = parent::getAttribute($entityType, $code);
        if (is_object($attribute) && $attribute->getAttributeCode() == '') {
            $attribute->setAttributeCode($code);
        }
        return $attribute;
    }
}
