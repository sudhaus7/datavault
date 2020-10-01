<?php
/**
 * Created by PhpStorm.
 * User: frank
 * Date: 30.04.18
 * Time: 14:47
 */

namespace SUDHAUS7\Guard7\Tools;

use SUDHAUS7\Guard7\Adapter\ConfigurationAdapter;
use SUDHAUS7\Guard7\Interfaces\Guard7Interface;
use SUDHAUS7\Guard7Core\Service\ChecksumService;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Exception;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;
use TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotException;
use TYPO3\CMS\Extbase\SignalSlot\Exception\InvalidSlotReturnException;

class Helper
{
    /**
     * @param $pid
     * @param null $table
     * @return array|mixed
     */
    public static function getTsConfig($pid, $table = null)
    {
        $cacheKey = __METHOD__ . '-CACHE';
        if (!isset($GLOBALS[$cacheKey])) {
            $GLOBALS[$cacheKey] = [];
        }
        if (!isset($GLOBALS[$cacheKey][$pid])) {
            $pageTs = BackendUtility::getPagesTSconfig($pid);
            if (isset($pageTs['tx_sudhaus7guard7.'])) {
                $GLOBALS[$cacheKey][$pid] = $pageTs['tx_sudhaus7guard7.'];
            }
        }
        if ($table !== null) {
            return $GLOBALS[$cacheKey][$pid][$table.'.'] ?? [];
        }
        return $GLOBALS[$cacheKey][$pid] ?? [];
    }
    
    /**
     * @param $pid
     * @param null $table
     * @return array
     */
    public static function getTsPublicKeys($pid, $table = null) : array
    {
        $pageTs = self::getTsConfig($pid);
        $ret = [];
        
        if (isset($pageTs['generalPublicKeys.']) && !empty($pageTs['generalPublicKeys.'])) {
            foreach ($pageTs['generalPublicKeys.'] as $key) {
                $ret[] = $key;
            }
        }
        if ($table) {
            $tabledot = $table . '.';
            if (isset($pageTs[$tabledot]['publicKeys.']) && is_array($pageTs[$tabledot]['publicKeys.'])) {
                foreach ($pageTs[$tabledot]['publicKeys.'] as $key) {
                    $ret[] = $key;
                }
            }
        }
        return $ret;
    }
    
    /**
     * @param string $table
     * @param int $pid
     * @return array
     */
    public static function getFields($table, $pid = 0) : array
    {
        $fields = [];
        if (!empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['guard7'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['guard7'] as $config) {
                if (isset($config['tableName']) && $config['tableName'] === $table) {
                    $myfields = $config['fields'];
                    if (!is_array($myfields)) {
                        $myfields =  GeneralUtility::trimExplode(',', $myfields, true);
                    }
                    if (!empty($myfields)) {
                        $fields = $myfields;
                    }
                }
            }
        }
        
        if ($pid > 0) {
            $pageTS = self::getTsConfig($pid, $table);
            if (isset($pageTS['fields'])) {
                $myfields = GeneralUtility::trimExplode(',', $pageTS['fields'], true);
                if (!empty($myfields)) {
                    $fields = \array_merge($fields, $myfields);
                }
            }
        }
        return $fields;
    }
    
    /**
     * @param AbstractEntity $obj
     * @param null $table
     * @return array
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     */
    public static function getModelFields(AbstractEntity $obj, $table = null): array
    {
        if ($table === null) {
            $table = self::getModelTable($obj);
        }
        return self::getFields($table, $obj->getPid());
    }
    
    /**
     * @param AbstractEntity $obj
     * @return string
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     */
    public static function getModelTable(AbstractEntity $obj) : ?string
    {
        return self::getClassTable(\get_class($obj));
    }
    
    /**
     * @param string $class
     * @return string|null
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     */
    public static function getClassTable($class) : ?string
    {
        $table = null;
        if (!empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['guard7'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['guard7'] as $config) {
                if (isset($config['className']) && $config['className'] === $class && isset($config['tableName']) && !empty($config['tableName'])) {
                    $table = $config['tableName'];
                }
            }
        }
        
        if ($table === null) {
            $om = GeneralUtility::makeInstance(ObjectManager::class);
            $dataMapper = $om->get(\TYPO3\CMS\Extbase\Persistence\Generic\Mapper\DataMapper::class);
            $table = $dataMapper->getDataMap($class)->getTableName();
        }
        return $table;
    }
    
    /**
     * @param $className
     * @return bool
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     */
    public static function classIsGuard7Element($className, $pid=0) : bool
    {
        if (in_array(Guard7Interface::class, \class_implements($className), true)) {
            return true;
        }
        
        if (!empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['guard7'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['guard7'] as $config) {
                if (isset($config['className']) && $className === $config['className']) {
                    return true;
                }
            }
        }
    
        if ($pid===0) {
            if (isset($GLOBALS['TSFE'])) {
                $pid = $GLOBALS['TSFE']->id;
            }
        }
        if ($pid > 0) {
            $table = self::getClassTable($className);
            if ($table !== null) {
                $ts = self::getTsConfig($pid, $table);
                return !empty($ts);
            }
        }
        return false;
    }
    
    public static function tableIsGuard7Element($tableName, $pid=0) : bool
    {
        if (!empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['guard7'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['guard7'] as $config) {
                if (isset($config['tableName']) && $tableName === $config['tableName']) {
                    return true;
                }
            }
        }
        if ($pid===0) {
            if (isset($GLOBALS['TSFE'])) {
                $pid = (int)$GLOBALS['TSFE']->id;
            }
        }
        if ($pid > 0) {
            $ts = self::getTsConfig($pid, $tableName);
            return !empty($ts);
        }
    }
    
    public static function getAllGuard7Tables($pid=0) : array
    {
        $tables = [];
        if (!empty($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['guard7'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['guard7'] as $config) {
                if (isset($config['tableName'])) {
                    $tables[]=$config['tableName'];
                }
            }
        }
        
        if ($pid===0) {
            if (isset($GLOBALS['TSFE'])) {
                $pid = (int)$GLOBALS['TSFE']->id;
            }
        }
        if ($pid > 0) {
            $ts = self::getTsConfig($pid);
            foreach ($ts as $tableName=>$config) {
                $tables[] = trim($tableName, '.');
            }
        }
        return $tables;
    }
    
    public static function checkLockedValue($value)
    {
        return $value === '&#128274;' || $value === '🔒';
    }
    
    
    /**
     * @param AbstractEntity $obj
     * @param bool $checkFEuser
     * @param array $aPubkeys
     * @return array
     * @throws Exception
     * @throws InvalidSlotException
     * @throws InvalidSlotReturnException
     */
    public static function collectPublicKeysForModel(AbstractEntity $obj, $checkFEuser = false, $aPubkeys = [])
    {
        $class = get_class($obj);
        $table = Helper::getClassTable($class);
        $encodeStorage = GeneralUtility::makeInstance(FrontendUserPublicKeySingleton::class);
        
        if (!$checkFEuser && $encodeStorage->has($obj)) {
            $checkFEuser = true;
            $encodeStorage->remove($obj);
        }
        
        return self::collectPublicKeys($table, (int)$obj->getUid(), (int)$obj->getPid(), $checkFEuser, $aPubkeys);
    }
    
    /**
     * @param null $table
     * @param mixed $uid
     * @param int $pid
     * @param bool $checkFEuser
     * @param array $aPubkeys
     *
     * @return array
     * @throws InvalidSlotException
     * @throws InvalidSlotReturnException
     */
    public static function collectPublicKeys($table = null, $uid = 0, $pid = 0, $checkFEuser = false, $aPubkeys = []) : array
    {
        
        /** @var Dispatcher $signalSlotDispatcher */
        $signalSlotDispatcher = GeneralUtility::makeInstance(Dispatcher::class);
        
        /** @var ChecksumService $checksumService */
        $checksumService = GeneralUtility::makeInstance(ChecksumService::class);
        /** @var ConfigurationAdapter $configadapter */
        $configadapter = GeneralUtility::makeInstance(ConfigurationAdapter::class);
        
        $pubKeys = [];
        
        // Signal Global
        $keysFromSignalslot = [];
        list($keysFromSignalslot) = $signalSlotDispatcher->dispatch(__CLASS__, 'global', [$keysFromSignalslot,$uid,$pid]);
        
        
        // Signal Name by table for example: collectPublicKeys_fe_users
        list($keysFromSignalslot) = $signalSlotDispatcher->dispatch(__CLASS__, __FUNCTION__.'_'.$table, [$keysFromSignalslot,$uid,$pid]);
        
        if (!empty($keysFromSignalslot)) {
            foreach ($keysFromSignalslot as $key) {
                $pubKeys[$checksumService->calculate($key)] = $key;
            }
        }
        if (!empty($aPubkeys)) {
            foreach ($aPubkeys as $key) {
                $pubKeys[$checksumService->calculate($key)] = $key;
            }
        }
        if (!empty($configadapter->config['masterkeypublic'])) {
            $checksum = $checksumService->calculate($configadapter->config['masterkeypublic']);
            $pubKeys[$checksum] = $configadapter->config['masterkeypublic']['masterkeypublic'];
        }
        if ($pid > 0) {
            $tskeys = Helper::getTsPublicKeys($pid, $table);
            foreach ($tskeys as $key) {
                $pubKeys[$checksumService->calculate($key)] = $key;
            }
        }
        if ($checkFEuser && isset($GLOBALS['TSFE']) && $GLOBALS['TSFE']->loginUser) {
            if (isset($GLOBALS['TSFE']->fe_user->user['tx_guard7_publickey']) && !empty($GLOBALS['TSFE']->fe_user->user['tx_guard7_publickey'])) {
                $pubKeys[$checksumService->calculate($GLOBALS['TSFE']->fe_user->user['tx_guard7_publickey'])] = $GLOBALS['TSFE']->fe_user->user['tx_guard7_publickey'];
            }
        }
        return $pubKeys;
    }
    
}
