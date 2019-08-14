<?php

/**
 * Lengow sync model order
 *
 * @category    Lengow
 * @package     Lengow_Sync
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Sync_Model_Order extends Mage_Sales_Model_Order
{

    protected $_countryCollection;

    protected $_config;

    protected $_canInvoice = false;

    protected $_canShip = false;

    protected $_canCancel = false;

    protected $_canRefund = false;

    protected $_hasInvoices = false;

    protected $_hasShipments = false;

    protected $_isCanceled = false;

    protected $_isRefunded = false;

    /**
     * is Already Imported
     *
     * @param string  $lengow_id           Lengow order id
     * @param integer $delivery_address_id delivery address id
     * @param string  $marketplace         marketplace name
     * @param string  $marketplace_legacy  marketplace legacy name
     *
     * @return mixed
     */
    public function isAlreadyImported($lengow_id, $delivery_address_id, $marketplace, $marketplace_legacy)
    {
        // V2 compatibility
        $in = is_null($marketplace_legacy) ? array($marketplace) : array($marketplace, strtolower($marketplace_legacy));

        $results = $this->getCollection()
            ->addAttributeToFilter('order_id_lengow', $lengow_id)
            ->addAttributeToFilter('marketplace_lengow', array('in' => $in))
            ->addAttributeToSelect('entity_id')
            ->addAttributeToSelect('delivery_address_id_lengow')
            ->addAttributeToSelect('feed_id_lengow')
            ->getData();
        if (count($results) == 0) {
            return false;
        }
        foreach ($results as $result) {
            if ($result['delivery_address_id_lengow'] == 0 && $result['feed_id_lengow'] != 0) {
                return $result['entity_id'];
            } elseif ($result['delivery_address_id_lengow'] == $delivery_address_id) {
                return $result['entity_id'];
            }
        }
        return false;
    }

    /**
     * Get Lengow ID with order ID Prestashop and delivery address ID
     *
     * @param integer $order_id magento    order id
     * @param string  $delivery_address_id delivery address id
     *
     * @return mixed
     */
    public function getLengowIdFromLengowDeliveryAddress($order_id, $delivery_address_id)
    {
        $results = $this->getCollection()
            ->addAttributeToFilter('entity_id', $order_id)
            ->addAttributeToFilter('delivery_address_id_lengow', $delivery_address_id)
            ->addAttributeToSelect('order_id_lengow')
            ->getData();
        if (count($results) > 0) {
            return $results[0]['order_id_lengow'];
        }
        return false;
    }

    /**
     * Get order line from Order
     *
     * @param integer $id_order
     *
     * @return mixed
     *
     */
    public function getOrderLineFromIdOrder($order_id)
    {
        $order_line_id = Mage::getModel('lensync/orderline')->getCollection()
            ->addFieldToFilter('id_order', $order_id)
            ->addFieldToSelect('id_order_line')
            ->getData();
        if (count($order_line_id) > 0) {
            return $order_line_id;
        }
        return false;
    }

    /**
     * Get order ids from lengow order ID
     *
     * @param string $lengow_id
     * @param string $marketplace
     *
     * @return array
     *
     */
    public function getOrderIdFromLengowOrder($lengow_id, $marketplace)
    {
        $orders = $this->getCollection()
            ->addAttributeToFilter('order_id_lengow', $lengow_id)
            ->addAttributeToFilter('marketplace_lengow', $marketplace)
            ->addAttributeToSelect('entity_id')
            ->getData();
        return $orders;
    }

    /**
     * Save order line in lengow orders line table
     *
     * @param Mage_Sales_Model_Order $order order imported
     * @param string $order_line_id order line id
     *
     */
    public function addLengowOrderLine($order, $order_line_id)
    {
        $orderLine = Mage::getModel('lensync/orderline');
        $orderLine->addOrderLine(
            (integer)$order->getId(),
            (string)$order_line_id
        );
    }

    /**
     * Retrieve config singleton
     *
     * @return Lengow_Sync_Model_Config
     */
    public function getConfig()
    {
        if (is_null($this->_config)) {
            $this->_config = Mage::getSingleton('lensync/config');
        }
        return $this->_config;
    }

    /**
     * Set config
     *
     * @param Lengow_Sync_Model_Config $config
     *
     * @return Lengow_Sync_Model_Order
     */
    public function setConfig($config)
    {
        $this->_config = $config;
        return $this;
    }

    /**
     * Create invoice
     *
     * @param Mage_Sales_Model_Order
     *
     */
    public function toInvoice($order)
    {
        $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
        if ($invoice) {
            $invoice->register();
            $invoice->getOrder()->setIsInProcess(true);
            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());
            $transactionSave->save();
            $this->_hasInvoices = true;
        }
    }

    /**
     * Ship order
     *
     * @param Mage_Sales_Model_Order $order
     * @param string $carrier
     * @param string $title
     * @param string $tracking
     *
     */
    public function toShip($order, $carrier = null, $title = '', $tracking = '')
    {
        if ($order->canShip()) {
            $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment();
            if ($shipment) {
                $shipment->register();
                $shipment->getOrder()->setIsInProcess(true);
                // Add tracking information
                if ($tracking) {
                    $track = Mage::getModel('sales/order_shipment_track')
                        ->setNumber($tracking)
                        ->setCarrierCode($carrier)
                        ->setTitle($title);
                    $shipment->addTrack($track);
                }
                $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($shipment)
                    ->addObject($shipment->getOrder());
                $transactionSave->save();
                $this->_hasShipments = true;
                try {
                    $shipment->save();
                } catch (Mage_Core_Exception $e) {
                    Mage::helper('lensync/data')->log(
                        'ERROR create shipment : '.$e->getMessage(),
                        $order->getOrderIdLengow()
                    );
                }
            }
        }
    }

    /**
     * Cancel order
     *
     * @param Mage_Sales_Model_Order
     *
     */
    public function toCancel($order)
    {
        if ($this->_canCancel && $order->canCancel()) {
            $order->cancel();
            $this->_isCanceled = true;
        }
    }

    /**
     * Refund order
     *
     * @param Mage_Sales_Model_Order
     *
     * @return Lengow_Sync_Model_Order
     */
    public function toRefund(Lengow_Sync_Model_Order $order)
    {
        if ($this->_canRefund && $order->canCreditmemo()) {
            $invoice_id = $order->getInvoiceCollection()->getFirstItem()->getId();
            if (!$invoice_id) {
                return $this;
            }
            $invoice = Mage::getModel('sales/order_invoice')->load($invoice_id)->setOrder($order);
            $service = Mage::getModel('sales/service_order', $order);
            $creditmemo = $service->prepareInvoiceCreditmemo($invoice);
            $backToStock = array();
            foreach ($order->getAllItems() as $item) {
                $backToStock[$item->getId()] = true;
            }
            // Process back to stock flags
            foreach ($creditmemo->getAllItems() as $creditmemoItem) {
                $orderItem = $creditmemoItem->getOrderItem();
                $parentId = $orderItem->getParentItemId();
                if (Mage::helper('cataloginventory')->isAutoReturnEnabled()) {
                    $creditmemoItem->setBackToStock(true);
                } else {
                    $creditmemoItem->setBackToStock(false);
                }
            }
            $creditmemo->register();
            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($creditmemo)
                ->addObject($creditmemo->getOrder());
            if ($creditmemo->getInvoice()) {
                $transactionSave->addObject($creditmemo->getInvoice());
            }
            $transactionSave->save();
            $this->_isRefunded = true;
        }
        return $this;
    }

    /**
     * Retrieve country id based on country name
     *
     * @param string $country_name
     *
     * @return string
     */
    protected function _getCountryId($country_name)
    {
        if (is_null($this->_countryCollection)) {
            $this->_countryCollection = Mage::getResourceModel('directory/country_collection')->toOptionArray();
        }
        foreach ($this->_countryCollection as $country) {
            if (strtolower($country['label']) == strtolower($country_name)) {
                return $country['value'];
            }
        }
        return $country_name;
    }

    /**
     * Get Magento equivalent to lengow order state
     *
     * @param  string $lengow lengow state
     *
     * @return string
     */
    public function getOrderState($lengow)
    {
        switch ($lengow) {
            case 'new':
            case 'waiting_acceptance':
                return Mage_Sales_Model_Order::STATE_NEW;
                break;
            case 'accepted':
            case 'waiting_shipment':
                return Mage_Sales_Model_Order::STATE_PROCESSING;
                break;
            case 'shipped':
            case 'closed':
                return Mage_Sales_Model_Order::STATE_COMPLETE;
                break;
            case 'refused':
            case 'canceled':
                return Mage_Sales_Model_Order::STATE_CANCELED;
                break;
        }
    }

    /**
     * Update order state to marketplace state
     *
     * @param Mage_Sales_Model_Order $order Magento Order
     * @param string $lengow_status marketplace status
     * @param mixed $package package data
     *
     * @return bool true if order has been updated
     */
    public function updateState($order, $lengow_status, $package)
    {
        $helper = Mage::helper('lensync/data');
        // Update order's status only if in process, shipped, or canceled
        if ($order->getState() != self::getOrderState($lengow_status) && $order->getData('from_lengow') == 1) {
            if ($order->getState() == self::getOrderState('new')
                && ($lengow_status == 'accepted' || $lengow_status == 'waiting_shipment')
            ) {
                // Generate invoice
                $this->toInvoice($order);
                $helper->log(
                    'state updated to "processing" (Order '.$order->getIncrementId().')',
                    $order->getOrderIdLengow()
                );
                return true;
            } elseif (($order->getState() == self::getOrderState('accepted')
                    || $order->getState() == self::getOrderState('new')
                )
                && ($lengow_status == 'shipped' || $lengow_status == 'closed')
            ) {
                // if order is new -> generate invoice
                if ($order->getState() == self::getOrderState('new')) {
                    $this->toInvoice();
                }
                $trackings = $package->delivery->trackings;
                $this->toShip(
                    $order,
                    (count($trackings) > 0 ? (string)$trackings[0]->carrier : null),
                    (count($trackings) > 0 ? (string)$trackings[0]->method : null),
                    (count($trackings) > 0 ? (string)$trackings[0]->number : null)
                );
                $helper->log(
                    'state updated to "shipped" (Order '.$order->getIncrementId().')',
                    $order->getOrderIdLengow()
                );
                return true;
            } else {
                if (($order->getState() == self::getOrderState('new')
                        || $order->getState() == self::getOrderState('accepted')
                        || $order->getState() == self::getOrderState('shipped')
                    )
                    && ($lengow_status == 'canceled' || $lengow_status == 'refused')
                ) {
                    $this->toCancel($order);
                    $helper->log(
                        'state update to "canceled" (Order '.$order->getIncrementId().')',
                        $order->getOrderIdLengow()
                    );
                    return true;
                }
            }
        }
        return false;
    }
}
