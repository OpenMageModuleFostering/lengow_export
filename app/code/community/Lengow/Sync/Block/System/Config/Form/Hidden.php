<?php

/**
 * Lengow Sync model system config source getmarkeplace
 *
 * @category    Lengow
 * @package     Lengow_Sync
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Sync_Block_System_Config_Form_Hidden extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
    * Render element html
    *
    * @param Varien_Data_Form_Element_Abstract $element
    * @return string
    */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
       return sprintf('<tr id="row_%s" style="display:none;"><td>%s</td></tr>', $element->getHtmlId(), $element->getElementHtml());
    }
}
