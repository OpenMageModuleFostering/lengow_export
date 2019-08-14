<?php

/**
 * Lengow_Tracker Tracking Block Simple
 *
 * @category   Lengow
 * @package    Lengow_Tracker
 * @author     Romain Le Polh <romain@lengow.com>
 */

class Lengow_Tracker_Block_Tag_Simple extends Mage_Core_Block_Template {
    
    protected $_config_model;
    protected $_id_client;
    protected $_id_group;
    protected $_id_order;
    protected $_total_paid;
    protected $_mode_paiement;
    protected $_ids_products;
    
    public function __construct() {
        $this->_config_model = Mage::getSingleton('tracker/config');
        $this->_id_client = $this->_config_model->get('general/login');
        $this->_id_group = $this->_config_model->get('general/group');
    }
    
    protected function _prepareLayout() {
        parent::_prepareLayout();
        $tracker_model = Mage::getSingleton('tracker/tracker');
        if(Mage::app()->getRequest()->getActionName() == 'success') {
            $order_id = Mage::getSingleton('checkout/session')->getLastOrderId();
            $order = Mage::getModel('sales/order')->load($order_id);
            $this->_mode_paiement = $order->getPayment()->getMethodInstance()->getCode();
            $this->_id_order = $order_id;
            $this->_total_paid = $order->getGrandTotal();
            $this->_ids_products = $tracker_model->getIdsProducts($order);
            $this->setTemplate('lengow/tracker/simpletag.phtml');
        }
        return $this;
    }
    
}