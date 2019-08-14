<?php
/**
 * Lengow Sync model file
 *
 * @category    Lengow
 * @package     Lengow_Sync
 * @author      Team Connector <team-connector@lengow.com>
 * @copyright   2016 Lengow SAS 
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Lengow_Sync_Model_File extends Varien_Io_File {
	
    /**
	 * Override adding the LOCK_NB option
	 * @param bool $exclusive
	 * @return bool
	 */
	public function streamLock($exclusive = true)
    {
        if (!$this->_streamHandler) {
            return false;
        }
        $this->_streamLocked = true;
        $lock = $exclusive ? LOCK_EX : LOCK_SH;
        return flock($this->_streamHandler, $lock | LOCK_NB);
    }

    public function streamErase()
    {
        if (!$this->_streamHandler) {
            return false;
        }
        return ftruncate($this->_streamHandler, 0);
    }
}
