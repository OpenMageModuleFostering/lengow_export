<?php
/**
 * Lengow export controller
 *
 * @category    Lengow
 * @package     Lengow_Export
 * @author      Ludovic Drin <ludovic@lengow.com>
 * @copyright   2013 Lengow SAS 
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Sync_ApiController extends Mage_Core_Controller_Front_Action {

    public function indexAction() {
        echo 'Please specify an action';
    }

    public function taxAction() {
        $helper = Mage::helper('export/security');
        if($helper->checkIp()) {
            $id_order = $this->getRequest()->getParam('id');
            $new_tax = $this->getRequest()->getParam('rate');
            if($order = Mage::getModel('sales/order')->load($id_order)) {
                if($order->hasFromLengow()) {
                    $this->_rebuildOrder($order);
                }
            } else {
                echo 'Order not find';
            }
        } else {
            echo 'Unauthorised ip : ' . $_SERVER['REMOTE_ADDR'];
        }
    } 

    private function _rebuildOrder($order) {
    }

    private function _rebuildInvoice($order) {
        
    }
}
