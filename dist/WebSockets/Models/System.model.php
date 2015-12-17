<?php

	namespace Packages\WebSockets\Models;

	class System{

		protected static $blDebug = false;

		protected static $intUpTime = 0;

		protected static $arrLog = array();
		protected static $arrSocketLog = array();
		protected static $arrServerHeaders = array();

		protected static $intDataBytesIn = 0;
		protected static $intDataBytesOut = 0;

		protected static $intRequestsIn = 0;
		protected static $intRequestsOut = 0;

		protected static $intBandwidthTime = 0;
		protected static $arrBandwidthData = array();

		/**
		 * Set messages to the server header array, used during debug
		 * @param string $strMessage
		 */
		public static function serverHeader($strMessage = ""){
			self::$arrServerHeaders[] = $strMessage;
		}

		public static function upTime($intStartTime = null){

			if(!is_null($intStartTime)){
				self::$intUpTime = $intStartTime;
			}

			return (time() - self::$intUpTime);
		}

		/**
		 * When debug is enabled, output a message to the command line server output
		 * @param string $strMessage
		 */
		public static function debug($strMessage = ""){
			if(self::$blDebug){
				echo $strMessage."\n";
			}
		}

		public static function log($strMessage){
			self::$arrLog[] = $strMessage;
			(count(self::$arrLog) > 20) ? array_shift(self::$arrLog) : null;
		}

		/**
		 * Log a message to the server log, max this can hold is 20 items (to prevent memory leek)
		 * @param $strMessage
		 * @param string $strDataFlow
		 */
		public static function socketLog($strMessage,$strDataFlow = 'outgoing'){

			($strDataFlow == 'incoming') ? self::$intRequestsIn ++ : self::$intRequestsOut ++;
			($strDataFlow == 'incoming') ? self::$intDataBytesIn += strlen($strMessage) : self::$intDataBytesOut += strlen($strMessage);

			self::$arrSocketLog[] = $strMessage;

			(count(self::$arrSocketLog) > 20) ? array_shift(self::$arrSocketLog) : null;
		}

		public static function output(){

			$intTotalUpTime = self::upTime();

			//echo \TwistPHPInstance::getUsage()."\n";
			//echo strlen(print_r($this,true))."\n";
			//echo print_r(\TwistErrorHandler::$arrErrorLog,true)."\n";

			if(true){

				$arrUsers = Users::getAll();

				(ob_get_length() > 0) ? ob_end_clean() : null;
				system("clear");
				echo implode("\n",self::$arrServerHeaders);
				echo "----------------------------------------------------------------------------------------------------\n";


				echo sprintf("Server Uptime\t: %s\n",\Twist::DateTime() -> getTimePeriod($intTotalUpTime));
				echo sprintf("Server Requests\t: %s in / %s out\n",
					self::$intRequestsIn,
					self::$intRequestsOut
				);
				echo sprintf("Server Traffic\t: %s in / %s out\n",
					\Twist::File()->bytesToSize(self::$intDataBytesIn),
					\Twist::File()->bytesToSize(self::$intDataBytesOut)
				);
				echo sprintf("Memory Usage\t: %s\n",
					\Twist::File()->bytesToSize(memory_get_usage())
				);
				echo "----------------------------------------------------------------------------------------------------\n";
				echo sprintf("Active Users\t: %s\n",
					count($arrUsers)
				);
				echo sprintf("Connections\t: %s\n",
					count(Sockets::getAllConnected())
				);
				$strUserList = "Active List\t: ";
				foreach($arrUsers as $arrEachUser){
					$strUserList .= sprintf("%s (%d), ",$arrEachUser['name'],count(Users::getSockets($arrEachUser['id'])));
					//$strUserList .= print_r($arrEachUser,true);
				}
				echo rtrim(trim($strUserList),',')."\n";
				
				/**echo sprintf("Active Rooms\t: %s (%d Fixed / %d Dynamic)\n",
					count($this->resSocketModules['SocketChat']->arrChatRooms),
					0,
					0
				);*/
				
				echo "----------------------------------------------------------------------------------------------------\n\n";
				echo implode("\n",self::$arrSocketLog);
				echo "\n----------------------------------------------------------------------------------------------------\n\n";
				echo implode("\n",self::$arrLog);
			}

			self::stats();
		}

		/**
		 * Send out the admin stats to any user that requests them
		 */
		public static function stats(){

			if(is_array(self::$arrBandwidthData) && count(self::$arrBandwidthData) == 0){
				self::$arrBandwidthData = array(
					'last_bytes_in' => 0,
					'last_bytes_out' => 0,
					'peak_rate' => 0,
					'previous_bandwidth_in' => array('unit' => 0, 'format' => '0B/s'),
					'previous_bandwidth_out' => array('unit' => 0, 'format' => '0B/s'),
					'last_monitor_request' => 0,
				);
			}

			//Only output the monitor data once a secound
			if(self::$arrBandwidthData['last_monitor_request'] == 0 || (time() - self::$arrBandwidthData['last_monitor_request']) >= 1){

				//Reset the counter
				self::$arrBandwidthData['last_monitor_request'] = time();

				$intTotalUpTime = self::upTime();
				$arrServer = array();

				$arrUsers = Users::getAll();
				$arrConnections = Sockets::getAllConnected();
				
				$arrServer['uptime'] = array('unit' => $intTotalUpTime,'format' => \Twist::DateTime() -> getTimePeriod($intTotalUpTime));

				$arrServer['request_in'] = array('unit' => self::$intRequestsIn,'format' => self::$intRequestsIn);
				$arrServer['request_out'] = array('unit' => self::$intRequestsOut,'format' => self::$intRequestsOut);

				$arrServer['traffic_in'] = array('unit' => self::$intDataBytesIn,'format' => \Twist::File()->bytesToSize(self::$intDataBytesIn));
				$arrServer['traffic_out'] = array('unit' => self::$intDataBytesOut,'format' => \Twist::File()->bytesToSize(self::$intDataBytesOut));

				$arrServer['mem_usage'] = array('unit' => memory_get_usage(),'format' => \Twist::File()->bytesToSize(memory_get_usage()));
				$arrServer['users'] = array('unit' => count($arrUsers),'format' => count($arrUsers));
				$arrServer['connections'] = array('unit' => count($arrConnections),'format' => count($arrConnections));


				//Calculate the avrage speed
				self::$intBandwidthTime = (self::$intBandwidthTime == 0) ? self::$intUpTime : self::$intBandwidthTime;
				$intLastSpeedCheck = (time() - self::$intBandwidthTime);

				if($intLastSpeedCheck > 2){
					$intBytesPerSecOut = floor((self::$intDataBytesOut - self::$arrBandwidthData['last_bytes_out']) / $intLastSpeedCheck);
					$arrServer['current_bandwidth_out'] = array('unit' => $intBytesPerSecOut,'format' => sprintf('%s/s',\Twist::File()->bytesToSize($intBytesPerSecOut)));

					$intBytesPerSecIn = floor((self::$intDataBytesIn - self::$arrBandwidthData['last_bytes_in']) / $intLastSpeedCheck);
					$arrServer['current_bandwidth_in'] = array('unit' => $intBytesPerSecIn,'format' => sprintf('%s/s',\Twist::File()->bytesToSize($intBytesPerSecIn)));

					//Now log and reset all the stats
					self::$arrBandwidthData['previous_bandwidth_in'] = $arrServer['current_bandwidth_in'];
					self::$arrBandwidthData['previous_bandwidth_out'] = $arrServer['current_bandwidth_out'];
					self::$arrBandwidthData['last_bytes_in'] = self::$intDataBytesIn;
					self::$arrBandwidthData['last_bytes_out'] = self::$intDataBytesOut;
					self::$intBandwidthTime = time();
				}else{
					$arrServer['current_bandwidth_in'] = self::$arrBandwidthData['previous_bandwidth_in'];
					$arrServer['current_bandwidth_out'] = self::$arrBandwidthData['previous_bandwidth_out'];
				}

				/**
				if(array_key_exists('SocketChat',$this->resSocketModules)){

					$intFixed = $intDynamic = 0;

					foreach($this->resSocketModules['SocketChat']->arrChatRooms as $arrEachRoom){
						($arrEachRoom['fixed']) ? $intFixed++ : $intDynamic++;
					}

					$arrServer['rooms'] = array('unit' => count($this->resSocketModules['SocketChat']->arrChatRooms),'format' => sprintf("%s (%d Fixed / %d Dynamic)",
						count($this->resSocketModules['SocketChat']->arrChatRooms),
						$intFixed,
						$intDynamic
					));
				}*/

				$arrUserOut = array();
				foreach($arrUsers as $arrEachUser){

					$arrConnectionData = array();
					foreach($arrConnections as $arrEachConnection){

						if($arrEachConnection['data']['user_id'] == $arrEachUser['id']){

							$arrConnectionData[] = array(
								'viewStatus' => (array_key_exists('viewStatus',$arrEachConnection['data'])) ? $arrEachConnection['data']['viewStatus'] : '',
								'activeStatus' => (array_key_exists('activeStatus',$arrEachConnection['data'])) ? $arrEachConnection['data']['activeStatus'] : '',
								'currentURI' => (array_key_exists('currentURI',$arrEachConnection['data'])) ? $arrEachConnection['data']['currentURI'] : '',
								'ip' => $arrEachConnection['ip'],
								'port' => $arrEachConnection['port']
							);
						}
					}

					$arrUserOut[] = array(
						'id' => $arrEachUser['id'],
						'name' => $arrEachUser['name'],
						'connections' => $arrConnectionData
					);
				}

				/**
				$arrChatOut = array();

				if(array_key_exists('SocketChat',$this->resSocketModules) && is_array($this->resSocketModules['SocketChat']->arrChatRooms)){

					foreach($this->resSocketModules['SocketChat']->arrChatRooms as $arrEachRoom){

						$strUserList = "";
						foreach($arrEachRoom['users'] as $intUserID){
							$arrUserData = $this->resUsers->getUser($intUserID);
							$strUserList .= sprintf("%s, ",$arrUserData['name']);
						}
						$strUserList = rtrim(trim($strUserList),',')."\n";

						$arrChatOut[] = array(
							'gid' => $arrEachRoom['id'],
							'name' => $arrEachRoom['name'],
							'users' => $strUserList
						);
					}
				}*/

				$arrResponseData = array(
					'instance' => '',
					'system' => 'twist',
					'action' => 'debug',
					'message' => 'System Stats',
					'data' => array('server' => $arrServer,'users' => $arrUserOut)
					//'data' => array('server' => $arrServer,'users' => $arrUserOut,'chat' => $arrChatOut)
				);

				Users::sendAdmin($arrResponseData,null,array('system_admin_log' => true));
			}
		}

		/**
		 * Send out the error responses message to the current resource
		 * @param $resUserSocket
		 * @param $arrData
		 * @param $strMessage
		 */
		public static function sendErrorResponse($resUserSocket,$arrData,$strMessage){

			$arrResponse = array(
				'instance' => (array_key_exists('instance',$arrData)) ? $arrData['instance'] : null,
				'system' => 'twist',
				'action' => 'error',
				'message' => $strMessage,
				'data' => array()
			);

			Sockets::writeJSON($resUserSocket, $arrResponse);
		}

	}