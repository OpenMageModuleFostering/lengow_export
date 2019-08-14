<?php

/**
 * Lengow sync model mysql4 orderline
 *
 * @category    Lengow
 * @package     Lengow_Sync
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Sync_Model_Mysql4_Orderline extends Mage_Core_Model_Mysql4_Abstract
{

    protected function _construct()
    {
        $this->_init('lensync/orderline', 'id');
    }
}
