<?php

	namespace Packages\WebSockets\Models;

	class DataHandler{

		/**
		 * Wrap data to be sent securely back to the resource connection via the WebSocket server
		 * @param $strMessage
		 * @param null $resUserSocket
		 * @return bool|string
		 */
		public static function wrap($strMessage, $resUserSocket = null){

			$intSocketVersion = Sockets::getVersion($resUserSocket);

			if($intSocketVersion == 0){
				$strOut = self::encodeFrame76($strMessage);
			}else{
				$strOut = self::hybi10Encode($strMessage,'text',false,$resUserSocket);
			}

			return $strOut;
		}

		/**
		 * Unwrap the data sent to the WebSocket server by the resource connection
		 * @param string $strMessage
		 * @param null $resUserSocket
		 * @return string
		 */
		public static function unwrap($strMessage = "", $resUserSocket = null){

			$intSocketVersion = Sockets::getVersion($resUserSocket);

			if($intSocketVersion == 0){
				$strOut = self::decodeFrame76($strMessage);
			}else{
				$arrData = self::hybi10Decode($strMessage,$resUserSocket);
				$strOut = $arrData['payload'];
				//$this->systemLog($arrData['type']);
			}

			return $strOut;
		}

		private static function encodeFrame76($strMessage){
			return chr(0).$strMessage.chr(255);
		}

		private static function decodeFrame76($strMessage){
			return substr($strMessage, 1, strlen($strMessage)-2);
		}

		private static function hybi10Encode($payload, $type = 'text', $masked = true, $resUserSocket = null){

			$frameHead = $mask = array();
			$frame = '';
			$payloadLength = strlen($payload);

			switch($type){
				case 'text':
					// first byte indicates FIN, Text-Frame (10000001):
					$frameHead[0] = 129;
					break;

				case 'close':
					// first byte indicates FIN, Close Frame(10001000):
					$frameHead[0] = 136;
					break;

				case 'ping':
					// first byte indicates FIN, Ping frame (10001001):
					$frameHead[0] = 137;
					break;

				case 'pong':
					// first byte indicates FIN, Pong frame (10001010):
					$frameHead[0] = 138;
					break;
			}

			// set mask and payload length (using 1, 3 or 9 bytes)
			if($payloadLength > 65535){
				$payloadLengthBin = str_split(sprintf('%064b', $payloadLength), 8);
				$frameHead[1] = ($masked === true) ? 255 : 127;
				for($i = 0; $i < 8; $i++){
					$frameHead[$i + 2] = bindec($payloadLengthBin[$i]);
				}
				// most significant bit MUST be 0 (close connection if frame too big)
				if($frameHead[2] > 127){
					self::dataError($resUserSocket,"most significant bit MUST be 0 (close connection if frame too big). 1004");
					return false;
				}
			} elseif($payloadLength > 125){
				$payloadLengthBin = str_split(sprintf('%016b', $payloadLength), 8);
				$frameHead[1] = ($masked === true) ? 254 : 126;
				$frameHead[2] = bindec($payloadLengthBin[0]);
				$frameHead[3] = bindec($payloadLengthBin[1]);
			} else{
				$frameHead[1] = ($masked === true) ? $payloadLength + 128 : $payloadLength;
			}

			// convert frame-head to string:
			foreach(array_keys($frameHead) as $i){
				$frameHead[$i] = chr($frameHead[$i]);
			}
			if($masked === true){
				// generate a random mask:
				$mask = array();
				for($i = 0; $i < 4; $i++){
					$mask[$i] = chr(rand(0, 255));
				}

				$frameHead = array_merge($frameHead, $mask);
			}
			$frame = implode('', $frameHead);

			// append payload to frame:
			$framePayload = array();
			for($i = 0; $i < $payloadLength; $i++){
				$frame .= ($masked === true) ? $payload[$i] ^ $mask[$i % 4] : $payload[$i];
			}

			return $frame;
		}

		private static function hybi10Decode($data,$resUserSocket = null){

			$payloadLength = '';
			$mask = '';
			$unmaskedPayload = '';
			$decodedData = array();

			// estimate frame type:
			$firstByteBinary = sprintf('%08b', ord($data[0]));
			$secondByteBinary = sprintf('%08b', ord($data[1]));
			$opcode = bindec(substr($firstByteBinary, 4, 4));
			$isMasked = ($secondByteBinary[0] == '1') ? true : false;
			$payloadLength = ord($data[1]) & 127;

			// close connection if unmasked frame is received:
			if($isMasked === false){
				self::dataError($resUserSocket,"close connection if unmasked frame is received. 1002");
			}

			switch($opcode){
				// text frame:

				case 0:
					$decodedData['type'] = 'continuation';
					break;

				case 1:
					$decodedData['type'] = 'text';
					break;

				case 2:
					$decodedData['type'] = 'binary';
					break;

				// connection close frame:
				case 8:
					$decodedData['type'] = 'close';
					break;

				// ping frame:
				case 9:
					$decodedData['type'] = 'ping';
					break;

				// pong frame:
				case 10:
					$decodedData['type'] = 'pong';
					break;

				default:
					self::dataError($resUserSocket,sprintf("Close connection on unknown opcode: %s. 1003",$opcode));
					break;
			}

			if($payloadLength === 126){
				$mask = substr($data, 4, 4);
				$payloadOffset = 8;
				$dataLength = bindec(sprintf('%08b', ord($data[2])) . sprintf('%08b', ord($data[3]))) + $payloadOffset;
			} elseif($payloadLength === 127){
				$mask = substr($data, 10, 4);
				$payloadOffset = 14;
				$tmp = '';
				for($i = 0; $i < 8; $i++){
					$tmp .= sprintf('%08b', ord($data[$i + 2]));
				}
				$dataLength = bindec($tmp) + $payloadOffset;
				unset($tmp);
			} else{
				$mask = substr($data, 2, 4);
				$payloadOffset = 6;
				$dataLength = $payloadLength + $payloadOffset;
			}

			/**
			 * We have to check for large frames here. socket_recv cuts at 1024 bytes
			 * so if websocket-frame is > 1024 bytes we have to wait until whole
			 * data is transferd.
			 */
			if(strlen($data) < $dataLength){
				return false;
			}

			if($isMasked === true){
				for($i = $payloadOffset; $i < $dataLength; $i++){
					$j = $i - $payloadOffset;
					if(isset($data[$i])){
						$unmaskedPayload .= $data[$i] ^ $mask[$j % 4];
					}
				}
				$decodedData['payload'] = $unmaskedPayload;
			} else{
				$payloadOffset = $payloadOffset - 4;
				$decodedData['payload'] = substr($data, $payloadOffset);
			}

			return $decodedData;
		}

		protected static function dataError($resSocket,$strMessage){

			//Remove the socket from the list of connected sockets
			if(Sockets::isTemporary($resSocket)){
				Sockets::removeTemporary($resSocket);
			}else{
				Sockets::remove($resSocket);
			}

			// Close connection on unknown opcode
			System::log('Disconnected from server: '.$strMessage);
		}
	}