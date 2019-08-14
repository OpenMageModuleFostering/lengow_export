<?php
/**
 * Lengow adminhtml sync order controller
 *
 * @category    Lengow
 * @package     Lengow_Sync
 * @author      Ludovic Drin <ludovic@lengow.com>
 * @copyright   2013 Lengow SAS 
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Sync_Adminhtml_Lengow_OrderController extends Mage_Adminhtml_Controller_Action {

    protected function _initAction() {
        $this->loadLayout()
             ->_setActiveMenu('lengow/order')
             ->_addBreadcrumb(Mage::helper('sync')->__('Lengow manage orders'), Mage::helper('sync')->__('Lengow orders'));
        return $this;
    }
    
    
    public function indexAction() {
        $this->_initAction()
             ->renderLayout();      
        return $this;
    }
    
    public function gridAction() {
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('sync/adminhtml_order_grid')->toHtml()
        );
        return $this;
    }
    
    /**
     * Export order grid to CSV format
     */
    public function exportCsvAction() {
        $filename   = 'orders_lengow.csv';
        $grid       = $this->getLayout()->createBlock('sync/adminhtml_order_grid');
        $this->_prepareDownloadResponse($filename, $grid->getCsvFile());
    }

    /**
     *  Export order grid to Excel XML format
     */
    public function exportExcelAction() {
        $filename   = 'orders_lengow.xml';
        $grid       = $this->getLayout()->createBlock('sync/adminhtml_order_grid');
        $this->_prepareDownloadResponse($filename, $grid->getExcelFile($filename));
    }
    
    public function importAction() {
        try {
            $days = Mage::getModel('sync/config')->setStore(Mage::app()->getStore()->getId())
                                                 ->getConfig('sync/orders/period');
            $date_from = date('Y-m-d', strtotime(date('Y-m-d') . '-' . $days . 'days'));
            $date_to = date('Y-m-d');
            $import = Mage::getModel('sync/import');
            $result = $import->exec('orders', array('dateFrom' => $date_from,
                                                    'dateTo' => $date_to)); 
            if($result['new'] > 0)
                $this->_getSession()->addSuccess(Mage::helper('sync')->__('%d orders are imported', $result['new']));
            if($result['update'] > 0)
                $this->_getSession()->addSuccess(Mage::helper('sync')->__('%d orders are updated', $result['update']));
            if($result['new'] == 0 && $result['update'] == 0)
                $this->_getSession()->addSuccess(Mage::helper('sync')->__('No order available to import'));
        } catch(Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        }       
        $this->_redirect("*/*/index");
    }
    
}