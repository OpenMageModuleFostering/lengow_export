<?php

/**
 * Lengow sync model import
 *
 * @category    Lengow
 * @package     Lengow_Sync
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Sync_Model_Import extends Varien_Object
{

    /**
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote = null;

    /**
     * @var Mage_Customer_Model_Customer
     */
    protected $_customer = null;

    protected $_ordersIdsImported = array();

    protected $_orderIdsAlreadyImported = array();

    protected $_result;

    protected $_resultSendOrder = "";

    protected $_isUnderVersion14 = null;

    /**
     * Product model
     *
     * @var Mage_Catalog_Model_Product
     */
    protected $_productModel;

    protected $_helper;

    protected $_connector;

    public static $import_start = false;

    /**
     * @var array states lengow to import
     */
    public static $STATES_LENGOW = array(
        'accepted',
        'waiting_shipment',
        'shipped',
        'closed',
    );

    protected $_debugMode;

    protected $_dateFrom;

    protected $_dateTo;

    protected $_config;

    protected $_idAccount;

    protected $_accessToken;

    protected $_secret;

    /**
     * Construct
     *
     * @param array $args
     *
     * @return Lengow_Sync_Model_Import
     */
    public function __construct($args)
    {
        parent::__construct();
        if (Mage::app()->getStore()->getCode() != 'admin') {
            Mage::app()->setCurrentStore('admin');
        }
        if (!is_array($args)) {
            return;
        }
        foreach ($args as $key => $value) {
            $this->{'_'.$key} = $value;
        }
        $this->_connector = Mage::getModel('lensync/connector');
        $this->_connector->init($this->_accessToken, $this->_secret);
        $this->_helper = Mage::helper('lensync/data');
        return $this;
    }

    /**
     * Execute import process
     *
     * @return array
     */
    public function exec()
    {
        self::$import_start = true;
        Mage::getSingleton('core/session')->setIsFromlengow('true');
        $orders = $this->getLengowOrders();
        $count_orders = count($orders);
        if ($count_orders === 0) {
            $this->_helper->log(
                'No orders to import between '
                .date('Y-m-d', strtotime((string)$this->_dateFrom))
                .' and '
                .date('Y-m-d', strtotime((string)$this->_dateTo))
            );
            return false;
        }
        $this->_helper->log(
            $count_orders.' order'.($count_orders > 1 ? 's ' : ' ').'found with account ID: '.$this->_idAccount
        );
        return $this->importOrders($orders);
    }

    /**
     * Retrieve Lengow orders
     *
     * @return array list of orders to be imported
     */
    protected function getLengowOrders()
    {
        $page = 1;
        $orders = array();
        if ($this->_connector->isValidAuth($this->_idAccount)) {
            $this->_helper->log(
                'Connector: get orders between '.date('Y-m-d', strtotime((string)$this->_dateFrom))
                .' and '.date('Y-m-d', strtotime((string)$this->_dateTo))
                .' with account ID: '.$this->_idAccount
            );
            do {
                if (defined('PHPUNIT_LENGOW_ACTIVE') === true) {
                    global $fileGetOrder;
                    $results = $fileGetOrder[$this->_idAccount];
                } else {
                    $results = $this->_connector->get(
                        '/v3.0/orders',
                        array(
                            'account_id'   => $this->_idAccount,
                            'updated_from' => $this->_dateFrom,
                            'updated_to'   => $this->_dateTo,
                            'page'         => $page
                        ),
                        'stream'
                    );
                }
                if (is_null($results)) {
                    throw new Exception('the connection didn\'t work with the Lengow webservice');
                }
                $results = json_decode($results);
                if (!is_object($results)) {
                    throw new Exception('the connection didn\'t work with the Lengow webservice');
                }
                if (isset($results->error)) {
                    throw new Exception(
                        'Error on lengow webservice : '.$results->error->code.' - '.$results->error->message
                    );
                }
                // Construct array orders
                foreach ($results->results as $order) {
                    $orders[] = $order;
                }
                $page++;
            } while ($results->next != null);
        } else {
            throw new Exception('Account ID, Token access or Secret are not valid');
        }
        return $orders;
    }

    /**
     * Imports all orders
     *
     * @param array $orders list of orders to be imported
     *
     * @return array Number of new and update orders
     */
    protected function importOrders($orders)
    {
        $count_orders_updated = 0;
        $count_orders_added = 0;
        $args = array(
            'connector' => $this->_connector,
            'accountId' => $this->_idAccount
        );
        $marketplace = Mage::getModel('lensync/marketplace', $args);
        foreach ($orders as $order_data) {
            $model_order = Mage::getModel('lensync/order');
            $model_order->setConfig($this->_config);
            $id_lengow_order = (string)$order_data->marketplace_order_id;
            if ($this->_config->isDebugMode() && !defined('PHPUNIT_LENGOW_ACTIVE')) {
                $id_lengow_order .= '--'.time();
            }
            // check if order has a status
            $marketplace_status = (string)$order_data->marketplace_status;
            if (empty($marketplace_status)) {
                $this->_helper->log('no order\'s status', $id_lengow_order);
                continue;
            }
            // convert marketplace status to Lengow equivalent
            $marketplace->set((string)$order_data->marketplace, $this->_idAccount);
            $lengow_status = $marketplace->getStateLengow($marketplace_status);
            // if order contains no package
            if (count($order_data->packages) == 0) {
                $this->_helper->log('create order fail: Lengow error: no package in the order', $id_lengow_order);
                continue;
            }
            // if first package -> import processing fees and shipping
            $first = true;
            foreach ($order_data->packages as $package) {
                // check whether the package contains a shipping address
                if (!isset($package->delivery->id)) {
                    $this->_helper->log('create order fail: Lengow error: no delivery address in the order', $id_lengow_order);
                    continue;
                }
                $delivery_address_id = (int)$package->delivery->id;
                // check order data
                if (!$this->checkOrderData($order_data, $package, $id_lengow_order)) {
                    continue;
                }
                // first check if not shipped by marketplace
                if (count($package->delivery->trackings) > 0
                    && (integer)$package->delivery->trackings[0]->is_delivered_by_marketplace == 1
                ) {
                    $this->_helper->log(
                        'delivery by marketplace ('.(string)$marketplace->name.')',
                        $id_lengow_order
                    );
                    continue;
                }
                // check if order has already been imported
                $id_order = $model_order->isAlreadyImported(
                    $id_lengow_order,
                    $delivery_address_id,
                    $marketplace->name,
                    $marketplace->legacy_code
                );
                if ($id_order) {
                    $order_imported = Mage::getModel('sales/order')->load($id_order);
                    $this->_helper->log(
                        'order already imported with order ID '.$order_imported->getIncrementId(),
                        $id_lengow_order
                    );
                    if ($model_order->updateState($order_imported, $lengow_status, $package)) {
                        $count_orders_updated++;
                    }
                } else {
                    // checks if an external id already exists
                    $id_order_magento = false;
                    $external_ids = $order_data->merchant_order_id;
                    foreach ($external_ids as $external_id) {
                        $result = $model_order->getLengowIdFromLengowDeliveryAddress(
                            (integer)$external_id,
                            $delivery_address_id
                        );
                        if ($result) {
                            $id_order_magento = $external_id;
                            break;
                        }
                    }
                    if ($this->_config->isDebugMode()) {
                        $id_order_magento = false;
                    }
                    // Import only process order or shipped order and not imported with previous module
                    if ($this->checkState($lengow_status) && !$id_order_magento) {
                        // Create or Update customer with addresses
                        $customer = Mage::getModel('lensync/customer_customer');
                        try {
                            $customer->setFromNode($order_data, $package->delivery, $this->_config);
                        } catch (Exception $e) {
                            $this->_helper->log('create customer fail: '.$e->getMessage(), $id_lengow_order);
                            continue;
                        }
                        // rewrite processing fees and shipping cost
                        if (!$this->_config->get('orders/processing_fee') || $first == false) {
                            $order_data->processing_fee = 0;
                            $this->_helper->log('rewrite amount without processing fee', $id_lengow_order);
                        }
                        if ($first == false) {
                            $order_data->commission = 0;
                            $order_data->shipping = 0;
                            $this->_helper->log('rewrite amount without shipping cost', $id_lengow_order);
                        }

                        // get total amount and shipping
                        $total_amount = 0;
                        foreach ($package->cart as $product) {
                            // check whether the product is canceled for amount
                            if ($product->marketplace_status != null) {
                                if ($marketplace->getStateLengow((string)$product->marketplace_status) == 'canceled'
                                    || $marketplace->getStateLengow((string)$product->marketplace_status) == 'refused'
                                ) {
                                    continue;
                                }
                            }
                            $total_amount += (float)$product->amount;
                        }
                        $order_amount = (float)$total_amount + (float)$order_data->processing_fee + (float)$order_data->shipping;
                        // create quote and order
                        try {
                            $quote = $this->_createQuote(
                                $id_lengow_order,
                                $order_data,
                                $package->cart,
                                $customer,
                                $marketplace,
                                $order_amount
                            );
                        } catch (Exception $e) {
                            $this->_helper->log('create quote fail: ' . $e->getMessage(), $id_lengow_order);
                            continue;
                        }
                        try {
                            $order = $this->makeOrder(
                                $id_lengow_order,
                                $order_data,
                                $package,
                                $quote,
                                $model_order,
                                $order_amount,
                                true
                            );
                        } catch (Exception $e) {
                            $this->_helper->log('create order fail: ' . $e->getMessage(), $id_lengow_order);
                        }
                        if ($order) {
                            // get all lines ids
                            $order_line_ids = array();
                            foreach ($package->cart as $product) {
                                $order_line_ids[] = (string)$product->marketplace_order_line_id;
                            }
                            // Save order line id in lengow_order_line table
                            $order_line_saved = false;
                            foreach ($order_line_ids as $order_line_id) {
                                $model_order->addLengowOrderLine($order, $order_line_id);
                                $order_line_saved .= (!$order_line_saved ? $order_line_id : ' / ' . $order_line_id);
                            }
                            if ($order_line_saved) {
                                $this->_helper->log('save order lines product : '.$order_line_saved, $id_lengow_order);
                            }
                            // Sync to lengow
                            if (!$this->_config->isDebugMode()) {
                                $order_ids = $model_order->getOrderIdFromLengowOrder(
                                    $id_lengow_order,
                                    (string)$marketplace->name
                                );
                                if (count($order_ids) > 0) {
                                    $magento_ids = array();
                                    foreach ($order_ids as $order_id) {
                                        $magento_ids[] = $order_id['entity_id'];
                                    }
                                    $result = $this->_connector->patch(
                                        '/v3.0/orders/moi/',
                                        array(
                                            'account_id'           => $this->_idAccount,
                                            'marketplace_order_id' => $id_lengow_order,
                                            'marketplace'          => $order_data->marketplace,
                                            'merchant_order_id'    => $magento_ids
                                        )
                                    );
                                    if (is_null($result)
                                        || (isset($result['detail']) && $result['detail'] == "Pas trouvé.")
                                        || isset($result['error'])
                                    ) {
                                        $this->_helper->log(
                                            'WARNING ! Order could NOT be synchronised with Lengow webservice (Order '.$order->getIncrementId().')',
                                            $id_lengow_order
                                        );
                                    } else {
                                        $this->_helper->log(
                                            'order successfully synchronised with Lengow webservice (Order '.$order->getIncrementId().')',
                                            $id_lengow_order
                                        );
                                    }
                                }
                            }
                            $count_orders_added++;
                            $this->_helper->log(
                                'order successfully imported (Order '.$order->getIncrementId().')',
                                $id_lengow_order
                            );
                            if ($lengow_status == 'shipped' || $lengow_status == 'closed') {
                                $trackings = $package->delivery->trackings;
                                $model_order->toShip(
                                    $order,
                                    (count($trackings) > 0 ? (string)$trackings[0]->carrier : null),
                                    (count($trackings) > 0 ? (string)$trackings[0]->method : null),
                                    (count($trackings) > 0 ? (string)$trackings[0]->number : null)
                                );
                                $this->_helper->log(
                                    'update state to "shipped" (Order '.$order->getIncrementId().')',
                                    $id_lengow_order
                                );
                            }
                            // export is finished for current order
                            $order->setExportFinishLengow(true);
                            $order->save();
                            // clean objects
                            unset($customer);
                            unset($quote);
                            unset($order);
                        }
                    } else {
                        if ($id_order_magento) {
                            $this->_helper->log(
                                'already imported in Magento with order ID '.$id_order_magento,
                                $id_lengow_order
                            );
                        } else {
                            $this->_helper->log(
                                'order\'s status ( '.$lengow_status.') not available to import',
                                $id_lengow_order
                            );
                        }
                    }
                }
                $first = false;
            }
            unset($model_order);
        }
        self::$import_start = false;
        // Clear session
        Mage::getSingleton('core/session')->clear();
        return array('new' => $count_orders_added, 'update' => $count_orders_updated);
    }

    /**
     * Checks if order data are present
     *
     * @param mixed $order_data
     * @param mixed $package
     * @param string $id_lengow_order
     *
     * @return boolean
     */
    protected function checkOrderData($order_data, $package, $id_lengow_order)
    {
        $error_message = false;

        if (count($package->cart) == 0) {
            $error_message = 'Lengow error: no product in the order';
        } elseif (is_null($order_data->currency)) {
            $error_message = 'Lengow error: no currency in the order';
        } elseif ($order_data->total_order == -1) {
            $error_message = 'Lengow error: no exchange rates available for order prices';
        } elseif (is_null($order_data->billing_address)) {
            $error_message = 'Lengow error: no billing address in the order';
        } elseif (is_null($order_data->billing_address->common_country_iso_a2)) {
            $error_message = 'Lengow error: billing address doesn\'t contain the country';
        } elseif (is_null($package->delivery->common_country_iso_a2)) {
            $error_message = 'Lengow error: delivery address doesn\'t contain the country';
        }
        if ($error_message) {
            $this->_helper->log('order import failed: '.$error_message, $id_lengow_order);
            return false;
        }
        return true;
    }

    /**
     * Create quote
     *
     * @param string $id_lengow_order
     * @param mixed $order_data
     * @param mixed $cart
     * @param Lengow_Sync_Model_Customer_Customer $customer
     * @param Lengow_Sync_Model_Marketplace $marketplace
     * @param float $order_amount
     *
     * @return
     */
    protected function _createQuote(
        $id_lengow_order,
        $order_data,
        $cart,
        Lengow_Sync_Model_Customer_Customer $customer,
        Lengow_Sync_Model_Marketplace $marketplace,
        $order_amount
    ) {
        $quote = Mage::getModel('lensync/quote')
            ->setIsMultiShipping(false)
            ->setStore($this->_config->getStore())
            ->setIsSuperMode(true); // set quote to supermode
        // import customer addresses into quote
        // Set billing Address
        $customer_billing_address = Mage::getModel('customer/address')
            ->load($customer->getDefaultBilling());
        $billing_address = Mage::getModel('sales/quote_address')
            ->setShouldIgnoreValidation(true)
            ->importCustomerAddress($customer_billing_address)
            ->setSaveInAddressBook(0);
        // Set shipping Address
        $customer_shipping_address = Mage::getModel('customer/address')
            ->load($customer->getDefaultShipping());
        $shipping_address = Mage::getModel('sales/quote_address')
            ->setShouldIgnoreValidation(true)
            ->importCustomerAddress($customer_shipping_address)
            ->setSaveInAddressBook(0)
            ->setSameAsBilling(0);
        $quote->assignCustomerWithAddressChange($customer, $billing_address, $shipping_address);
        // check if store include tax (Product and shipping cost)
        $priceIncludeTax = Mage::helper('tax')->priceIncludesTax($quote->getStore());
        $shippingIncludeTax = Mage::helper('tax')->shippingPriceIncludesTax($quote->getStore());
        // add product in quote
        $quote->addLengowProducts($cart, $marketplace, $id_lengow_order, $priceIncludeTax);
        // Get shipping cost with tax
        $shipping_cost = (float)$order_data->processing_fee + (float)$order_data->shipping;
        // if shipping cost not include tax -> get shipping cost without tax
        if (!$shippingIncludeTax) {
            $basedOn = Mage::getStoreConfig(Mage_Tax_Model_Config::CONFIG_XML_PATH_BASED_ON, $quote->getStore());
            $country_id = ($basedOn == 'shipping')
                ? $shipping_address->getCountryId()
                : $billing_address->getCountryId();
            $shippingTaxClass = Mage::getStoreConfig(
                Mage_Tax_Model_Config::CONFIG_XML_PATH_SHIPPING_TAX_CLASS,
                $quote->getStore()
            );
            $taxCalculator = Mage::getModel('tax/calculation');
            $taxRequest = new Varien_Object();
            $taxRequest->setCountryId($country_id)
                ->setCustomerClassId($customer->getTaxClassId())
                ->setProductClassId($shippingTaxClass);
            $tax_rate = (float)$taxCalculator->getRate($taxRequest);
            $tax_shipping_cost = (float)$taxCalculator->calcTaxAmount($shipping_cost, $tax_rate, true);
            $shipping_cost = $shipping_cost - $tax_shipping_cost;
        }
        // update shipping rates for current order
        $rates = $quote->getShippingAddress()
            ->setCollectShippingRates(true)
            ->collectShippingRates()
            ->getShippingRatesCollection();
        $shipping_method = $this->updateRates($rates, $id_lengow_order, $shipping_cost);
        // set shipping price and shipping method for current order
        $quote->getShippingAddress()
            ->setShippingPrice($shipping_cost)
            ->setShippingMethod($shipping_method);
        // collect totals
        $quote->collectTotals();
        // Re-ajuste cents for item quote
        // Conversion Tax Include > Tax Exclude > Tax Include maybe make 0.01 amount error
        if (!$priceIncludeTax) {
            if ($quote->getGrandTotal() != $order_amount) {
                $quote_items = $quote->getAllItems();
                foreach ($quote_items as $item) {
                    $row_total_lengow = (float)$quote->getRowTotalLengow((string)$item->getProduct()->getId());
                    if ($row_total_lengow != $item->getRowTotalInclTax()) {
                        $diff = $row_total_lengow - $item->getRowTotalInclTax();
                        $item->setPriceInclTax($item->getPriceInclTax() + ($diff / $item->getQty()));
                        $item->setBasePriceInclTax($item->getPriceInclTax());
                        $item->setPrice($item->getPrice() + ($diff / $item->getQty()));
                        $item->setOriginalPrice($item->getPrice());
                        $item->setRowTotal($item->getRowTotal() + $diff);
                        $item->setBaseRowTotal($item->getRowTotal());
                        $item->setRowTotalInclTax($row_total_lengow);
                        $item->setBaseRowTotalInclTax($item->getRowTotalInclTax());
                    }
                }
            }
        }
        // set payment method lengow
        $quote->getPayment()
            ->importData(
                array(
                    'method'      => 'lengow',
                    'marketplace' => (string)$order_data->marketplace.' - '.(string)(count($order_data->payments) > 0 ? $order_data->payments[0]->type : null),
                )
            );
        $quote->save();
        return $quote;
    }

    /**
     * Create order
     *
     * @param string $id_lengow_order
     * @param mixed $order_data
     * @param mixed $package
     * @param Lengow_Sync_Model_Quote $quote
     * @param Lengow_Sync_Model_Order $model_order
     * @param float $order_amount
     * @param boolean $invoice
     *
     * @return
     */
    protected function makeOrder(
        $id_lengow_order,
        $order_data,
        $package,
        Lengow_Sync_Model_Quote $quote,
        Lengow_Sync_Model_Order $model_order,
        $order_amount,
        $invoice = true
    ) {
        try {
            // get tracking informations
            $trackings = $package->delivery->trackings;
            $additional_data = array(
                'from_lengow'                => true,
                'export_finish_lengow'       => false,
                'marketplace_lengow'         => (string)$order_data->marketplace,
                'fees_lengow'                => (float)$order_data->commission,
                'order_id_lengow'            => (string)$id_lengow_order,
                'delivery_address_id_lengow' => (int)$package->delivery->id,
                'xml_node_lengow'            => Mage::helper('Core')->jsonEncode($order_data),
                'message_lengow'             => (string)$order_data->comments,
                'total_paid_lengow'          => (float)$order_amount,
                'carrier_lengow'             => (count($trackings) > 0 ? (string)$trackings[0]->carrier : null),
                'carrier_method_lengow'      => (count($trackings) > 0 ? (string)$trackings[0]->method : null),
                'carrier_tracking_lengow'    => (count($trackings) > 0 ? (string)$trackings[0]->number : null),
                'carrier_id_relay_lengow'    => (count($trackings) > 0 ? (string)$trackings[0]->relay->id : null),
                'global_currency_code'       => (string)$order_data->currency->iso_a3,
                'base_currency_code'         => (string)$order_data->currency->iso_a3,
                'store_currency_code'        => (string)$order_data->currency->iso_a3,
                'order_currency_code'        => (string)$order_data->currency->iso_a3
            );
            $service = Mage::getModel('sales/service_quote', $quote);
            $service->setOrderData($additional_data);
            $order = false;
            if (method_exists($service, 'submitAll')) {
                $service->submitAll();
                $order = $service->getOrder();
            } else {
                $order = $service->submit();
            }
            if (!$order) {
                throw new Exception('service unable to create order based on given quote');
            }
            $order->setIsFromLengow(true);
            // modify order dates to use actual dates
            if ($this->_config->get('orders/date_import')) {
                $date = date('Y-m-d H:i:s', strtotime((string)$order_data->marketplace_order_date));
                $order->setCreatedAt($date);
                $order->setUpdatedAt($date);
            }
            $order->save();
            // Re-ajuste cents for total and shipping cost
            // Conversion Tax Include > Tax Exclude > Tax Include maybe make 0.01 amount error
            $priceIncludeTax = Mage::helper('tax')->priceIncludesTax($quote->getStore());
            $shippingIncludeTax = Mage::helper('tax')->shippingPriceIncludesTax($quote->getStore());
            if (!$priceIncludeTax || !$shippingIncludeTax) {
                if ($order->getGrandTotal() != $order_amount) {
                    // check Grand Total
                    $diff = $order_amount - $order->getGrandTotal();
                    $order->setGrandTotal($order_amount);
                    $order->setBaseGrandTotal($order->getGrandTotal());
                    // if the difference is only on the grand total, removing the difference of shipping cost
                    if (($order->getSubtotalInclTax() + $order->getShippingInclTax()) == $order_amount) {
                        $order->setShippingAmount($order->getShippingAmount() + $diff);
                        $order->setBaseShippingAmount($order->getShippingAmount());
                    } else {
                        // check Shipping Cost
                        $diff_shipping = 0;
                        $shipping_cost = (float)$order_data->processing_fee + (float)$order_data->shipping;
                        if ($order->getShippingInclTax() != (float)$shipping_cost) {
                            $diff_shipping = ($shipping_cost - $order->getShippingInclTax());
                            $order->setShippingAmount($order->getShippingAmount() + $diff_shipping);
                            $order->setBaseShippingAmount($order->getShippingAmount());
                            $order->setShippingInclTax($shipping_cost);
                            $order->setBaseShippingInclTax($order->getShippingInclTax());
                        }
                        // update Subtotal without shipping cost
                        $order->setSubtotalInclTax($order->getSubtotalInclTax() + ($diff - $diff_shipping));
                        $order->setBaseSubtotalInclTax($order->getSubtotalInclTax());
                        $order->setSubtotal($order->getSubtotal() + ($diff - $diff_shipping));
                        $order->setBaseSubtotal($order->getSubtotal());
                    }
                }
                $order->save();
            }
            // generate invoice for order
            if ($invoice && $order->canInvoice()) {
                $model_order->toInvoice($order);
            }
            $carrier_name = '';
            if (count($trackings) > 0) {
                $carrier_name = (string)$trackings[0]->carrier;
                if ($carrier_name === 'None' || $carrier_name == '') {
                    $carrier_name = (string)$trackings[0]->method;
                }
            }
            $order->setShippingDescription(
                $order->getShippingDescription().' [marketplace shipping method : '.$carrier_name.']'
            );
            $order->save();
        } catch (Exception $e) {
            $this->_helper->log('error create order : '.$e->getMessage(), $id_lengow_order);
        }
        return $order;
    }

    /**
     * Update Rates with shipping cost
     *
     * @param Mage_Sales_Model_Quote_Address_Rate $rates
     * @param string $id_lengow_order
     * @param float $shipping_cost
     * @param string $shipping_method
     * @param boolean $first stop recursive effect
     *
     * @return boolean
     */
    protected function updateRates($rates, $id_lengow_order, $shipping_cost, $shipping_method = null, $first = true)
    {
        if (!$shipping_method) {
            $shipping_method = $this->_config->get('orders/default_shipping');
        }
        if (empty($shipping_method)) {
            $shipping_method = 'lengow_lengow';
        }
        foreach ($rates as &$rate) {
            // make sure the chosen shipping method is correct
            if ($rate->getCode() == $shipping_method) {
                if ($rate->getPrice() != $shipping_cost) {
                    $rate->setPrice($shipping_cost);
                    $rate->setCost($shipping_cost);
                }
                return $rate->getCode();
            }
        }
        // stop recursive effect
        if (!$first) {
            return 'lengow_lengow';
        }
        // get lengow shipping method if selected shipping method is unavailable
        $this->_helper->log(
            'the selected shipping method is unavailable for current order. Lengow shipping method assigned.',
            $id_lengow_order
        );
        return $this->updateRates($rates, $id_lengow_order, $shipping_cost, 'lengow_lengow', false);
    }

    /**
     * Check if order status is valid and is available for import
     *
     * @param string $order_state order state
     *
     * @return boolean
     */
    protected function checkState($lengow_status)
    {
        if (empty($lengow_status)) {
            return false;
        }
        if (!in_array($lengow_status, self::$STATES_LENGOW)) {
            return false;
        }
        return true;
    }
}
