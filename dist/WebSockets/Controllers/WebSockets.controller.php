<?php

	namespace Packages\WebSockets\Controllers;

	use Packages\WebSockets\Models\AppHandler;
	use Packages\WebSockets\Models\CronLock;
	use Packages\WebSockets\Models\DataHandler;
	use Packages\WebSockets\Models\Scheduler;
	use Packages\WebSockets\Models\Sockets;
	use Packages\WebSockets\Models\System;
	use Packages\WebSockets\Models\Users;

	/**
	 * Class WebSockets Controller
	 * @package Packages\WebSockets\Controllers
	 */
	class WebSockets{

		protected $resMasterSocket;
		protected $blShutdownServer = false;

		public function __construct(){

			//Set the default required admin level
			Users::setAdminLevel(\Twist::framework()->setting('WS_REQUIRED_ADMIN_LEVEL'));
		}

		/**
		 * The main and only function needed to be called to initialise the socket server
		 * @param null $strHostAddress
		 * @param null $intPort
		 */
		public function server($strHostAddress = null, $intPort = null){

			\Twist::Session() -> start();

			\Twist::framework()->register()->cancelHandler('error');
			\Twist::framework()->register()->cancelHandler('fatal');
			\Twist::framework()->register()->cancelHandler('exception');

			if(is_null($strHostAddress)){
				$strHostAddress = \Twist::framework()->setting('WS_SERVER_HOST');
			}

			if(is_null($intPort)){
				$intPort = \Twist::framework()->setting('WS_SERVER_PORT');
			}

			CronLock::$strLockLocation = __DIR__.'/../lock/';

			//Remove the lock on complete failure, fatal error and exception
			\Twist::framework()->register()->shutdownEvent('CronLock','\Packages\WebSockets\Models\CronLock', "destroy");

			//Only start if their is no lock file present
			if(!CronLock::active()){

				//Create a lock file so that the server will function
				CronLock::create();

				$this->listen($strHostAddress, $intPort);

				//Remove the lock on safe shutdown of the server
				CronLock::destroy();
			}
		}

		/**
		 * Main function that runs in a forever loop to keep listening on all required ports
		 * @param $strHostAddress
		 * @param $intPort
		 */
		protected function listen($strHostAddress, $intPort){

			System::upTime(time());

			error_reporting(E_ALL);
			set_time_limit(0);
			ob_implicit_flush(true);

			//Load in all the registered WebSocket Apps
			AppHandler::load();

			$this->resMasterSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)	or die("socket_create() failed");
			socket_set_option($this->resMasterSocket, SOL_SOCKET, SO_REUSEADDR, 1)	or die("socket_option() failed");
			socket_bind($this->resMasterSocket, $strHostAddress, $intPort)			or die("socket_bind() failed");
			socket_listen($this->resMasterSocket, 20)								or die("socket_listen() failed");

			System::serverHeader(sprintf("Server Started\t: %s",date('Y-m-d H:i:s')));
			System::serverHeader(sprintf("Listening on\t: %s port %s",$strHostAddress,$intPort));
			System::serverHeader(sprintf("Master socket\t: %s\n",$this->resMasterSocket));
			System::output();

			while(true){

				//Run the Scheduled Tasks on every iteration of the while loop
				Scheduler::process();
				System::output();

				//Grab an array of all the sockets that are connected
				$arrSockets = Sockets::getAll($this->resMasterSocket);

				$resWrite = null;
				$resExcept = null;

				//Proceed to the next iteration if no sockets are currently active
				if(socket_select($arrSockets, $resWrite, $resExcept, 1) < 1){
					continue;
				}

				//Go through each socket and process accordingly
				foreach($arrSockets as $resClientSocket){

					if($resClientSocket == $this->resMasterSocket){
						$resNewClient = socket_accept($this->resMasterSocket);

						if($resNewClient < 0) {
							System::debug("socket_accept() failed");
							continue;
						}else{
							$this->connect($resNewClient);
							System::output();
						}
					}else{

						//8192
						$intBytes = socket_recv($resClientSocket, $strBuffer, 8192,0);

						//No bytes returned then disconnect the user from the system
						if($intBytes === 0 || $intBytes == 0) {
							$this->disconnect($resClientSocket,"No bytes returned then disconnect the user from the system");
						}else{
							//If the socket has not completed the handshake process yet then handshake now
							if(Sockets::getHandshake($resClientSocket) === false){
								$this->handshake($resClientSocket, $strBuffer);
							}else{

								$strDecodedData = DataHandler::unwrap($strBuffer,$resClientSocket);

								//Process the request message as the user is already valid and confirmed
								$this->process($resClientSocket, $strDecodedData);
							}
						}

						System::output();
					}
				}

				//If the server is to be shutdown then kill the server now
				if($this->blShutdownServer == true){
					echo "\n\n############################\n# Server is shutting down! #\n############################\n\n";
					break;
				}
			}

			//Close the server socket as it is not needed
			socket_close($this->resMasterSocket);
		}

		/**
		 * Create a Temp connection record for the new connection
		 * @param $resSocket
		 */
		protected function connect($resSocket){
			$arrTempData = Sockets::createTemporary($resSocket);
			System::log(sprintf("Connection to server: %s",$arrTempData['ip']));
		}

		/**
		 * Disconnect a socket connection when required, resource could be temp or verified
		 * @param $resSocket
		 * @param string $strErrorMessage
		 * @param bool $blRebootAlert
		 */
		protected function disconnect($resSocket,$strErrorMessage = "Unknown Reason for disconnect",$blRebootAlert = false){

			if($blRebootAlert == true){
				$arrRebootData = array(
					'instance' => '',
					'system' => 'twist',
					'action' => 'restart',
					'message' => $strErrorMessage,
					'data' => array('time' => ((60 - date('s')) + 5))
				);

				Sockets::writeJSON($resSocket,$arrRebootData);
			}

			//Remove the socket from the list of connected sockets
			if(Sockets::isTemporary($resSocket)){
				Sockets::removeTemporary($resSocket);
			}else{
				Sockets::remove($resSocket);
			}

			System::stats();
			System::log(sprintf("Disconnected from server: %s",$strErrorMessage));
		}

		/**
		 * Shutdown the server sending all users the disconnect reboot message
		 */
		protected function shutdownServer(){

			//Get all user and temp sockets and close them down
			$arrSockets = Sockets::getAll();

			foreach($arrSockets as $resEachSocket){
				$this->disconnect($resEachSocket,'Server going down to reboot!',true);
			}

			$this->blShutdownServer = true;
		}

		/**
		 * Preform the handshake protocol between the server and the client, after this the client is free to communicate with the server
		 * @param $resSocket
		 * @param $strBuffer
		 * @return bool
		 */
		protected function handshake($resSocket, $strBuffer){

			System::debug("\nRequesting handshake...");
			System::debug($strBuffer);

			list($strRequestURI, $strHost, $strOriginAddress, $intKey1, $intKey2, $strLast8Bytes, $strAcceptKey, $intProtocolVersion, $strProtocol) = $this->getHeaders($strBuffer);

			System::debug("Handshaking...");
			Sockets::setTemporaryVersion($resSocket,$intProtocolVersion);

			//echo "\n\n".$intProtocolVersion."\n\n";

			if($intProtocolVersion == '13' || $intProtocolVersion == '8'){

				// Generate our Socket-Accept key based on the IETF specifications
				//$strReturnAcceptKey = $strAcceptKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
				//$strReturnAcceptKey = sha1($strReturnAcceptKey, true);
				//$strReturnAcceptKey = base64_encode($strReturnAcceptKey);

				$strReturnAcceptKey = base64_encode(pack('H*', sha1($strAcceptKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

				$arrUpgradeHeaders = array(
					'HTTP/1.1 101 Switching Protocols',
					'Upgrade: websocket',
					'Connection: Upgrade',
					sprintf('Sec-WebSocket-Accept: %s',$strReturnAcceptKey)
				);

				//Only return the protocol header if originally sent it.
				if(!is_null($strProtocol)){
					$arrUpgradeHeaders[] = sprintf('Sec-WebSocket-Protocol: %s',$strProtocol);
				}

				//Set the end bit
				$arrUpgradeHeaders[] = '';

			}else{

				$arrUpgradeHeaders = array(
					'HTTP/1.1 101 WebSocket Protocol Handshake',
					'Upgrade: WebSocket',
					'Connection: Upgrade',
					sprintf('Sec-WebSocket-Origin: %s',$strOriginAddress),
					sprintf('Sec-WebSocket-Location: ws://%s%s',$strHost,$strRequestURI),
					'',
					$this->calculateKey($intKey1, $intKey2, $strLast8Bytes)
				);
			}

			$strUpgradeHeader = '';
			foreach($arrUpgradeHeaders as $strEachHeader){
				$strUpgradeHeader .= sprintf("%s\r\n",$strEachHeader);
			}
			//$strUpgradeHeader .= chr(0);

			Sockets::writeRawBuffer($resSocket,$strUpgradeHeader);
			Sockets::setTemporaryHandshake($resSocket,true);

			//Send the safari fix so that safari wont error upon connection
			$arrSafariFix = array(
				'instance' => null,
				'system' => 'twist-safari-fix',
				'action' => '',
				'message' => 'Safari Fix',
				'data' => array()
			);

			Sockets::writeJSON($resSocket,$arrSafariFix);

			return true;
		}

		/**
		 * Calculate the Key used to complete the handshake for users on Socket versions other than 13 and 8
		 * @param $intKey1
		 * @param $intKey2
		 * @param $strLast8Bytes
		 * @return string
		 */
		protected function calculateKey($intKey1, $intKey2, $strLast8Bytes){

			//Get the numbers
			preg_match_all('/([\d]+)/', $intKey1, $intKey1Num);
			preg_match_all('/([\d]+)/', $intKey2, $intKey2Num);

			//Number crunching [/bad pun]
			System::debug("Key1: ".$intKey1Num = implode($intKey1Num[0]));
			System::debug("Key2: ".$intKey2Num = implode($intKey2Num[0]));

			//Count spaces
			preg_match_all('/([ ]+)/', $intKey1, $intKey1Space);
			preg_match_all('/([ ]+)/', $intKey2, $intKey2Space);

			//How many spaces did it find?
			System::debug("Key1 Spaces: ".$intKey1Space = strlen(implode($intKey1Space[0])));
			System::debug("Key2 Spaces: ".$intKey2Space = strlen(implode($intKey2Space[0])));

			if($intKey1Space == 0 | $intKey2Space == 0) {
				System::debug("Invalid key");
				return '';
			}

			//Get the 32bit secret key, minus the other thing
			$intKey1Secret = pack("N", $intKey1Num / $intKey1Space);
			$intKey2Secret = pack("N", $intKey2Num / $intKey2Space);

			//This needs checking, I'm not completely sure it should be a binary string
			return md5($intKey1Secret.$intKey2Secret.$strLast8Bytes, 1); //The result, I think
		}

		/**
		 * Get the headers passed by the current request, used during the handshake process
		 * @param $strRequest
		 * @return array
		 */
		protected function getHeaders($strRequest){

			$strRequestURI = $strHost = $strOriginAddress = $strAcceptKey = $intProtocolVersion = $strProtocol = $strSecretKey1 = $strSecretKey2 = $strLast8Bytes = null;

			if(preg_match("/GET (.*) HTTP/", $strRequest, $arrMatch)){
				$strRequestURI = $arrMatch[1];
			}

			if(preg_match("/Host: (.*)\r\n/", $strRequest, $arrMatch)){
				$strHost = $arrMatch[1];
			}

			if(preg_match("/Origin: (.*)\r\n/", $strRequest, $arrMatch)){
				$strOriginAddress = $arrMatch[1];
			}

			if(preg_match("/Sec-WebSocket-Version: (.*)\r\n/", $strRequest, $arrMatch)){
				$intProtocolVersion = $arrMatch[1];
			}

			if(preg_match("/Sec-WebSocket-Protocol: (.*)\r\n/", $strRequest, $arrMatch)){
				$strProtocol = $arrMatch[1];
			}

			if(preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $strRequest, $arrMatch)){
				$strAcceptKey = $arrMatch[1];
			}

			if(preg_match("/Sec-WebSocket-Key1: (.*)\r\n/", $strRequest, $arrMatch)){
				System::debug("Sec Key1: ".$strSecretKey1 = $arrMatch[1]);
			}

			if(preg_match("/Sec-WebSocket-Key2: (.*)\r\n/", $strRequest, $arrMatch)){
				System::debug("Sec Key2: ".$strSecretKey2 = $arrMatch[1]);
			}

			if($arrMatch = substr($strRequest, -8)){
				System::debug("Last 8 bytes: ".$strLast8Bytes = $arrMatch);
			}

			return array($strRequestURI, $strHost, $strOriginAddress, $strSecretKey1, $strSecretKey2, $strLast8Bytes, $strAcceptKey, $intProtocolVersion, $strProtocol);
		}

		protected function objectArray($arrResponseData){

			$arrOut = array();

			if(!is_null($arrResponseData)){

				foreach($arrResponseData as $strKey => $mxdValue){
					$mxdValue = (is_object($mxdValue)) ? (array) $mxdValue : $mxdValue;
					if(is_array($mxdValue)){
						$arrOut[$strKey] = $this->objectArray($mxdValue);
					}else{
						$arrOut[$strKey] = $mxdValue;
					}
				}
			}

			return $arrOut;
		}

		function parseString($strMessageData){

			$arrData = json_decode($strMessageData,true);
			$arrOut = $this->objectArray($arrData);

			System::socketLog(sprintf("--> %s",$strMessageData),'incoming');
			return $arrOut;
		}

		/**
		 * Process the incoming command, default commands have a system of twist
		 * @param $resUserSocket
		 * @param $strMessageData
		 */
		protected function process($resUserSocket, $strMessageData){

			$arrData = $this->parseString($strMessageData);

			//Check that we have a valid response
			if(is_array($arrData) && count($arrData)){

				//Check that a valid system call has been passed in
				if(array_key_exists('instance',$arrData) &&
					array_key_exists('system',$arrData) &&
					array_key_exists('action',$arrData) &&
					array_key_exists('data',$arrData)){

					switch($arrData['system']){

						case'twist':

							switch($arrData['action']){
								case'login':
									Users::login($arrData,$resUserSocket);
									break;

								case'logout':
									Users::logout($arrData,$resUserSocket);
									break;

								case'debug':
									//Toggle the admin log for this connection, only allowing toggle of connections that belong to admins
									$blCurrentStatus = Sockets::getData($resUserSocket,'system_admin_log');
									Sockets::setData($resUserSocket,'system_admin_log',($blCurrentStatus) ? false : true);
									System::stats();
									break;

								case'restart':
									//Shutdown the server and it will restart in 1 minute
									$this->shutdownServer();
									break;

								case'active':
								case'inactive':
									//Set the connections current Active status (clicked in the last 30 seconds or not)
									Sockets::setData($resUserSocket,'activeStatus',array('status' => $arrData['action'],'updated' => time()));
									break;

								case'blur':
								case'focus':
									//Set the connections current View status (focused on browser tab or not)
									Sockets::setData($resUserSocket,'viewStatus',array('status' => $arrData['action'],'updated' => time()));
									break;

								case'uri':
									//Set the connections current URI (The page of the website that the connection is coming from)
									Sockets::setData($resUserSocket,'currentURI',$arrData['data']);
									break;

								case'users':
									Users::sendUserList($resUserSocket,$arrData);
									break;
							}

							break;

						//This is where the magic happens, any module can be called here using the correct command
						default:
							AppHandler::process($arrData['system'],$resUserSocket,$arrData);
							break;
					}

				}else{
					//Error invalid params
					System::sendErrorResponse($resUserSocket,$arrData,'Invalid request parameters');
				}
			}
		}
	}