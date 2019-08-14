<?php
/**
 * Lengow sync model observer
 *
 * @category    Lengow
 * @package     Lengow_Sync
 * @author      Ludovic Drin <ludovic@lengow.com>
 * @copyright   2013 Lengow SAS 
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Sync_Model_Observer {

	public function import($observer)	{	
		try {
			$_config = Mage::getSingleton('sync/config');
			if($_config->getConfig('sync/orders/cron')) {
				$import = Mage::getModel('sync/import');
	            $days = Mage::getModel('sync/config')->setStore(Mage::app()->getStore()->getId())
	                                                 ->getConfig('sync/orders/period');
	            $date_from = date('Y-m-d', strtotime(date('Y-m-d') . '-' . $days . 'days'));
	            $date_to = date('Y-m-d');
	            $import = Mage::getModel('sync/import');
	            $result = $import->exec('orders', array('dateFrom' => $date_from,
	                                                    'dateTo' => $date_to)); 
			}
		} catch (Exception $e) {
			Mage::throwException($e);
		}
		return $this;
	}

	public function salesOrderShipmentSaveAfter(Varien_Event_Observer $observer) {
        $shipment = $observer->getEvent()->getShipment();
        $order = $shipment->getOrder();
        if($order->getData('from_lengow') == 1) {          
	        $marketplace = Mage::getModel('sync/marketplace');
	        $marketplace->set($order->getMarketplaceLengow());
	        if ($order->getState() == Mage::getSingleton('sync/config')->getOrderState('processing')) {
	        	Mage::helper('sync')->log('WDSL : send tracking to ' . $order->getData('marketplace_lengow') . ' - ' . $order->getData('feed_id_lengow') . ' - ' . $order->getData('order_id_lengow'));
	            $marketplace->wsdl('shipped', $order->getData('feed_id_lengow'), $order, $shipment);
	        }
	    }
        return $this;
    }

	public function salesOrderPaymentCancel(Varien_Event_Observer $observer) {
		$payment = $observer->getEvent()->getPayment();
        $order = $payment->getOrder();
        if($order->getData('from_lengow') == 1) {        
	        $marketplace = Mage::getModel('sync/marketplace');
	        $marketplace->set($order->getMarketplaceLengow());
	        if ($order->getState() == Mage::getSingleton('sync/config')->getOrderState('processing')) {
	        	Mage::helper('sync')->log('WDSL : send cancel to ' . $order->getData('marketplace_lengow') . ' - ' . $order->getData('feed_id_lengow') . ' - ' . $order->getData('order_id_lengow'));
	            $marketplace->wsdl('refuse', $order->getData('feed_id_lengow'), $order);
	        }
        }
    }

	public function salesOrderSaveCommitAfter(Varien_Event_Observer $observer) {
		$order = $observer->getEvent();
        if($order->getData('from_lengow') == 1) {  
	        $marketplace = Mage::getModel('sync/marketplace');
	        $marketplace->set($order->getMarketplaceLengow());
	        if ($order->getState() == self::STATE_COMPLETE && $order->getState() == Mage::getSingleton('sync/config')->getOrderState('processing')) {
	        	Mage::helper('sync')->log('WDSL : send cancel to ' . $order->getData('marketplace_lengow') . ' - ' . $order->getData('feed_id_lengow') . ' - ' . $order->getData('order_id_lengow'));
	            $marketplace->wsdl('shipped', $order->getData('feed_id_lengow'), $order);
	        }
        }
	}

}