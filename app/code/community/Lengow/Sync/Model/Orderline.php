<?php

/**
 * Lengow sync model orderline
 *
 * @category    Lengow
 * @package     Lengow_Sync
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Sync_Model_Orderline extends Mage_Core_Model_Abstract
{

    /**
     * Constructor
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init('lensync/orderline');
    }

    /**
     * Save Order line
     *
     * @param integer $id_order
     * @param string $id_order_line
     *
     */
    public function addOrderLine($id_order, $id_order_line)
    {
        $this->setIdOrder($id_order);
        $this->setIdOrderLine($id_order_line);
        return $this->save();
    }
}
