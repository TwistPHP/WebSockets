<?php

	namespace Packages\WebSockets\Models;

	class AppHandler{

		protected static $arrApps = array();

		public static function load(){

			//Load in all the Twist Hooks and register them ready for use in the system
			self::$arrApps = \Twist::framework()->hooks()->getAll('WEB_SOCKET_APPS');
		}

		/**
		 * Process request for module functionality that is not part of the core
		 * @param $strCommand
		 * @param $resUserSocket
		 * @param $arrData
		 */
		public static function process($strCommand,$resUserSocket,$arrData){

			if(array_key_exists(self::$arrApps,$strCommand)){

				$intUserID = Sockets::getData($resUserSocket,'user_id');

				//Set the current class to be accessible from the object
				self::$arrApps[$strCommand]->processRequest($arrData,$intUserID);
			}else{
				//System not found
				System::sendErrorResponse($resUserSocket,$arrData,"Invalid requested 'system' parameter");
			}
		}
	}