<?php

	namespace Packages\WebSockets\Models;

	class Scheduler{

		protected static $arrCronTab = array();
		protected static $intLastRun = 0;

		/**
		 * Register a cron in the virtual crontab
		 * @param $strTimePeriod
		 * @param $strSystem
		 * @param $strAction
		 * @param array $arrData
		 * @param null $intUserID
		 */
		public static function register($strTimePeriod,$strSystem,$strAction,$arrData = array(),$intUserID = null){

			$strKey = sha1($strSystem.$strAction.$intUserID);

			$arrMultipliers = array(
				's' => 1,
				'm' => 60,
				'h' => 3600,
				'd' => 86400,
				'w' => 604800
			);

			//Get the multiplier that can either be m,h,d,w
			$strMultiplier = substr($strTimePeriod,-1);
			$intInterval = str_replace($strMultiplier,'',$strTimePeriod);

			if(array_key_exists($strMultiplier,$arrMultipliers)){

				//Multiply the interval by the multiplier to get the amount of seconds
				$intSeconds = $intInterval * $arrMultipliers[$strMultiplier];

				$arrCronData = array(
					'interval' => $intSeconds,
					'last_run' => time(),
					'system' => $strSystem,
					'action' => $strAction,
					'user_id' => $intUserID,
					'data' => $arrData
				);

				self::$arrCronTab[$strKey] = $arrCronData;
			}else{
				echo "Error: Invalid cron time period";
			}
		}

		/**
		 * Cancel any single cron if and when required
		 * @param $strSystem
		 * @param $strAction
		 * @param null $intUserID
		 * @return bool
		 */
		public static function cancel($strSystem,$strAction,$intUserID = null){

			$blOut = false;
			$strKey = sha1($strSystem.$strAction.$intUserID);

			if(array_key_exists($strKey,self::$arrCronTab)){
				unset(self::$arrCronTab[$strKey]);
				$blOut = true;
			}

			return $blOut;
		}

		/**
		 * Cancel all crons by user ID
		 * @param $intUserID
		 * @return bool
		 */
		public static function cancelByUser($intUserID){

			$blOut = false;

			foreach(self::$arrCronTab as $strKey => $arrData){
				if(!is_null($arrData['user_id']) && $arrData['user_id'] == $intUserID){
					unset(self::$arrCronTab[$strKey]);
					$blOut = true;
				}
			}

			return $blOut;
		}

		/**
		 * Run the crontab and process the results
		 */
		public static function process(){

			//Decide if the crontab is needed to be run (once a minute only)
			if((self::$intLastRun + 1) <= time()){

				$intCronsRun = 0;

				//Reset the last run timestamp
				self::$intLastRun = time();

				//For each item in the cron tab check if the cron should be run
				foreach(self::$arrCronTab as $strKey => $arrData){

					//If the time is bigger or equal to the last run + interval
					if(time() >= ($arrData['interval'] + $arrData['last_run'])){

						$intCronsRun++;

						$resUserSocket = null;
						if(!is_null($arrData['user_id'])){
							$arrUserSockets = Users::getSockets($arrData['user_id']);
							$resUserSocket = (count($arrUserSockets)) ? array_shift($arrUserSockets) : null;
						}


						//Call the custom function
						AppHandler::process($arrData['system'],$resUserSocket,$arrData);

						//Update the last runtime for the cron
						self::$arrCronTab[$strKey]['last_run'] = time();
					}
				}

				if($intCronsRun > 0){
					System::log(sprintf('Virtual Socket CronTab: %d crons ran successfully',$intCronsRun));
					System::output();
				}
			}
		}
	}