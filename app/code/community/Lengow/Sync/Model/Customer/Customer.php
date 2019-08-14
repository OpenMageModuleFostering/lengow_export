<?php

/**
 * Lengow sync model customer customer
 *
 * @category    Lengow
 * @package     Lengow_Sync
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Sync_Model_Customer_Customer extends Mage_Customer_Model_Customer
{

    /**
     * Convert array to customer model
     *
     * @param object $order_data
     * @param array $shipping_address
     * @param Lengow_Sync_Model_Config $config
     */
    public function setFromNode($order_data, $shipping_address, $config)
    {
        $id_store = $config->getStore()->getStoreId();
        $id_website = Mage::getModel('core/store')->load($id_store)->getWebsiteId();
        $array = array(
            'billing_address' => Mage::helper('lensync')->xmlToAssoc($order_data->billing_address),
            'delivery_address' => Mage::helper('lensync')->xmlToAssoc($shipping_address)
        );
        if (empty($array['billing_address']['email']) || $config->isDebugMode() || $config->get('orders/fake_email')) {
            $array['billing_address']['email'] = $order_data->marketplace_order_id . '-' . $order_data->marketplace . '@lengow.com';
        }

        // first get by email
        $this->setWebsiteId($id_website)
            ->loadByEmail($array['billing_address']['email']);

        if (!$this->getId()) {
            $this->setImportMode(true);
            $this->setWebsiteId($id_website);
            $this->setConfirmation(null);
            $this->setForceConfirmed(true);
            $this->setPasswordHash($this->hashPassword($this->generatePassword(8)));
            $this->setFromLengow(1);
        }

        // Billing address
        $temp_names = array(
            'firstname' => $array['billing_address']['first_name'],
            'lastname'  => $array['billing_address']['last_name'],
            'fullname'  => $array['billing_address']['full_name']
        );
        $billing_names = self::getNames($temp_names, $config->get('orders/split_name'));
        $array['billing_address']['first_name'] = $billing_names['firstname'];
        $array['billing_address']['last_name'] = $billing_names['lastname'];
        $billing_address = $this->convertAddress($array['billing_address']);
        $this->addAddress($billing_address);

        // Shipping address
        $temp_names = array(
            'firstname' => $array['delivery_address']['first_name'],
            'lastname'  => $array['delivery_address']['last_name'],
            'fullname'  => $array['delivery_address']['full_name']
        );
        $shipping_names = self::getNames($temp_names, $config->get('orders/split_name'));
        $array['delivery_address']['first_name'] = $shipping_names['firstname'];
        $array['delivery_address']['last_name'] = $shipping_names['lastname'];

        if (count($shipping_address->trackings) > 0 && !is_null($shipping_address->trackings[0]->relay->id)) {
            $array['delivery_address']['tracking_relay'] = $shipping_address->trackings[0]->relay->id;
        }
        $shipping_address = $this->convertAddress($array['delivery_address'], 'shipping');
        $this->addAddress($shipping_address);
        Mage::helper('core')->copyFieldset('lengow_convert_address', 'to_customer', $array['billing_address'], $this);

        // set group
        $this->setGroupId($config->get('orders/customer_group'));

        $this->save();
        return $this;
    }

    /**
     * Convert a array to customer address model
     *
     * @param array $data
     * @param string $type
     *
     * @return  Mage_Customer_Model_Address
     */
    public function convertAddress(array $data, $type = 'billing')
    {
        $address = Mage::getModel('customer/address');
        $address->setId(null);
        $address->setIsDefaultBilling(true);
        $address->setIsDefaultShipping(false);
        if ($type == 'shipping') {
            $address->setIsDefaultBilling(false);
            $address->setIsDefaultShipping(true);
        }
        Mage::helper('core')->copyFieldset('lengow_convert_address', 'to_' . $type . '_address', $data, $address);
        $address_1 = $data['first_line'];
        $address_2 = $data['second_line'];
        // Fix address 1
        if (empty($address_1) && !empty($address_2)) {
            $address_1 = $address_2;
            $address_2 = null;
        }
        // Fix address 2
        if (!empty($address_2)) {
            $address_1 = $address_1 . "\n" . $address_2;
        }
        $address_3 = $data['complement'];
        if (!empty($address_3)) {
            $address_1 = $address_1 . "\n" . $address_3;
        }
        // adding relay to address
        if (isset($data['tracking_relay'])) {
            $address_1 .= ' - Relay : ' . $data['tracking_relay'];
        }
        $address->setStreet($address_1);
        $tel_1 = $data['phone_office'];
        $tel_2 = $data['phone_mobile'];
        // Fix tel
        $tel_1 = empty($tel_1) ? $tel_2 : $tel_1;

        if (!empty($tel_1)) {
            $this->setTelephone($tel_1);
        }
        if (!empty($tel_1)) {
            $address->setFax($tel_1);
        } else {
            if (!empty($tel_2)) {
                $address->setFax($tel_2);
            }
        }
        $codeRegion = substr(str_pad($address->getPostcode(), 5, '0', STR_PAD_LEFT), 0, 2);
        $id_region = Mage::getModel('directory/region')->getCollection()
            ->addRegionCodeFilter($codeRegion)
            ->addCountryFilter($address->getCountry())
            ->getFirstItem()
            ->getId();
        $address->setRegionId($id_region);
        $address->setCustomer($this);
        return $address;
    }

    /**
     * Check if firstname or lastname are empty
     *
     * @param array $array
     * @param boolean $split
     *
     * @return array
     */
    protected static function getNames($array, $split)
    {
        if (empty($array['firstname'])) {
            if (!empty($array['lastname'])) {
                if ($split) {
                    $array = self::splitNames($array['lastname']);
                }
            }
        }
        if (empty($array['lastname'])) {
            if (!empty($array['firstname'])) {
                if ($split) {
                    $array = self::splitNames($array['firstname']);
                }
            }
        }
        // check full name if last_name and first_name are empty
        if (empty($array['lastname']) && empty($array['firstname'])) {
            $array = self::splitNames($array['fullname']);
        }
        if (empty($array['lastname'])) {
            $array['lastname'] = '__';
        }
        if (empty($array['firstname'])) {
            $array['firstname'] == '__';
        }

        return $array;
    }

    /**
     * Split fullname
     *
     * @param string $fullname
     *
     * @return array
     */
    protected static function splitNames($fullname)
    {
        $split = explode(' ', $fullname);
        if ($split && count($split)) {
            $names['firstname'] = $split[0];
            $names['lastname'] = '';
            for ($i = 1; $i < count($split); $i++) {
                if (!empty($names['lastname'])) {
                    $names['lastname'] .= ' ';
                }

                $names['lastname'] .= $split[$i];
            }
        } else {
            $names['firstname'] = '__';
            $names['lastname'] = empty($fullname) ? '__' : $fullname;
        }
        return $names;
    }
}
