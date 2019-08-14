<?php

/**
 * Lengow sync model log
 *
 * @category    Lengow
 * @package     Lengow_Sync
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Sync_Model_Log extends Mage_Core_Model_Abstract
{

    /**
     * Constructor
     */
    protected function _construct()
    {
        $this->_init('lensync/log');
    }

    /**
     * Save message event
     * @param $message string
     * @param $id_order integer
     *
     * @return boolean
     */
    public function log($message, $id_order = null)
    {
        if (!is_null($id_order)) {
            $message =  Mage::helper('lensync')->__('ID Order').' Lengow '.$id_order.' - '.$message;
        }
        $log = Mage::getModel('lensync/log');
        if (strlen($message) > 0) {
            $log->setDate(Mage::getModel('core/date')->date('Y-m-d H:i:s'));
            $log->setMessage($message);
            return $log->save();
        } else {
            return false;
        }
    }

    /**
     * Suppress log files when too old.
     */
    public function cleanLog()
    {
        $resource = Mage::getSingleton('core/resource');
        $writeConnection = $resource->getConnection('core_write');
        $table = $resource->getTableName('lensync/log');
        $query = "DELETE FROM ".$table." WHERE `date` < DATE_SUB(NOW(),INTERVAL 20 DAY)";
        $writeConnection->query($query);
    }
}
