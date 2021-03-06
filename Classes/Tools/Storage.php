<?php
/**
 * Created by PhpStorm.
 * User: frank
 * Date: 16.02.18
 * Time: 15:58
 */

namespace SUDHAUS7\Guard7\Tools;

use SUDHAUS7\Guard7\Adapter\ConfigurationAdapter;
use SUDHAUS7\Guard7Core\Exceptions\UnlockException;
use SUDHAUS7\Guard7Core\Factory\KeyFactory;
use SUDHAUS7\Guard7Core\Tools\Decoder;
use SUDHAUS7\Guard7Core\Tools\Encoder;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class Storage
 *
 * @package SUDHAUS7\Guard7\Tools
 */
class Storage
{
    /**
     * @param $signature
     */
    public static function markForReencode($signature)
    {
        /** @var Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_guard7_signatures');
        $res = $connection->select(['parent'], 'tx_guard7_signatures', ['signature' => $signature]);
        $list = $res->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($list as $row) {
            $connection->update('tx_guard7_domain_model_data', ['needsreencode' => 1], ['uid' => $row['parent']]);
        }
    }
    
    /**
     * @param $tx_guard7_domain_model_data_uid
     * @param $pubkeys
     */
    public static function updateKeyLog($tx_guard7_domain_model_data_uid, $pubkeys)
    {
        /** @var Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_guard7_signatures');
        
        $connection->delete('tx_guard7_signatures', ['parent' => $tx_guard7_domain_model_data_uid]);
        foreach ($pubkeys as $checksum => $key) {
            $connection->insert(
                'tx_guard7_signatures',
                [
                    'parent' => $tx_guard7_domain_model_data_uid,
                    'signature' => $checksum
                ]
            );
        }
    }
    
    
    
    /**
     * @param \TYPO3\CMS\Extbase\DomainObject\AbstractEntity $obj
     * @param array $fields
     * @param array $pubKeys
     * @throws \SUDHAUS7\Guard7\SealException
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     */
    public static function lockModel(\TYPO3\CMS\Extbase\DomainObject\AbstractEntity $obj, array $fields, array $pubKeys, $store = true)
    {
        
        /** @var ConfigurationAdapter $configuration */
        $configuration = GeneralUtility::makeInstance(ConfigurationAdapter::class);
        
        $table = Helper::getModelTable($obj);
        
        /** @var Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($table);
        
        
        foreach ($fields as $fieldname) {
            $setter = 'set' . GeneralUtility::underscoredToUpperCamelCase($fieldname);
            $getter = 'get' . GeneralUtility::underscoredToUpperCamelCase($fieldname);
            if (\method_exists($obj, $getter)) {
                $value = $obj->$getter();
                if (Helper::checkLockedValue($value) || empty($value)) {
                    continue;
                }
                $connection->delete(
                    'tx_guard7_domain_model_data',
                    [
                        'tablename' => $table,
                        'tableuid' => $obj->getUid(),
                        'fieldname' => $fieldname
                    ]
                );
                $obj->$setter('&#128274;'); // 🔒
                
                $encoder = new Encoder($configuration, $pubKeys, $value);
                $encoded = $encoder->run();
                unset($encoder);
                
                $connection->insert('tx_guard7_domain_model_data', [
                    'tablename' => $table,
                    'tableuid' => $obj->getUid(),
                    'fieldname' => $fieldname,
                    'secretdata' => $encoded,
                ]);
                
                $insertid = $connection->lastInsertId();
                self::updateKeyLog($insertid, $pubKeys);
                $connection->update($table, [$fieldname => '&#128274;'], ['uid' => $obj->getUid()]);// 🔒
            }
        }
    }
    
    /**
     * @param $table
     * @param $uid
     * @param $fields
     * @param $data
     * @param $pubKeys
     * @return mixed
     * @throws \SUDHAUS7\Guard7\SealException
     */
    public static function lockRecord($table, $uid, $fields, $data, $pubKeys)
    {
        /** @var ConfigurationAdapter $configuration */
        $configuration = GeneralUtility::makeInstance(ConfigurationAdapter::class);
        
        /** @var Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_guard7_domain_model_data');
        foreach ($data as $fieldname => $value) {
            if (in_array($fieldname, $fields)) {
                $data[$fieldname] = '&#128274;';
                if (Helper::checkLockedValue($value)) {
                    continue;
                }
                
                $fieldArray[$fieldname] = '&#128274;'; // 🔒
                $encoder = new Encoder($configuration, $pubKeys, $value);
                $encoded = $encoder->run();
                unset($encoder);
                
                $connection->delete(
                    'tx_guard7_domain_model_data',
                    [
                        'tablename' => $table,
                        'tableuid' => $uid,
                        'fieldname' => $fieldname
                    ]
                );
                $connection->insert('tx_guard7_domain_model_data', [
                    'tablename' => $table,
                    'tableuid' => $uid,
                    'fieldname' => $fieldname,
                    'secretdata' => $encoded,
                ]);
                $insertid = $connection->lastInsertId();
                self::updateKeyLog($insertid, $pubKeys);
            }
        }
        return $data;
    }
    
    /**
     * @param \TYPO3\CMS\Extbase\DomainObject\AbstractEntity $obj
     * @param string|null $table
     * @param string|null $privateKey
     * @param string|null $password
     * @throws \TYPO3\CMS\Extbase\Persistence\Generic\Exception
     */
    public static function unlockModel(\TYPO3\CMS\Extbase\DomainObject\AbstractEntity $obj, $table=null, $privateKey=null, $password = null)
    {
    
        /** @var ConfigurationAdapter $configuration */
        $configuration = GeneralUtility::makeInstance(ConfigurationAdapter::class);
        $key = KeyFactory::readFromString($configuration, $privateKey, $password);
        
        if ($table===null) {
            $table = Helper::getModelTable($obj);
        }
        
        $uid = $obj->getUid();
        /** @var Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_guard7_domain_model_data');
        $res = $connection->select(
            ['*'],
            'tx_guard7_domain_model_data',
            [
                'tablename' => $table,
                'tableuid' => $uid
            ]
        );
        while ($row = $res->fetch(\PDO::FETCH_ASSOC)) {
            $setter = 'set' . GeneralUtility::underscoredToUpperCamelCase($row['fieldname']);
            $getter = 'get' . GeneralUtility::underscoredToUpperCamelCase($row['fieldname']);
            if (\method_exists($obj, $getter)) {
                $value = $obj->$getter();
                if (Helper::checkLockedValue($value)) {
                    try {
                        $newvalue = Decoder::decode($configuration,$key,$row['secretdata']);
                        if (\method_exists($obj, $setter)) {
                            $obj->$setter($newvalue);
                        }
                    } catch (\Exception $e) {
                        //$data[ $fieldname ] = '&#128274;';
                    }
                }
            }
        }
    }
    
    /**
     * @param string $table Tablename of the locked Record
     * @param array $data The locked data-row
     * @param string|null $privateKey
     * @param string|null $password
     * @param int $uid
     * @throws \SUDHAUS7\Guard7Core\Exceptions\MissingKeyException
     * @return array
     */
    public static function unlockRecord($table, $data, $privateKey=null, $password = null, $uid = 0)
    {
        /** @var ConfigurationAdapter $configuration */
        $configuration = GeneralUtility::makeInstance(ConfigurationAdapter::class);
        $key = KeyFactory::readFromString($configuration, $privateKey, $password);
        
        if ($uid == 0) {
            $uid = $data['uid'];
        }
        
        /** @var Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_guard7_domain_model_data');
        foreach ($data as $fieldname => $value) {
            if (Helper::checkLockedValue($value)) {
                $row = $connection->select(
                    ['secretdata'],
                    'tx_guard7_domain_model_data',
                    [
                        'tablename' => $table,
                        'tableuid' => $uid,
                        'fieldname' => $fieldname
                    ],
                    [],
                    [],
                    0,
                    1
                )
                    ->fetch(\PDO::FETCH_ASSOC);
                if ($row && $row['secretdata']) {
                    try {
                        $data[$fieldname] = Decoder::decode($configuration, $key, $row['secretdata']);
                    } catch (UnlockException $exception) {
                        //$data[ $fieldname ] = '&#128274;';
                    }
                }
            }
        }
        return $data;
    }
    
    
    /**
     * @param $path
     * @return false|string|null
     */
    private static function sanitizePath($path)
    {
        str_replace('../', '', $path);
        $path = \realpath($path);
        if (strpos($path, PATH_site) === 0) {
            return $path;
        }
        return null;
    }
    
    /**
     * @param $filepath
     * @param $pubKeys
     * @return bool
     */
    public static function lockFile($filepath, $pubKeys)
    {
        $filepath = self::sanitizePath($filepath);
        if (is_file($filepath)) {
            try {
                $encoded = self::encodeFile($filepath, $pubKeys);
                if ($encoded !== null) {
                    //@unlink( $filepath );
                    \file_put_contents($filepath, 'encoded');
                    \file_put_contents($filepath . '.s7sec', $encoded);
                    return true;
                }
            } catch (\Exception $e) {
                return false;
            }
        }
        return false;
    }
    
    /**
     * @param $filepath
     * @param $privateKey
     * @param $password
     * @return bool
     */
    public static function unlockFile($filepath, $privateKey, $password)
    {
        $filepath = self::sanitizePath($filepath) . '.s7sec';
        
        if (is_file($filepath)) {
            try {
                $data = self::decodeFile($filepath, $privateKey, $password);
                if ($data !== null) {
                    @unlink($filepath);
                    \file_put_contents(
                        dirname($filepath) . '/' . $data['filename'],
                        \base64_decode($data['secure'])
                    );
                    return true;
                }
            } catch (\Exception $e) {
                return false;
            }
        }
        return false;
    }
    
    /**
     * @param $filepath
     * @param $pubKeys
     * @return string|null
     * @throws \SUDHAUS7\Guard7\SealException
     */
    public static function encodeFile($filepath, $pubKeys): ?string
    {
        /** @var ConfigurationAdapter $configuration */
        $configuration = GeneralUtility::makeInstance(ConfigurationAdapter::class);
    
        $filepath = self::sanitizePath($filepath);
        $encoded = null;
        if (is_file($filepath)) {
            $identifier = str_replace(array(
                PATH_site,
                'fileadmin/'
            ), '', $filepath);
            
            $buf = \file_get_contents($filepath);
            if ($buf === 'encoded') {
                throw new \Exception('already encoded');
            }
            $data = [
                'checksum' => \sha1_file($filepath),
                'secure' => base64_encode($buf),
                'filename' => basename($filepath),
                'identifier' => $identifier,
                'identifier_hash' => \sha1($identifier)
            ];
            
            $encoder = new Encoder($configuration, $pubKeys, \json_encode($data));
            $encoded = $encoder->run();
        }
        return $encoded;
    }
    
    /**
     * @param $filepath
     * @param $privatekey
     * @param null $password
     * @return mixed|null
     * @throws MissingKeyException
     * @throws UnlockException
     * @throws WrongKeyPassException
     * @throws \SUDHAUS7\Guard7\KeyNotReadableException
     */
    public static function decodeFile($filepath, $privatekey, $password = null)
    {
        /** @var ConfigurationAdapter $configuration */
        $configuration = GeneralUtility::makeInstance(ConfigurationAdapter::class);
        $key = KeyFactory::readFromString($configuration, $privatekey, $password);
        $filepath = self::sanitizePath($filepath);
        $data = null;
        if (is_file($filepath)) {
            $enc = \file_get_contents($filepath);
            $json = Decoder::decode($configuration, $key, $enc);
            $data = \json_decode($json, true);
        }
        return $data;
    }
}
