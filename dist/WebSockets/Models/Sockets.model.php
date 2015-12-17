<?php

	namespace Packages\WebSockets\Models;

	class Sockets{

		protected static $arrTemporary = array();
		protected static $arrConnected = array();

		/**
		 * Get the connection resource ID from the resource
		 * @param $resSocket
		 * @return int
		 */
		public static function id($resSocket){
			return (int)$resSocket;
		}

		/**
		 * Create a temp connection, once handshake and verification has been taken place it can be stored as a connected socket
		 * @param $resSocket
		 * @return array Returns the temporary connection details
		 */
		public static function createTemporary($resSocket){

			$arrInfo = self::getPeerDetails($resSocket);
			$intSocketID = self::id($resSocket);

			self::$arrTemporary[$intSocketID] = array(
				'id' => $intSocketID,
				'socket' => $resSocket,
				'handshake' => false,
				'version' => 0,
				'ip' => $arrInfo['ip_address'],
				'port' => $arrInfo['port']
			);

			return self::$arrTemporary[$intSocketID];
		}

		/**
		 * Remove and close a temp connection as it is either verified or being rejected
		 * @param $resSocket
		 */
		public static function removeTemporary($resSocket){

			//Close the socket connection
			socket_close($resSocket);

			unset(self::$arrTemporary[self::id($resSocket)]);
		}

		/**
		 * Get the details of a temp connection
		 * @param $resSocket
		 * @return mixed
		 */
		public static function getTemporary($resSocket){
			return self::$arrTemporary[self::id($resSocket)];
		}

		/**
		 * Set the status of the temp connections handshake
		 * @param $resSocket
		 * @param $blHandshake
		 */
		public static function setTemporaryHandshake($resSocket,$blHandshake){

			$intSocketID = self::id($resSocket);

			if(array_key_exists($intSocketID,self::$arrTemporary)){
				self::$arrTemporary[$intSocketID]['handshake'] = $blHandshake;
			}
		}

		/**
		 * Set the socket version number of the temp connection, this version will be carried through to the full connection array
		 * @param $resSocket
		 * @param $intVersionID
		 */
		public static function setTemporaryVersion($resSocket,$intVersionID){

			$intSocketID = self::id($resSocket);

			if(array_key_exists($intSocketID,self::$arrTemporary)){
				self::$arrTemporary[$intSocketID]['version'] = (is_null($intVersionID)) ? 0 : $intVersionID;
			}
		}

		/**
		 * Check to see if the Socket is a temp connection or not
		 * @param $resSocket
		 * @return bool Returns true if its a temp connection
		 */
		public static function isTemporary($resSocket){
			return array_key_exists(self::id($resSocket),self::$arrTemporary);
		}

		/**
		 * Make a temporary connection into a fully connected socket
		 * @param $resSocket
		 * @return bool
		 */
		public static function create($resSocket){

			$blConnected = false;
			$arrTemporarySocket = self::getTemporary($resSocket);

			//If the temp connection is not correct then terminate the connection
			if(is_null($arrTemporarySocket['socket']) || $arrTemporarySocket['socket'] == '' || $arrTemporarySocket['ip'] == ''){
				self::removeTemporary($resSocket);
			}else{

				self::$arrConnected[$arrTemporarySocket['id']] = array(
					'id' => $arrTemporarySocket['id'],
					'socket' => $arrTemporarySocket['socket'],
					'handshake' => $arrTemporarySocket['handshake'],
					'version' => $arrTemporarySocket['version'],
					'ip' => $arrTemporarySocket['ip'],
					'port' => $arrTemporarySocket['port'],
					'time_connected' => time(),
					'data' => array()
				);

				self::removeTemporary($resSocket);
				$blConnected = true;
			}

			return $blConnected;
		}

		/**
		 * Remove and close a connection as it is being rejected
		 * @param $resSocket
		 */
		public static function remove($resSocket){

			//Close the socket connection
			socket_close($resSocket);

			unset(self::$arrConnected[self::id($resSocket)]);
		}

		/**
		 * Set a key value pair against a particular socket connection
		 * @param $resSocket
		 * @param $strDataKey
		 * @param $mxdDataValue
		 */
		public static function setData($resSocket,$strDataKey,$mxdDataValue){
			self::$arrConnected[self::id($resSocket)]['data'][$strDataKey] = $mxdDataValue;
		}

		/**
		 * Get a key value pair from an particular socket connection
		 * @param $resSocket
		 * @param $strDataKey
		 * @return null|mixed
		 */
		public static function getData($resSocket,$strDataKey){

			$intSocketID = self::id($resSocket);

			return (array_key_exists($strDataKey,self::$arrConnected[$intSocketID]['data'])) ? self::$arrConnected[$intSocketID]['data'][$strDataKey] : null;
		}

		/**
		 * Find all the socket connections that have a key value pair associated with them
		 * @param $strDataKey
		 * @param $mxdDataValue
		 * @return array
		 */
		public static function findResources($strDataKey,$mxdDataValue){

			$arrSockets = array();

			foreach(self::$arrConnected as $arrEachSocket){
				if(array_key_exists($strDataKey,$arrEachSocket['data']) && $arrEachSocket['data'][$strDataKey] === $mxdDataValue){
					$arrSockets[] = $arrEachSocket;
				}
			}

			return $arrSockets;
		}

		/**
		 * Get the details of a connection
		 * @param $resSocket
		 * @return mixed
		 */
		public static function get($resSocket){
			return self::$arrConnected[self::id($resSocket)];
		}

		/**
		 * Get an array of all the sockets (temp and valid connections), include the master socket when requested
		 * @param null $resMasterSocket Master socket resource to be added to output
		 * @return array An array of all sockets in the system
		 */
		public static function getAll($resMasterSocket = null){

			$arrOut = array();

			//Include the master socket in the array when passed in
			if(!is_null($resMasterSocket)){
				$arrOut[] = $resMasterSocket;
			}

			foreach(self::$arrTemporary as $arrEachConnection){
				$arrOut[] = $arrEachConnection['socket'];
			}

			foreach(self::$arrConnected as $arrEachConnection){
				$arrOut[] = $arrEachConnection['socket'];
			}

			return $arrOut;
		}

		/**
		 * Get an array of all the connected sockets and their associated info
		 * @return array
		 */
		public static function getAllConnected(){
			return self::$arrConnected;

		}

		/**
		 * Get an array of all the temporary sockets and their associated info (Sockets that have not yet completed handshake and connection/login process)
		 * @return array
		 */
		public static function getAllTemporary(){
			return self::$arrTemporary;
		}

		/**
		 * Get the handshake status for the temp or connected socket
		 * @param $resSocket
		 * @return bool
		 */
		public static function getHandshake($resSocket){

			$blHandshake = false;
			$intSocketID = self::id($resSocket);

			if(array_key_exists($intSocketID,self::$arrConnected)){
				$blHandshake = self::$arrConnected[$intSocketID]['handshake'];
			}elseif(array_key_exists($intSocketID,self::$arrTemporary)){
				$blHandshake = self::$arrTemporary[$intSocketID]['handshake'];
			}

			return $blHandshake;
		}

		/**
		 * Get the version of the temp or connected socket
		 * @param $resSocket
		 * @return int
		 */
		public static function getVersion($resSocket){

			$intVersion = 0;
			$intSocketID = self::id($resSocket);

			if(array_key_exists($intSocketID,self::$arrConnected)){
				$intVersion = self::$arrConnected[$intSocketID]['version'];
			}elseif(array_key_exists($intSocketID,self::$arrTemporary)){
				$intVersion = self::$arrTemporary[$intSocketID]['version'];
			}

			return $intVersion;
		}

		/**
		 * Get the peers IP and Port from the resource
		 * @param $resSocket
		 * @return array
		 */
		protected static function getPeerDetails($resSocket){

			socket_getpeername($resSocket, $strClientIP, $intClientPort);

			return array(
				'ip_address' => $strClientIP,
				'port' => $intClientPort
			);
		}

		/**
		 * Write/Send an array of data to be JSON encoded back to the socket resource, message will be wrapped for transport
		 * @param $resSocket
		 * @param $mxdData
		 */
		public static function writeJSON($resSocket,$mxdData){
			self::writeMessage($resSocket,json_encode($mxdData));
		}

		/**
		 * Write/Send a plain text message back to the socket resource, message will be wrapped for transport
		 * @param $resSocket
		 * @param $mxdMessage
		 */
		public static function writeMessage($resSocket,$mxdMessage){
			$mxdWrappedMessage = DataHandler::wrap($mxdMessage,$resSocket);
			self::writeRawBuffer($resSocket,$mxdWrappedMessage);
		}

		/**
		 * Writes the raw data to the selected socket resource
		 * @param $resSocket
		 * @param $mxdString
		 * @return int
		 */
		public static function writeRawBuffer($resSocket, $mxdString){
			$mxdOut = socket_write($resSocket,$mxdString,strlen($mxdString));
			return $mxdOut;
		}
	}