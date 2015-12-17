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

		/**
		 * Encode data into a Frame76 encoded frame to be sent back via WebSocket communication
		 * @param $strMessage
		 * @return string
		 */
		private static function encodeFrame76($strMessage){
			return chr(0).$strMessage.chr(255);
		}

		/**
		 * Decode Frame76 encoded data from the WebSocket communication
		 * @param $strMessage
		 * @return string
		 */
		private static function decodeFrame76($strMessage){
			return substr($strMessage, 1, strlen($strMessage)-2);
		}

		/**
		 * Encode data into a hybi-10 encoded frame to be sent back via WebSocket communication
		 * @param string $strPayload Payload to be hybi-10 encoded
		 * @param string $strType Frame type to be encoded
		 * @param bool $blMasked Set to true will mask the encoded data
		 * @param null $resUserSocket Socket resource to which the hybi-10 encoded data will be sent
		 * @return string hybi-10 encoded string
		 */
		private static function hybi10Encode($strPayload, $strType = 'text', $blMasked = true, $resUserSocket = null){

			$arrFrameHead = $arrMask = array();
			$intPayloadLength = strlen($strPayload);

			switch($strType){
				case 'text':
					//First byte indicates FIN, Text-Frame (10000001):
					$arrFrameHead[0] = 129;
					break;

				case 'close':
					//First byte indicates FIN, Close Frame(10001000):
					$arrFrameHead[0] = 136;
					break;

				case 'ping':
					//First byte indicates FIN, Ping frame (10001001):
					$arrFrameHead[0] = 137;
					break;

				case 'pong':
					//First byte indicates FIN, Pong frame (10001010):
					$arrFrameHead[0] = 138;
					break;
			}

			//Set mask and payload length (using 1, 3 or 9 bytes)
			if($intPayloadLength > 65535){
				
				$arrPayloadLengthBin = str_split(sprintf('%064b', $intPayloadLength), 8);
				$arrFrameHead[1] = ($blMasked === true) ? 255 : 127;
				
				for($i = 0; $i < 8; $i++){
					$arrFrameHead[$i + 2] = bindec($arrPayloadLengthBin[$i]);
				}
				
				//Most significant bit MUST be 0 (close connection if frame too big)
				if($arrFrameHead[2] > 127){
					self::dataError($resUserSocket,"most significant bit MUST be 0 (close connection if frame too big). 1004");
					return false;
				}
				
			}elseif($intPayloadLength > 125){
				
				$arrPayloadLengthBin = str_split(sprintf('%016b', $intPayloadLength), 8);
				$arrFrameHead[1] = ($blMasked === true) ? 254 : 126;
				$arrFrameHead[2] = bindec($arrPayloadLengthBin[0]);
				$arrFrameHead[3] = bindec($arrPayloadLengthBin[1]);
			}else{
				$arrFrameHead[1] = ($blMasked === true) ? $intPayloadLength + 128 : $intPayloadLength;
			}

			//Convert frame-head to string:
			foreach(array_keys($arrFrameHead) as $i){
				$arrFrameHead[$i] = chr($arrFrameHead[$i]);
			}
			
			if($blMasked === true){
				
				//Generate a random mask:
				$arrMask = array();
				for($i = 0; $i < 4; $i++){
					$arrMask[$i] = chr(rand(0, 255));
				}

				$arrFrameHead = array_merge($arrFrameHead, $arrMask);
			}
			
			$strFrame = implode('', $arrFrameHead);

			//Append payload to the frame
			for($i = 0; $i < $intPayloadLength; $i++){
				$strFrame .= ($blMasked === true) ? $strPayload[$i] ^ $arrMask[$i % 4] : $strPayload[$i];
			}

			return $strFrame;
		}

		/**
		 * Decode hybi-10 encoded data from the WebSocket communication
		 * @param string $strData The hybi-10 encoded data
		 * @param null $resUserSocket Socket resource the hybi-10 encoded data was received from
		 * @return array|bool Decoded hybi-10 payload and type
		 */
		private static function hybi10Decode($strData,$resUserSocket = null){

			$strUnmaskedPayload = '';
			$arrDecodedData = array();

			//Estimate frame type:
			$binFirstByte = sprintf('%08b', ord($strData[0]));
			$binSecondByte = sprintf('%08b', ord($strData[1]));
			$intOpcode = bindec(substr($binFirstByte, 4, 4));
			$blMasked = ($binSecondByte[0] == '1') ? true : false;
			$intPayloadLength = ord($strData[1]) & 127;

			//Close connection if unmasked frame is received:
			if($blMasked === false){
				self::dataError($resUserSocket,"close connection if unmasked frame is received. 1002");
			}

			switch($intOpcode){

				case 0:
					//Text Frame
					$arrDecodedData['type'] = 'continuation';
					break;

				case 1:
					//Text Frame
					$arrDecodedData['type'] = 'text';
					break;

				case 2:
					//Text Frame
					$arrDecodedData['type'] = 'binary';
					break;

				case 8:
					//Connection Close Frame
					$arrDecodedData['type'] = 'close';
					break;

				case 9:
					//Ping Frame
					$arrDecodedData['type'] = 'ping';
					break;

				case 10:
					//Pong Frame
					$arrDecodedData['type'] = 'pong';
					break;

				default:
					self::dataError($resUserSocket,sprintf("Close connection on unknown opcode: %s. 1003",$intOpcode));
					break;
			}

			if($intPayloadLength === 126){

				$mxdMask = substr($strData, 4, 4);
				$intPayloadOffset = 8;
				$intDataLength = bindec(sprintf('%08b', ord($strData[2])) . sprintf('%08b', ord($strData[3]))) + $intPayloadOffset;

			}elseif($intPayloadLength === 127){

				$mxdMask = substr($strData, 10, 4);
				$intPayloadOffset = 14;
				$mxdTemp = '';

				for($intCount = 0; $intCount < 8; $intCount++){
					$mxdTemp .= sprintf('%08b', ord($strData[$intCount + 2]));
				}

				$intDataLength = bindec($mxdTemp) + $intPayloadOffset;
				unset($mxdTemp);
			}else{
				$mxdMask = substr($strData, 2, 4);
				$intPayloadOffset = 6;
				$intDataLength = $intPayloadLength + $intPayloadOffset;
			}

			//If the frame is bigger than 1024bytes we will return false and wait for all the data to be transferred. (socket_recv cuts at 1024 bytes)
			if(strlen($strData) < $intDataLength){
				return false;
			}

			if($blMasked === true){

				//Unmask the payload
				for($intCount = $intPayloadOffset; $intCount < $intDataLength; $intCount++){
					$intPosition = $intCount - $intPayloadOffset;

					if(isset($strData[$intCount])){
						$strUnmaskedPayload .= $strData[$intCount] ^ $mxdMask[$intPosition % 4];
					}
				}

				$arrDecodedData['payload'] = $strUnmaskedPayload;
			}else{

				$intPayloadOffset = $intPayloadOffset - 4;
				$arrDecodedData['payload'] = substr($strData, $intPayloadOffset);
			}

			return $arrDecodedData;
		}

		/**
		 * Disconnect procedure for the payloads that have failed to be encoded/decoded successfully
		 * @param resource $resSocket Socket resource the is to be disconnected
		 * @param string $strMessage Message to be send to the socket upon disconnection
		 */
		protected static function dataError($resSocket,$strMessage){

			//Remove the socket from the list of connected sockets
			if(Sockets::isTemporary($resSocket)){
				Sockets::removeTemporary($resSocket);
			}else{
				Sockets::remove($resSocket);
			}

			//Close connection on unknown opcode
			System::log('Disconnected from server: '.$strMessage);
		}
	}