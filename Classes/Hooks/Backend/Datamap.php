<?php
/**
 * Created by PhpStorm.
 * User: frank
 * Date: 31.01.18
 * Time: 14:38
 */

namespace SUDHAUS7\Guard7\Hooks\Backend;

use SUDHAUS7\Guard7\Tools\Encoder;
use SUDHAUS7\Guard7\Tools\Keys;
use SUDHAUS7\Guard7\Tools\Storage;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Datamap implements SingletonInterface
{
    protected $insertCache = [];
    
    /**
     * @param $status
     * @param $table
     * @param $id
     * @param $fieldArray
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $pObj
     */
    public function processDatamap_afterDatabaseOperations($status, $table, $id, &$fieldArray, \TYPO3\CMS\Core\DataHandling\DataHandler &$pObj)
    {
        if ($status === 'new') {
            if (isset($this->insertCache[$table]) && isset($this->insertCache[$table][$id]) && is_array($this->insertCache[$table][$id])) {
                $newid = $pObj->substNEWwithIDs[$id];
                /** @var Connection $connection */
                $connection = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getConnectionForTable('tx_guard7_domain_model_data');
                foreach ($this->insertCache[$table][$id] as $data) {
                    $connection->insert('tx_guard7_domain_model_data', [
                        'tablename' => $table,
                        'tableuid' => $newid,
                        'fieldname' => $data['fieldname'],
                        'secretdata' => $data['encoded'],
                    ]);
                    $insertid = $connection->lastInsertId();
                    Storage::updateKeyLog($insertid, $data['pubkeys']);
                }
            }
        }
    }
    
    /**
     * @param $incomingFieldArray
     * @param $table
     * @param $id
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $pObj
     */
    public function processDatamap_preProcessFieldArray(&$incomingFieldArray, $table, $id, \TYPO3\CMS\Core\DataHandling\DataHandler &$pObj)
    {
        if ($table == 'fe_users') {
            if (strpos($id, 'NEW') !== false) {
                $password = $incomingFieldArray['password'];
                $keypair = Keys::createKey($password);
                $incomingFieldArray['tx_guard7_publickey'] = $keypair['public'];
                $incomingFieldArray['tx_guard7_privatekey'] = $keypair['private'];
            } elseif (strpos($incomingFieldArray['password'], 'rsa:') === false) {
                $tmprec = BackendUtility::getRecord('fe_users', $id);
                if ($tmprec['password'] != $incomingFieldArray['password']) {
                    $signature_old = Keys::getChecksum($tmprec['tx_guard7_publickey']);
                    Storage::markForReencode($signature_old);
                    
                    $password = $incomingFieldArray['password'];
                    $keypair = Keys::createKey($password);
                    $incomingFieldArray['tx_guard7_publickey'] = $keypair['public'];
                    $incomingFieldArray['tx_guard7_privatekey'] = $keypair['private'];
                }
            }
        }
    }
    
    /**
     * @param $status
     * @param $table
     * @param $id
     * @param $fieldArray
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $pObj
     * @throws \SUDHAUS7\Guard7\SealException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     */
    public function processDatamap_postProcessFieldArray($status, $table, $id, &$fieldArray, \TYPO3\CMS\Core\DataHandling\DataHandler &$pObj)
    {
        
        //fieldArray['pid']
        //	$pObj->substNEWwithIDs
       
        if ($status === 'new') {
            $vaultfields = $this->getTableFields($table, $fieldArray['pid']);
            if (!empty($vaultfields)) {
                $fieldArray = $this->handeInsert($table, $id, $fieldArray, $vaultfields);
            }
        }
        
        if ($status === 'update') {
            $extraPubkeys = [];
            if ($table === 'fe_users' && !empty($fieldArray['tx_guard7_publickey'])) {
                $extraPubkeys[] = $fieldArray['tx_guard7_publickey'];
            }
            $pid = $pObj->getPID($table, $id);
            $vaultfields = $this->getTableFields($table, $pid);
            if (!empty($vaultfields)) {
                $pubkeys = Keys::collectPublicKeys($table, $id, $pid, false, $extraPubkeys);
                $fieldArray = Storage::lockRecord($table, $id, $vaultfields, $fieldArray, $pubkeys);
            }
        }
    }
    
    /**
     * @param $table
     * @param $id
     * @param $fieldArray
     * @param array $vaultfields
     * @return array
     * @throws \SUDHAUS7\Guard7\SealException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException
     * @throws \TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException
     */
    protected function handeInsert($table, $id, $fieldArray, array $vaultfields): array
    {
        $extraPubkeys = [];
        if ($table === 'fe_users' && !empty($fieldArray['tx_guard7_publickey'])) {
            $extraPubkeys[] = $fieldArray['tx_guard7_publickey'];
        }
        
        $pubkeys = Keys::collectPublicKeys($table, 0, $fieldArray['pid'], false, $extraPubkeys);
        
        if (!isset($this->insertCache[$table])) {
            $this->insertCache[$table] = [];
        }
        $this->insertCache[$table][$id] = [];
        
        
        foreach ($fieldArray as $fieldname => $value) {
            if (in_array($fieldname, $vaultfields)) {
                if (strlen($value) > 0) {
                    $fieldArray[$fieldname] = '&#128274;';
                    //$fieldArray[$fieldname] = '&#128274;'; // 🔒
                    $encoder = new Encoder($value, $pubkeys);
                    $this->insertCache[$table][$id][] = [
                        'fieldname' => $fieldname,
                        'encoded' => $encoder->run(),
                        'pubkeys' => $pubkeys
                    ];
                    unset($encoder);
                }
            }
        }
        return $fieldArray;
    }
    
    /**
     * @param $table
     * @param $pid
     * @param $tmp
     * @return array
     */
    protected function getTableFields($table, $pid)
    {
        $ts = BackendUtility::getPagesTSconfig($pid);
        $vaultfields = [];
        if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['guard7']) && !empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['guard7'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['guard7'] as $config) {
                if ($config['tableName'] === $table) {
                    $vaultfields = GeneralUtility::trimExplode(',', $config['fields'], true);
                }
            }
        }
        if (isset($ts['tx_sudhaus7guard7.'])) {
            $tablekey = $table . '.';
            if (isset($ts['tx_sudhaus7guard7.'][$tablekey]) && isset($ts['tx_sudhaus7guard7.'][$tablekey]['fields'])) {
                $tmpfields = GeneralUtility::trimExplode(',', $ts['tx_sudhaus7guard7.'][$tablekey]['fields'], true);
                if (!empty($tmpfields)) {
                    $vaultfields = array_merge($vaultfields, $tmpfields);
                }
            }
        }
        return $vaultfields;
    }
}
