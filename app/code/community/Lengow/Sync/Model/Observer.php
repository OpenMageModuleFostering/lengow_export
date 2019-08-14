<?php

/**
 * Lengow sync model observer
 *
 * @category    Lengow
 * @package     Lengow_Sync
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Sync_Model_Observer
{

    protected $_alreadyShipped = array();

    protected $_alreadyShippedV2 = array();

    protected $_alreadyChecked = array();

    public function import($observer = null)
    {
        if (Mage::getStoreConfig('lensync/performances/active_cron')) {
            // update marketplace file
            Mage::helper('lensync/data')->updateMarketplaceXML();
            // clean old log (20 days)
            Mage::helper('lensync/data')->cleanLog();
            // check if import is not already in process
            if (!Mage::getSingleton('lensync/config')->importCanStart()) {
                Mage::helper('lensync/data')->log('## Error cron import : import is already started ##');
            } else {
                Mage::helper('lensync/data')->log('## Start cron import ##');
                if (Mage::getStoreConfig('lensync/performances/debug')) {
                    Mage::helper('lensync/data')->log('WARNING ! Debug mode is activated');
                }
                $result_new = 0;
                $result_update = 0;
                $lengow_groups = array();
                $lengow_id_accounts = array();
                $store_collection = Mage::getResourceModel('core/store_collection')->addFieldToFilter('is_active', 1);
                // Import different view if is different
                foreach ($store_collection as $store) {
                    try {
                        if (!$store->getId()) {
                            continue;
                        }
                        Mage::helper('lensync/data')->log(
                            'Start cron import in store '.$store->getName().' ('.$store->getId().')'
                        );
                        $lensync_config = Mage::getModel('lensync/config', array('store' => $store));
                        // if store is enabled -> stop import
                        if (!$lensync_config->get('orders/active_store')) {
                            Mage::helper('lensync/data')->log(
                                'Stop cron import - Store '.$store->getName().'('.$store->getId().') is disabled'
                            );
                            continue;
                        }
                        // start v2 import process
                        if ((int)Mage::getStoreConfig('lentracker/general/version2')) {
                            // get login informations
                            $error_import = false;
                            $lentracker_config = Mage::getModel('lentracker/config', array('store' => $store));
                            $id_lengow_customer = $lentracker_config->get('general/login');
                            $id_lengow_group = $this->_cleanGroup($lentracker_config->get('general/group'));
                            $api_token_lengow = $lentracker_config->get('general/api_key');
                            unset($lentracker_config);
                            // if ID Customer, ID Group or token API are empty -> stop import
                            if (empty($id_lengow_customer)
                                || !is_numeric($id_lengow_customer)
                                || empty($id_lengow_group)
                                || empty($api_token_lengow)
                            ) {
                                $store_name = $store->getName().'('.$store->getId().').';
                                $message = 'Please checks your plugin configuration. ID customer, ID group or token API is empty in store ';
                                Mage::helper('lensync/data')->log($message.$store_name);
                                $error_import = true;
                            }
                            // check if group was already imported
                            $new_id_lengow_group = false;
                            $id_groups = explode(',', $id_lengow_group);
                            foreach ($id_groups as $id_group) {
                                if (is_numeric($id_group) && !in_array($id_group, $lengow_groups)) {
                                    $lengow_groups[] = $id_group;
                                    $new_id_lengow_group .= !$new_id_lengow_group ? $id_group : ','.$id_group;
                                }
                            }
                            if (!$new_id_lengow_group) {
                                Mage::helper('lensync/data')->log(
                                    'ID group '.$id_lengow_group.' is already used by another store'
                                );
                            }
                            if (!$error_import && $new_id_lengow_group) {
                                $days = $lensync_config->get('orders/period');
                                $args = array(
                                    'dateFrom'   => date('Y-m-d', strtotime(date('Y-m-d').'-'.$days.'days')),
                                    'dateTo'     => date('Y-m-d'),
                                    'config'     => $lensync_config,
                                    'idCustomer' => $id_lengow_customer,
                                    'idGroup'    => $new_id_lengow_group,
                                    'apiToken'   => $api_token_lengow,
                                );
                                $import = Mage::getModel('lensync/importv2', $args);
                                $result = $import->exec();
                                $result_new += $result['new'];
                                $result_update += $result['update'];
                            }
                        }
                        // start v3 import process
                        if ((int)Mage::getStoreConfig('lentracker/general/version3')) {
                            $error_import = false;
                            $lentracker_config = Mage::getModel('lentracker/config', array('store' => $store));
                            $id_account = (integer)$lentracker_config->get('general/account_id');
                            $access_token = $lentracker_config->get('general/access_token');
                            $secret = $lentracker_config->get('general/secret');
                            // if ID Account, Access Token or Secret are empty -> stop import
                            if (empty($id_account)
                                || !is_numeric($id_account)
                                || empty($access_token)
                                || empty($secret)
                            ) {
                                $store_name = $store->getName().'('.$store->getId().').';
                                $message = 'Please checks your plugin configuration. ID account, access token or secret is empty in store ';
                                Mage::helper('lensync/data')->log($message.$store_name);
                                $error_import = true;
                            }
                            // check if id_account was already imported
                            $new_id_account = false;
                            if (is_numeric($id_account) && !in_array($id_account, $lengow_id_accounts)) {
                                $lengow_id_accounts[] = $id_account;
                                $new_id_account = $id_account;
                            } else {
                                Mage::helper('lensync/data')->log(
                                    'ID account '.$id_account.' is already used by another store'
                                );
                            }
                            // star import for actual store
                            if (!$error_import && $new_id_account) {
                                $days = $lensync_config->get('orders/period');
                                $args = array(
                                    'dateFrom'    => date('c', strtotime(date('Y-m-d').'-'.$days.'days')),
                                    'dateTo'      => date('c'),
                                    'config'      => $lensync_config,
                                    'idAccount'   => $new_id_account,
                                    'accessToken' => $access_token,
                                    'secret'      => $secret,
                                );
                                $import = Mage::getModel('lensync/import', $args);
                                $result = $import->exec();
                                $result_new += $result['new'];
                                $result_update += $result['update'];
                            }
                        }
                    } catch (Exception $e) {
                        Mage::helper('lensync/data')->log('Error '.$e->getMessage());
                    }
                }
                if ($result_new > 0) {
                    Mage::helper('lensync/data')->log(
                        Mage::helper('lensync')->__('%d orders are imported', $result_new)
                    );
                }
                if ($result_update > 0) {
                    Mage::helper('lensync/data')->log(
                        Mage::helper('lensync')->__('%d orders are updated', $result_update)
                    );
                }
                if ($result_new == 0 && $result_update == 0) {
                    Mage::helper('lensync/data')->log(
                        Mage::helper('lensync')->__('No order available to import')
                    );
                }
                Mage::helper('lensync/data')->log('## End cron import ##');
                Mage::getSingleton('lensync/config')->importSetEnd();
            }
        }
        return $this;
    }

    /**
     * Sending a call WSDL for a new order shipment
     */
    public function salesOrderShipmentSaveAfter(Varien_Event_Observer $observer)
    {
        $shipment = $observer->getEvent()->getShipment();
        $order = $shipment->getOrder();
        if ((int)Mage::getStoreConfig('lentracker/general/version2')) {
            if ($order->getData('from_lengow') == 1
                && !array_key_exists($order->getData('order_id_lengow'), $this->_alreadyShippedV2)
                && $order->getData('feed_id_lengow') != 0
            ) {
                $marketplace = Mage::getModel('lensync/marketplacev2');
                $marketplace->set($order->getMarketplaceLengow());
                if ($order->getState() == Mage::getSingleton('lensync/orderv2')->getOrderState('processing')) {
                    Mage::helper('lensync')->log(
                        'WSDL : send tracking to '.$order->getData('marketplace_lengow').' - '.$order->getData('feed_id_lengow'),
                        $order->getData('order_id_lengow')
                    );
                    $marketplace->wsdl('shipped', $order->getData('feed_id_lengow'), $order, $shipment);
                    $this->_alreadyShippedV2[$order->getData('order_id_lengow')] = true;
                }
            }
        }
        if ((int)Mage::getStoreConfig('lentracker/general/version3')) {
            if ($order->getData('from_lengow') == 1
                && ($order->getData('export_finish_lengow') == 1 || is_null($order->getData('export_finish_lengow')))
                && $order->getState() == Mage::getSingleton('lensync/order')->getOrderState('accepted')
                && !array_key_exists($order->getData('order_id_lengow'), $this->_alreadyShipped)
            ) {
                $store = $order->getStore();
                $connector = Mage::helper('lensync/data')->getConnectorByStore($store);
                if ($connector) {
                    $account_id = (integer)Mage::getStoreConfig('lentracker/general/account_id', $store);
                    $args = array(
                        'connector' => $connector,
                        'accountId' => $account_id
                    );
                    $marketplace = Mage::getModel('lensync/marketplace', $args);
                    // Compatibility V2
                    if ($order->getData('feed_id_lengow') != 0  && !array_key_exists($order->getData('order_id_lengow'), $this->_alreadyChecked)) {
                        $order = $marketplace->checkAndChangeMarketplaceName($order);
                        $this->_alreadyChecked[$order->getData('order_id_lengow')] = true;
                    }
                    $marketplace->set($order->getData('marketplace_lengow'));
                    if ($marketplace->isLoaded()) {
                        Mage::helper('lensync/data')->log(
                            'WSDL : send tracking to '.$order->getData('marketplace_lengow').' with account id '.$account_id,
                            $order->getData('order_id_lengow')
                        );
                        $marketplace->wsdl('ship', $order->getData('order_id_lengow'), $order, $shipment);
                    }
                    $this->_alreadyShipped[$order->getData('order_id_lengow')] = true;
                } else {
                    Mage::helper('lensync')->log(
                        'WSDL : call canceled - Account ID, Token access or Secret are not valid in store '.$store->getName().'('.$store->getId().')',
                        $order->getData('order_id_lengow')
                    );
                }
            }
        }
        return $this;
    }

    /**
     * Sending a call WSDL for a new tracking
     */
    public function salesOrderShipmentTrackSaveAfter(Varien_Event_Observer $observer)
    {
        $track = $observer->getEvent()->getTrack();
        $shipment = $track->getShipment();
        $order = $shipment->getOrder();
        if ((int)Mage::getStoreConfig('lentracker/general/version2')) {
            if ($order->getData('from_lengow') == 1
                && !array_key_exists($order->getData('order_id_lengow'), $this->_alreadyShippedV2)
                && $order->getData('feed_id_lengow') != 0
            ) {
                $marketplace = Mage::getModel('lensync/marketplacev2');
                $marketplace->set($order->getMarketplaceLengow());
                if ($order->getState() == Mage::getSingleton('lensync/orderv2')->getOrderState('shipped')) {
                    Mage::helper('lensync')->log(
                        'WSDL : send tracking to '.$order->getData('marketplace_lengow').' - '.$order->getData('feed_id_lengow'),
                        $order->getData('order_id_lengow')
                    );
                    $marketplace->wsdl('shipped', $order->getData('feed_id_lengow'), $order, $shipment);
                    $this->_alreadyShippedV2[$order->getData('order_id_lengow')] = true;
                }
            }
        }
        if ((int)Mage::getStoreConfig('lentracker/general/version3')) {
            if ($order->getData('from_lengow') == 1
                && ($order->getData('export_finish_lengow') == 1 || is_null($order->getData('export_finish_lengow')))
                && $order->getState() == Mage::getSingleton('lensync/order')->getOrderState('shipped')
                && !array_key_exists($order->getData('order_id_lengow'), $this->_alreadyShipped)
            ) {
                $store = $order->getStore();
                $connector = Mage::helper('lensync/data')->getConnectorByStore($store);
                if ($connector) {
                    $account_id = (integer)Mage::getStoreConfig('lentracker/general/account_id', $store);
                    $args = array(
                        'connector' => $connector,
                        'accountId' => $account_id
                    );
                    $marketplace = Mage::getModel('lensync/marketplace', $args);
                    // Compatibility V2
                    if ($order->getData('feed_id_lengow') != 0  && !array_key_exists($order->getData('order_id_lengow'), $this->_alreadyChecked)) {
                        $order = $marketplace->checkAndChangeMarketplaceName($order);
                        $this->_alreadyChecked[$order->getData('order_id_lengow')] = true;
                    }
                    $marketplace->set($order->getData('marketplace_lengow'));
                    if ($marketplace->isLoaded()) {
                        Mage::helper('lensync/data')->log(
                            'WSDL : send tracking to '.$order->getData('marketplace_lengow').' with account id '.$account_id,
                            $order->getData('order_id_lengow')
                        );
                        $marketplace->wsdl('ship', $order->getData('order_id_lengow'), $order, $shipment, $track);
                    }
                    $this->_alreadyShipped[$order->getData('order_id_lengow')] = true;
                } else {
                    Mage::helper('lensync')->log(
                        'WSDL : call canceled - Account ID, Token access or Secret are not valid in store '.$store->getName().'('.$store->getId().')',
                        $order->getData('order_id_lengow')
                    );
                }
            }
        }
        return $this;
    }

    /**
     * Sending a call for a cancellation of order
     */
    public function salesOrderPaymentCancel(Varien_Event_Observer $observer)
    {
        $payment = $observer->getEvent()->getPayment();
        $order = $payment->getOrder();
        if ((int)Mage::getStoreConfig('lentracker/general/version2')) {
            if ($order->getData('from_lengow') == 1) {
                $marketplace = Mage::getModel('lensync/marketplacev2');
                $marketplace->set($order->getMarketplaceLengow());
                if ($order->getState() == Mage::getSingleton('lensync/orderv2')->getOrderState('processing')) {
                    Mage::helper('lensync')->log(
                        'WSDL : send cancel to '.$order->getData('marketplace_lengow').' - '.$order->getData('feed_id_lengow'),
                        $order->getData('order_id_lengow')
                    );
                    $marketplace->wsdl('refuse', $order->getData('feed_id_lengow'), $order);
                }
            }
        }
        if ((int)Mage::getStoreConfig('lentracker/general/version3')) {
            if ($order->getData('from_lengow') == 1
                && $order->getData('export_finish_lengow') == 1
                && $order->getState() == Mage::getSingleton('lensync/order')->getOrderState('accepted')
            ) {
                $store = $order->getStore();
                $connector = Mage::helper('lensync/data')->getConnectorByStore($store);
                if ($connector) {
                    $account_id = (integer)Mage::getStoreConfig('lentracker/general/account_id', $store);
                    $args = array(
                        'connector' => $connector,
                        'accountId' => $account_id
                    );
                    $marketplace = Mage::getModel('lensync/marketplace', $args);
                    // Compatibility V2
                    if ($order->getData('feed_id_lengow') != 0) {
                        $order = $marketplace->checkAndChangeMarketplaceName($order);
                    }
                    $marketplace->set($order->getData('marketplace_lengow'));
                    if ($marketplace->isLoaded()) {
                        Mage::helper('lensync')->log(
                            'WSDL : send cancel to ' . $order->getData('marketplace_lengow').' with account id '.$account_id,
                            $order->getData('order_id_lengow')
                        );
                        $marketplace->wsdl('cancel', $order->getData('order_id_lengow'), $order);
                    }
                } else {
                    Mage::helper('lensync')->log(
                        'WSDL : call canceled - Account ID, Token access or Secret are not valid in store '.$store->getName().'('.$store->getId().')',
                        $order->getData('order_id_lengow')
                    );
                }
            }
        }
        return $this;
    }

    /**
     *  Clean group id
     *
     * @param string $data
     */
    private function _cleanGroup($data)
    {
        return trim(str_replace(array("\r\n", ';', '-', '|', ' '), ',', $data), ',');
    }
}
