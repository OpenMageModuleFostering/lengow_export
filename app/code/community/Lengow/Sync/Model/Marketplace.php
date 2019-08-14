<?php

/**
 * Lengow sync model marketplace
 *
 * @category    Lengow
 * @package     Lengow_Sync
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Sync_Model_Marketplace
{

    public static $VALID_ACTIONS = array(
        'ship',
        'cancel'
    );

    const MARKETPLACE_STATUS_NO_MATCH = -1;

    public $MARKETPLACES;

    public $marketplace;

    public $name;

    public $legacy_code;

    public $is_loaded = false;

    public $states_lengow = array();

    public $states = array();

    public $actions = array();

    public $arg_values = array();

    public $carriers = array();

    protected $_connector;

    protected $_accountId;

    /**
     * Construct
     *
     * @param array $args
     *
     * @return Lengow_Sync_Model_Marketplace
     */
    public function __construct($args)
    {
        if (!is_array($args)) {
            return;
        }
        foreach ($args as $key => $value) {
            $this->{'_'.$key} = $value;
        }
    }

    /**
     * Load a new Markerplace instance.
     *
     * @param string $name The name of the marketplace
     */
    public function set($name)
    {
        if ($this->_loadApiMarketplace()) {
            $this->name = strtolower($name);
            if (isset($this->MARKETPLACES->{$this->name})) {
                $this->marketplace = $this->MARKETPLACES->{$this->name};
                if (!empty($this->marketplace)) {
                    $this->legacy_code = $this->marketplace->legacy_code;
                    foreach ($this->marketplace->orders->status as $key => $state) {
                        foreach ($state as $value) {
                            $this->states_lengow[(string)$value] = (string)$key;
                            $this->states[(string)$key][(string)$value] = (string)$value;
                        }
                    }
                    foreach ($this->marketplace->orders->actions as $key => $action) {
                        foreach ($action->status as $state) {
                            $this->actions[(string)$key]['status'][(string)$state] = (string)$state;
                        }
                        foreach ($action->args as $arg) {
                            $this->actions[(string)$key]['args'][(string)$arg] = (string)$arg;
                        }
                        foreach ($action->optional_args as $optional_arg) {
                            $this->actions[(string)$key]['optional_args'][(string)$optional_arg] = $optional_arg;
                        }
                        foreach ($action->args_description as $key => $arg_description) {
                            $valid_values = array();
                            if (isset($arg_description->valid_values)) {
                                foreach ($arg_description->valid_values as $code => $valid_value) {
                                    $valid_values[(string)$code] = (string)$valid_value->label;
                                }
                            }
                            $this->arg_values[(string)$key] = array(
                                'default_value'      => (string)$arg_description->default_value,
                                'accept_free_values' => (bool)$arg_description->accept_free_values,
                                'valid_values'       => $valid_values
                            );
                        }
                    }
                    if (isset($this->marketplace->orders->carriers)) {
                        foreach ($this->marketplace->orders->carriers as $key => $carrier) {
                            $this->carriers[(string)$key] = (string)$carrier->label;
                        }
                    }
                    $this->is_loaded = true;
                }
            }
        }
    }

    /**
     * Load the json configuration of all marketplaces
     *
     * @param integer $account_id
     *
     * @return boolean
     */
    private function _loadApiMarketplace()
    {
        if (!$this->MARKETPLACES) {
            if ($this->_connector->isValidAuth($this->_accountId)) {
                $results = $this->_connector->get(
                    '/v3.0/marketplaces',
                    array(
                        'account_id' => $this->_accountId
                    ),
                    'stream'
                );
                $this->MARKETPLACES = json_decode($results);
                return true;
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * If marketplace exist in xml configuration file
     *
     * @return boolean
     */
    public function isLoaded()
    {
        return $this->is_loaded;
    }

    /**
     * Get the real lengow's state
     *
     * @param string $name The marketplace state
     *
     * @return string The lengow state
     */
    public function getStateLengow($name)
    {
        return array_key_exists($name, $this->states_lengow)
            ? $this->states_lengow[$name]
            : self::MARKETPLACE_STATUS_NO_MATCH;
    }

    /**
     * Get the marketplace's state
     *
     * @param string $name The lengow state
     *
     * @return string The marketplace state
     */
    public function getState($name)
    {
        return array_key_exists($name, $this->states) ? $this->states[$name] : self::MARKETPLACE_STATUS_NO_MATCH;
    }

    /**
     * Get the action with parameters
     *
     * @param string $name The action's name
     *
     * @return array
     */
    public function getAction($name)
    {
        return array_key_exists($name, $this->actions) ? $this->actions[$name] : false;
    }

    /**
     * If action exist
     *
     * @param string $name The marketplace state
     *
     * @return boolean
     */
    public function isAction($name)
    {
        return array_key_exists($name, $this->actions) ? true : false;
    }

    /**
     * Return array of marketplaces from the marketplaces.xml file
     *
     * @return array $marketplaces
     */
    public function getMarkeplaces()
    {
        $marketplaces = array();
        if ($this->_loadApiMarketplace()) {
            foreach ($this->MARKETPLACES as $key => $marketplace) {
                $marketplaces[(string)$key] = (string)$key;
            }
            ksort($marketplaces);
        }
        return $marketplaces;
    }

    /**
     * Check if a status is valid for action
     *
     * @param array $action_status valid status for action
     * @param string $current_status curent status id
     *
     * @return boolean
     */
    public function isValidState($action_status, $current_status)
    {
        $model_order = Mage::getModel('lensync/order');
        foreach ($action_status as $status) {
            if ($current_status == $model_order->getOrderState($status)) {
                return true;
            }
        }
        return false;
    }

    /**
    * Get the default value for argument
    *
    * @param string $name The argument's name
    *
    * @return mixed
    */
    public function getDefaultValue($name)
    {
        if (array_key_exists($name, $this->arg_values)) {
            $default_value = $this->arg_values[$name]['default_value'];
            if (!empty($default_value)) {
                return $default_value;
            }
        }
        return false;
    }

    /**
     * Call the Lengow WSDL for current marketplace
     *
     * @param string                            $action             The name of the action
     * @param string                            $id_lengow_order    Lengow Order ID
     * @param Mage_Sales_Model_Order            $order              Magento order
     * @param Mage_Sales_Model_Order_Shipment   $shipment           Magento shipment
     * @param string                            $args               An array of arguments
     *
     */
    public function wsdl(
        $action,
        $id_lengow_order,
        Mage_Sales_Model_Order $order,
        Mage_Sales_Model_Order_Shipment $shipment,
        $args = array()
    ) {
        if (!in_array($action, self::$VALID_ACTIONS)) {
            return false;
        }
        if (!$this->isAction($action)) {
            return false;
        }
        $store = $order->getStore();
        $lensync_config = Mage::getModel('lensync/config', array('store' => $store));
        $action_array = $this->getAction($action);
        if (!$action_array) {
            return false;
        }
        $params = array(
            'account_id' => $this->_accountId,
            'marketplace_order_id' => (string)$id_lengow_order,
            'marketplace' => (string)$order->getData('marketplace_lengow')
        );
        if (isset($action_array['optional_args'])) {
            $all_args = array_merge($action_array['args'], $action_array['optional_args']);
        } else {
            $all_args = $action_array['args'];
        }
        switch ($action) {
            case 'ship':
                $params['action_type'] = 'ship';
                if (isset($action_array['args'])) {
                    foreach ($all_args as $arg) {
                        switch ($arg) {
                            case 'tracking_number':
                                $trackings = $shipment->getAllTracks();
                                if (!empty($trackings)) {
                                    $last_track = end($trackings);
                                }
                                $params[$arg] = isset($last_track) ? $last_track->getNumber() : '';
                                break;
                            case 'carrier':
                            case 'shipping_method':
                                $trackings = $shipment->getAllTracks();
                                if (!empty($trackings)) {
                                    $last_track = end($trackings);
                                }
                                $params[$arg] = isset($last_track)
                                    ? $this->_matchCarrier($last_track->getCarrierCode(), $last_track->getTitle())
                                    : '';
                                break;
                            case 'shipping_price':
                                $params[$arg] = $order->getShippingInclTax();
                                break;
                            case 'shipping_date':
                                $params[$arg] = date('c');
                                break;
                            default:
                                $default_value = $this->getDefaultValue((string)$arg);
                                $param_value = $default_value ? $default_value : $arg.' not available';
                                $params[$arg] = $param_value;
                                break;
                        }
                    }
                }
                break;
            case 'cancel':
                $params['action_type'] = 'cancel';
                if (isset($all_args['args'])) {
                    foreach ($all_args as $arg) {
                        switch ($arg) {
                            default:
                                $default_value = $this->getDefaultValue((string)$arg);
                                $param_value = $default_value ? $default_value : $arg.' not available';
                                $params[$arg] = $param_value;
                                break;
                        }
                    }
                }
                break;
        }
        try {
            // if line_id is a required parameter -> send a call for each line_id
            if (in_array('line', $all_args)) {
                $order_line_sent = false;
                $order_lines = Mage::getModel('lensync/order')->getOrderLineFromIdOrder((integer)$order->getId());
                if ($order_lines) {
                    foreach ($order_lines as $order_line) {
                        $params['line'] = $order_line['id_order_line'];
                        if (!$lensync_config->isDebugMode()) {
                            $result = $this->_connector->post('/v3.0/orders/actions/', $params);
                            if (isset($result->id)) {
                                Mage::helper('lensync/data')->log(
                                    'WSDL : action '.$action.' successfully sent',
                                    $id_lengow_order
                                );
                            } else {
                                Mage::helper('lensync/data')->log(
                                    'WSDL : WARNING ! action '.$action.' could NOT be sent: '.json_encode($result),
                                    $id_lengow_order
                                );
                            }
                        }
                        // Get all params send
                        $param_list = false;
                        foreach ($params as $param => $value) {
                            $param_list.= (!$param_list ? '"'.$param.'": '.$value : ' -- "'.$param.'": '.$value);
                        }
                        Mage::helper('lensync/data')->log('WSDL : '.$param_list, $id_lengow_order);
                    }
                }
            } else {
                if (!$lensync_config->isDebugMode()) {
                    $result = $this->_connector->post('/v3.0/orders/actions/', $params);
                    if (isset($result->id)) {
                        Mage::helper('lensync/data')->log(
                            'WSDL : action '.$action.' successfully sent',
                            $id_lengow_order
                        );
                    } else {
                        Mage::helper('lensync/data')->log(
                            'WSDL : WARNING ! action '.$action.' could NOT be sent: '.json_encode($result),
                            $id_lengow_order
                        );
                    }
                }
                // Get all params send
                $param_list = false;
                foreach ($params as $param => $value) {
                    $param_list.= (!$param_list ? '"'.$param.'": '.$value : ' -- "'.$param.'": '.$value);
                }
                Mage::helper('lensync/data')->log('WSDL : '.$param_list, $id_lengow_order);
            }
        } catch (Exception $e) {
            Mage::helper('lensync/data')->log('call error WSDL - exception: '.$e->getMessage(), $id_lengow_order);
        }
    }

    /**
     * Match carrier's name with accepted values
     *
     * @param string $code
     * @param string $title
     *
     * @return string The matching carrier name
     */
    private function _matchCarrier($code, $title)
    {
        // no carrier
        if (count($this->carriers) == 0) {
            if ($code == 'custom') {
                return $title;
            }
            return $code;
        }
        // search by code
        // exact match
        foreach ($this->carriers as $key => $carrier) {
            $value = (string)$key;
            if (preg_match('`'.$value.'`i', trim($code))) {
                return $value;
            }
        }
        // approximately match
        foreach ($this->carriers as $key => $carrier) {
            $value = (string)$key;
            if (preg_match('`.*?'.$value.'.*?`i', $code)) {
                return $value;
            }
        }
        // search by title
        if (strtoupper($title) == 'GLS S') {
            $title = 'GLS';
        }
        // exact match
        foreach ($this->carriers as $key => $carrier) {
            $value = (string)$key;
            if (preg_match('`'.$value.'`i', trim($title))) {
                return $value;
            }
        }
        // approximately match
        foreach ($this->carriers as $key => $carrier) {
            $value = (string)$key;
            if (preg_match('`.*?'.$value.'.*?`i', $title)) {
                return $value;
            }
        }
        // no match
        if ($code == 'custom') {
            return $title;
        }
        return $code;
    }

    /**
     * Check and change the name of the marketplace for v3 compatibility
     *
     * @param Mage_Sales_Model_Order  $order  Magento order
     *
     * @return Mage_Sales_Model_Order
     */
    public function checkAndChangeMarketplaceName(Mage_Sales_Model_Order $order)
    {
        $results = $this->_connector->get(
            '/v3.0/orders',
            array(
                'marketplace_order_id' => (string)$order->getData('order_id_lengow'),
                'marketplace'          => (string)$order->getData('marketplace_lengow'),
                'account_id'           => $this->_accountId
            ),
            'stream'
        );
        if (is_null($results)) {
            return $order;
        }
        $results = json_decode($results);
        if (!is_object($results)) {
            return $order;
        }
        if (isset($results->error)) {
            return $order;
        }
        foreach ($results->results as $result) {
            if ((string)$order->getData('marketplace_lengow') != (string)$result->marketplace) {
                $order->setMarketplaceLengow((string)$result->marketplace);
                $order->save();
            }
        }
        return $order;
    }
}
