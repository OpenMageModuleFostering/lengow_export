<?php

/**
 * Lengow sync helper api
 *
 * @category    Lengow
 * @package     Lengow_Sync
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Sync_Helper_Api extends Mage_Core_Helper_Abstract
{

    public function getVersion()
    {
        return (string)Mage::getConfig()->getNode()->modules->Lengow_Sync->version;
    }
}
