<?php

/**
 * Lengow_Tracker Tracking Block Simple
 *
 * @category    Lengow
 * @package     Lengow_Tracker
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Tracker_Block_Tag_Simple extends Mage_Core_Block_Template
{

    protected $_tracker_model;

    public function __construct()
    {
        $this->_tracker_model = Mage::getSingleton('lentracker/tracker');
    }

    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if (Mage::app()->getRequest()->getActionName() == 'success') {
            $order_id = Mage::getSingleton('checkout/session')->getLastOrderId();
            if ((int)Mage::getStoreConfig('lentracker/general/version2')) {
                $order = Mage::getModel('sales/order')->load($order_id);
                $this->setData(
                    'id_client',
                    Mage::getStoreConfig('lentracker/general/login', Mage::app()->getStore())
                );
                // explode group id => force 1 group only
                $id_groups = Mage::getStoreConfig('lentracker/general/group', Mage::app()->getStore());
                $id_groups = explode(',', $id_groups);
                $this->setData('id_group', $id_groups[0]);
                $this->setData('mode_paiement', $order->getPayment()->getMethodInstance()->getCode());
                $this->setData('id_order', $order_id);
                $this->setData('total_paid', $order->getGrandTotal());
                $this->setData('ids_products', $this->_tracker_model->getIdsProductsV2($order));
            }
            if ((int)Mage::getStoreConfig('lentracker/general/version3')) {
                $order = Mage::getModel('sales/order')->load($order_id);
                $this->setData(
                    'account_id',
                    Mage::getStoreConfig('lentracker/general/account_id', Mage::app()->getStore())
                );
                $cart = $this->_tracker_model->getIdsProducts($order);
                $this->setData('order_ref', $order_id);
                $this->setData('amount', $order->getGrandTotal());
                $this->setData('currency', $order->getOrderCurrencyCode());
                $this->setData('payment_method', $order->getPayment()->getMethodInstance()->getCode());
                $this->setData('cart', htmlspecialchars($cart));
                $this->setData('cart_number', $order->getQuoteId());
                $this->setData('newbiz', 1);
                $this->setData('valid', 1);
            }
            $this->setTemplate('lengow/tracker/simpletag.phtml');
        }
        return $this;
    }
}
