<?php

	namespace Packages\WebSockets\Models;

	/**
	 * CronLock - Locks the cron script so that it can not be started again whilst the cron is still running
	 * @version 1.0.0
	 * @author Dan Walker
	 */
	class CronLock{

		public static $strLockLocation = '/tmp/';

		public static function active(){

			$blOut = false;

			//Check the lock file exists
			if(file_exists(sprintf("%s/twistsocket.lock",rtrim(self::$strLockLocation,'/')))){

				//Check if their is a process running, if more than the grep it is running
				exec("ps ax | grep server.php",$arrOut);

				foreach($arrOut as $strEachProcess){
					if(strstr($strEachProcess,"server.php")){
						$blOut = true;
						break;
					}
				}

				//No process found then kill the lock file and restart the server
				if(!$blOut){
					self::destroy();
				}
			}

			return $blOut;
		}

		public static function create(){
			//Create a lock file so that the server will function
			file_put_contents(sprintf("%s/twistsocket.lock",rtrim(self::$strLockLocation,'/')),'Twist Socket Server Running: '.date('Y-m-d H:i:s'));
			chmod(sprintf("%s/twistsocket.lock",rtrim(self::$strLockLocation,'/')),0777);
		}

		public static function destroy(){
			unlink(sprintf("%s/twistsocket.lock",rtrim(self::$strLockLocation,'/')));
		}
	}