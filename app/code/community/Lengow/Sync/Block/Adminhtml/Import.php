<?php

/**
 * Lengow order block manageorders adminhtml sync
 *
 * @category    Lengow
 * @package     Lengow_Sync
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Order_Block_Manageorders_Adminhtml_Sync extends Mage_Adminhtml_Block_Template
{

    public function getSyncOrdersUrl()
    {
        return $this->getUrl('*/*/SyncOrders');
    }
}
