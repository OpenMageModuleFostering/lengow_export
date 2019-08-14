<?php

/**
 * Lengow tracker model config
 *
 * @category    Lengow
 * @package     Lengow_Tracker
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Tracker_Model_Config extends Varien_Object {

    protected $store;

    public function __construct($args = null)
    {
        parent::__construct();
        if (isset($args['store'])) {
            $this->setStore($args['store']);
        }
    }

    public function setStore($store)
    {
        $this->store = $store;
        return $this;
    }

    public function getStore()
    {
        return $this->store;
    }

    public function get($key)
    {
        if (is_null($this->store)) {
            $id_store = null;
        } else {
            $id_store = $this->store->getId();
        }
        return Mage::getStoreConfig('lentracker/'.$key, $id_store);
    }
}
