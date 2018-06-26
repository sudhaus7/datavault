<?php
/**
 * Created by PhpStorm.
 * User: frank
 * Date: 21.02.18
 * Time: 15:56
 */

namespace SUDHAUS7\Guard7\Hooks\Frontend;

use SUDHAUS7\Guard7\Tools\Keys;
use SUDHAUS7\Guard7\Tools\Storage;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class Userchangepassword
 * @package SUDHAUS7\Guard7\Frontend
 */
class Userchangepassword
{
    
    /**
     * @param $params
     */
    public function handle($params)
    {
        
        /** @var Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('fe_users');
        $user = $params['user'];
        $signature_old = Keys::getChecksum($user['tx_guard7_publickey']);
        Storage::markForReencode($signature_old);
        
        $password = $params['newPassword'];
        $keypair = Keys::createKey($password);
        $data = [];
        $data['tx_guard7_publickey'] = $keypair['public'];
        $data['tx_guard7_privatekey'] = $keypair['private'];
        $connection->update('fe_users', $data, ['uid' => $user['uid']]);
    }
}
