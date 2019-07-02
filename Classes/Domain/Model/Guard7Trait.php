<?php


namespace Domain\Model;


use SUDHAUS7\Guard7\Tools\Helper;
use SUDHAUS7\Guard7\Tools\Keys;
use SUDHAUS7\Guard7\Tools\Storage;

trait Guard7Trait
{
    
    /**
     * @var bool
     */
    private $_needsPersisting = false;

    final public function _hasNeedForPersisting() {
        return $this->_needsPersisting;
    }
    
    final public function _hasBeenForPersisted() {
        $this->_needsPersisting = false;
    }
    
    public function _isNew()
    {
        $isNew = parent::_isNew();
        if ($isNew) {
            $this->_needsPersisting = true;
        } else {
            // we hijack this function, as it is called just before persisting an object. We actually don't care if it is new..
            $table = Helper::getModelTable($this);
            $fields = Helper::getModelFields($this, $table);
            $pubKeys = Keys::collectPublicKeys($table, 0, (int)$this->getPid(), true);
            Storage::lockModel($this, $fields, $pubKeys, false);
        }
        return $isNew;
    }
    
    /**
     * @param $privateKey
     * @param null $password
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     */
    public function _unlock($privateKey, $password=null)
    {
        $table = Helper::getModelTable($this);
        Storage::unlockModel($this, $table, $privateKey, $password);
    }
}
