<?php
/**
 * Created by PhpStorm.
 * User: frank
 * Date: 16.02.18
 * Time: 15:58
 */

namespace SUDHAUS7\Datavault\Tools;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Storage {


	public static function markForReencode($signature) {
		/** @var Connection $connection */
		$connection = GeneralUtility::makeInstance(ConnectionPool::class)
		                            ->getConnectionForTable('tx_sudhaus7datavault_signatures');
		$res = $connection->select( 'parent', 'tx_sudhaus7datavault_signatures',['signature'=>$signature]);
		$list = $res->fetchAll(\PDO::FETCH_ASSOC);
		foreach ($list as $row) {
			$connection->update( 'tx_sudhaus7datavault_data', ['needsreencode'=>1], ['uid'=>$row['parent']]);
		}

	}

	public static function updateKeyLog($tx_sudhaus7datavault_data_uid,$pubkeys) {
		/** @var Connection $connection */
		$connection = GeneralUtility::makeInstance(ConnectionPool::class)
		                            ->getConnectionForTable('tx_sudhaus7datavault_signatures');
		$connection->delete('tx_sudhaus7datavault_signatures',['parent'=>$tx_sudhaus7datavault_data_uid]);
		foreach($pubkeys as $checksum=>$key) {
			$connection->insert('tx_sudhaus7datavault_signatures',['parent'=>$tx_sudhaus7datavault_data_uid,'signature'=>$checksum]);
		}
	}

	/**
	 * @param $table
	 * @param $uid
	 * @param $fields
	 * @param $data
	 * @param $pubKeys
	 *
	 * @return mixed
	 */
	public static function lockRecord($table,$uid,$fields,$data,$pubKeys) {
		/** @var Connection $connection */
		$connection = GeneralUtility::makeInstance(ConnectionPool::class)
		                            ->getConnectionForTable('tx_sudhaus7datavault_data');
		foreach ($data as $fieldname=>$value) {
			if ( in_array( $fieldname, $fields ) ) {
				$data[$fieldname] = '&#128274;';
				if ($value == '&#128274;' || $value == '🔒') {
					continue;
				}
				$fieldArray[$fieldname] = '&#128274;'; // 🔒
				$encoder = new Encoder( $value, $pubKeys);
				$encoded = $encoder->run();
				unset($encoder);
				$connection->delete( 'tx_sudhaus7datavault_data', ['tablename'=>$table,'tableuid'=>$uid,'fieldname'=>$fieldname]);
				$connection->insert( 'tx_sudhaus7datavault_data', ['tablename'=>$table,'tableuid'=>$uid,'fieldname'=>$fieldname,'secretdata'=>$encoded]);
				$insertid = $connection->lastInsertId();
				self::updateKeyLog( $insertid, $pubKeys);

			}
		}
		return $data;
	}


	public static function unlockRecord($table,$data,$privateKey,$uid=0) {

		if ($uid==0) {
			$uid = $data['uid'];
		}

		/** @var Connection $connection */
		$connection = GeneralUtility::makeInstance(ConnectionPool::class)
		                            ->getConnectionForTable('tx_sudhaus7datavault_data');
		foreach ($data as $fieldname=>$value) {
			if ($value == '&#128274;' || $value == '🔒') {
				$row = $connection->select( 'secretdata', 'tx_sudhaus7datavault_data', [ 'tablename' => $table, 'tableuid' => $uid, 'fieldname'=>$fieldname],[],[],0,1 )->fetch(\PDO::FETCH_ASSOC);
				if ($row && $row['secretdata']) {
					try {
						$data[ $fieldname ] = Decoder::decode( $row['secretdata'], $privateKey );
					} catch (\Exception $e) {

					}
				}
			}
		}
		return $data;
	}


	public static function lockFile($filepath,$pubKeys) {
		try {
			$encoded = self::encodeFile( $filepath, $pubKeys );
			@unlink( $filepath );
			\file_put_contents( $filepath . '.s7sec', $encoded );
		} catch (\Exception $e) {
			return false;
		}
		return true;
	}

	public static function unlockFile($filepath,$privateKey,$password) {
		try {
			$data = self::decodeFile( $filepath, $privateKey,$password);
			@unlink($filepath);
			\file_put_contents( dirname($filepath).'/'.$data['filename'], \base64_decode( $data['secure']));
		} catch (\Exception $e) {
			return false;
		}
		return true;
	}

	public static function encodeFile($filepath,$pubKeys) {
		$identifier = str_replace(PATH_site,'',$filepath);
		$identifier = str_replace('fileadmin/','',$identifier);

		$data = [
			'checksum'=>\sha1_file( $filepath),
			'secure'=>base64_encode(\file_get_contents( $filepath)),
			'filename'=>basename($filepath),
			'identifier'=>$identifier,
			'identifier_hash'=>\sha1( $identifier)
		];

		$encoder = new Encoder( \json_encode( $data), $pubKeys);
		$encoded = $encoder->run();
		return $encoded;

	}

	public static function decodeFile($filepath,$privatekey,$password=null) {
		$enc = \file_get_contents( $filepath);
		$json = Decoder::decode( $enc, $privatekey, $password);
		$data =\json_decode( $json, true);
		return $data;
	}

}
