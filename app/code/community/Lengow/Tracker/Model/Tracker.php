<?php

/**
 * Lengow tracker block tracker
 *
 * @category    Lengow
 * @package     Lengow_Tracker
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Tracker_Model_Tracker extends Varien_Object
{
    /**
     * Return list of order's items id
     *
     * @param $order Mage_Sales_Model_Order
     * @return string
     */
    public function getIdsProducts($quote)
    {
        if ($quote instanceof Mage_Sales_Model_Order || $quote instanceof Mage_Sales_Model_Quote) {
            $quote_items = $quote->getAllVisibleItems();
            $products_cart = array();
            foreach ($quote_items as $item) {
                if ($item->hasProduct()) {
                    $product = $item->getProduct();
                } else {
                    $product = Mage::getModel('catalog/product')->load($item->getProductId());
                }
                $quantity = (int) $item->getQtyOrdered();
                $price = round((float)$item->getRowTotalInclTax() / $quantity, 2);
                $product_datas = array(
                    'product_id' => $product->getData($this->_getIdentifier()),
                    'price'      => $price,
                    'quantity'   => $quantity
                );
                $products_cart[] = $product_datas;
            }
            return json_encode($products_cart);
        }
        return false;
    }

    /**
     * Return list of order's items id for v2 version
     *
     * @param $order Mage_Sales_Model_Order
     * @return string
     */
    public function getIdsProductsV2($quote)
    {
        if ($quote instanceof Mage_Sales_Model_Order || $quote instanceof Mage_Sales_Model_Quote) {
            $quote_items = $quote->getAllVisibleItems();
            $ids = array();
            foreach ($quote_items as $item) {
                if ($item->hasProduct()) {
                    $product = $item->getProduct();
                } else {
                    $product = Mage::getModel('catalog/product')->load($item->getProductId());
                }
                $ids[] = $product->getData($this->_getIdentifier());
            }
            return implode('|', $ids);
        }
        return false;
    }

    /**
     * Return list of order's items id
     *
     * @param $order Mage_Sales_Model_Order
     * @return string
     */
    protected function _getIdentifier()
    {
        $config = Mage::getModel('lentracker/config');
        return $config->get('tag/identifiant');
    }
}
