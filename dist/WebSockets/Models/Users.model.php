<?php

	namespace Packages\WebSockets\Models;
	use \Twist\Core\Models\User\Auth;

	class Users{

		protected static $arrUsers = array();
		protected static $intGuestCount = 0;
		protected static $intAdminLevel = 0;

		/**
		 * Set the required admin level to be able to monitor the server, (Can only be called before the 'server' function is called or from withing the server itself)
		 * @param int $intLevel
		 */
		public static function setAdminLevel($intLevel){
			self::$intAdminLevel = $intLevel;
		}

	    /**
	     * Create a user record when the user has successfully connected
	     * @param $arrUserData
	     */
	    public static function createUser($arrUserData){

	        //If the user already exists just update the last connected time
	        if(array_key_exists($arrUserData['id'],self::$arrUsers)){

		        System::debug("Additional Connection - user(".$arrUserData['id'].")");
	            self::logUserConnection($arrUserData['id']);
	        }else{

		        System::debug("New Connection - user(".$arrUserData['id'].")");

	            //Set the user to admin status if required (in the settings array)
	            self::$arrUsers[$arrUserData['id']] = array(
	                'id' => $arrUserData['id'],
	                'name' => sprintf("%s %s",$arrUserData['firstname'],$arrUserData['surname']),
	                'email' => $arrUserData['email'],
	                'level' => $arrUserData['level'],
	                'time_connected' => time(),
	                'last_connected' => time(),
	                'global_settings' => array(),
	                'settings' => array(
	                    'system_admin' => ($arrUserData['level'] == self::$intAdminLevel)
	                )
	            );
	        }

	        return $arrUserData['id'];
	    }

	    /**
	     * Remove a user when no longer logged in
	     * @param $intUserID
	     */
	    public static function removeUser($intUserID){
	        unset(self::$arrUsers[$intUserID]);
	    }

	    /**
	     * Get a users details by ID
	     * @param $intUserID
	     * @return mixed
	     */
	    public static function get($intUserID){
	        return (array_key_exists($intUserID,self::$arrUsers)) ? self::$arrUsers[$intUserID] : array();
	    }

	    /**
	     * Get all users that are logged in
	     * @param $intUserID
	     * @return mixed
	     */
	    public static function getAll(){
	        return self::$arrUsers;
	    }

	    /**
	     * Log the time that a user has opened a new connection, helps keep user accounts from going stale
	     * @param $intUserID
	     */
	    public static function logUserConnection($intUserID){
	        self::$arrUsers[$intUserID]['last_connected'] = time();
	    }

	    /**
	     * Validate the users login credentials, create their connection and user record or reject them from the system
	     * @param $arrData
	     * @param $resSocket
	     */
	    public static function login($arrData,$resSocket){

	        $arrResponse = array(
	            'instance' => (array_key_exists('instance',$arrData)) ? $arrData['instance'] : null,
	            'system' => 'twist',
	            'action' => 'login',
	            'message' => '',
	            'data' => array()
	        );

	        //Detect the correct configuration of parameters provided
			$strUID = (array_key_exists('uid',$arrData['data'])) ? $arrData['data']['uid'] : 'guest';
	        $strPassword = (array_key_exists('password',$arrData['data'])) ? $arrData['data']['password'] : null;

	        //Get the users identification data
		    $arrUserData = self::requestUserData($strUID,$strPassword);

	        //If the user is valid then connect the user
	        if($arrUserData['status']){

		        if(Sockets::create($resSocket)){

			        Sockets::setData($resSocket,'user_id',$arrUserData['data']['id']);
			        $intUserID = self::createUser($arrUserData['data']);

			        //Now send through the valid login details to the user when they login
			        $arrResponse['message'] = $arrUserData['message'];
			        $arrResponse['data'] = array(
				        'user_id' => $intUserID,
				        'valid' => (!is_null($intUserID)) ? true : false
			        );

		        }else{
			        $arrResponse['action'] = 'login-failed';
			        $arrResponse['message'] = 'Failed to login, unknown connection issue';
		        }

		        Sockets::writeJSON($resSocket,$arrResponse);
	        }else{

	            //Failed to login, tell the user and destroy the connection
	            $arrResponse['action'] = 'login-failed';
		        $arrResponse['message'] = $arrUserData['message'];

		        Sockets::writeJSON($resSocket,$arrResponse);
		        Sockets::removeTemporary($resSocket);
	        }

		    //Log user status to the admin users
		    System::stats();
		}

		public static function logout($arrData,$resSocket){
			Sockets::remove($resSocket);
		}

	    /**
	     * Get the users data by email and password of Guest Details
	     * @param $strUID
	     * @param null $strPassword
	     * @return array
	     */
	    protected static function requestUserData($strUID,$strPassword = null){

	        $arrOut = array(
		        'status' => false,
		        'message' => 'Login method not allowed',
		        'data' => array()
	        );

		    //Get the user data if valid credentials
		    if($strUID != 'guest' && !strstr($strUID,'@')){

			    //If the UID dosnt contain an @ symbol treat it as a session Key of a logged in user
			    $intUserID = Auth::SessionHandler()->validateCode($strUID,false);

			    if(!is_null($intUserID) && $intUserID > 0){
				    $arrOut['data'] = \Twist::User() -> getData($intUserID);
				    $arrOut['status'] = true;
				    $arrOut['message'] = sprintf('Connected to Twist WebSocket Server as %s %s',$arrOut['data']['firstname'],$arrOut['data']['surname']);
			    }

		    }elseif(\Twist::framework()->setting('WS_ALLOW_GUEST_LOGIN') && $strUID == 'guest' && is_null($strPassword)){

			    self::$intGuestCount++;

			    //If Guest Users are allowed then set a special guest ID prefixed with a 'g'
			    $arrOut['status'] = true;
			    $arrOut['message'] = 'Connected to Twist WebSocket Server as Guest';
			    $arrOut['data'] = array(
				    'id' => 'g'.self::$intGuestCount,
				    'firstname' => 'Guest',
				    'surname' => 'User',
				    'level' => 1,
				    'email' => sprintf('guest@%s',\Twist::framework()->setting('HTTP_HOST')),
				    'session_key' => ''
			    );

		    }elseif(\Twist::framework()->setting('WS_ALLOW_REMOTE_LOGIN')){

			    //Authenticate the user, grab the user data and then logout (remove session data)
			    Auth::validate($strUID,$strPassword);
			    $arrSession = Auth::current();
			    Auth::logout();

			    $arrOut['status'] = $arrSession['status'];
			    $arrOut['message'] = ($arrSession['status']) ? sprintf('Connected to Twist WebSocket Server as %s %s',$arrSession['user_data']['firstname'],$arrSession['user_data']['surname']) : $arrSession['message'];
			    $arrOut['data'] = (array_key_exists('user_data',$arrSession)) ? $arrSession['user_data'] : array();
		    }

	        return $arrOut;
	    }

		/**
		 * Send only to connections that belong to the passed in userID
		 * @param $intUserID
		 * @param $arrResponseData
		 */
		public static function sendAll($arrResponseData,$arrHasUserSetting = null,$arrHasSocketData = null){

			foreach(self::getAll() as $arrEachUser){

				$blUserMatch = true;

				//Run through the user settings and match any required settings, for example only send the the users connections when 'system_admin' is set to true
				if(is_array($arrHasUserSetting)){
					foreach($arrHasUserSetting as $strKey => $mxdValue){
						if(array_key_exists($strKey,$arrEachUser['settings']) && $arrEachUser['settings'][$strKey] != $mxdValue){
							$blUserMatch = false;
							break;
						}
					}
				}

				//If the user is Admin then send to all their connections
				if($blUserMatch){
					self::send($arrEachUser['id'],$arrResponseData,$arrHasSocketData);
				}
			}
		}

		/**
		 * Send only to connections that belong to the passed in userID
		 * @param $intUserID
		 * @param $arrResponseData
		 * @param $arrHasConnectionSetting
		 */
		public static function send($intUserID,$arrResponseData,$arrHasSocketData = null){

			foreach(Sockets::getAllConnected() as $arrEachSocket){

				//Check User ID for matches
				if($arrEachSocket['data']['user_id'] == $intUserID){

					$blConnectionMatch = true;

					//Run through the connection data and match any required data, for example only send the the users connections when 'system_admin_log' is set to 1
					if(is_array($arrHasSocketData)){
						foreach($arrHasSocketData as $strKey => $mxdValue){
							if(Sockets::getData($arrEachSocket['socket'],$strKey) != $mxdValue){
								$blConnectionMatch = false;
								break;
							}
						}
					}

					if($blConnectionMatch){
						Sockets::writeJSON($arrEachSocket['socket'],$arrResponseData);
					}
				}
			}
		}

		/**
		 * Send only to connections that belong to Admin users
		 * @param $arrResponseData
		 */
		public static function sendAdmin($arrResponseData,$arrHasUserSetting = null,$arrHasSocketData = null){

			if(!is_array($arrHasUserSetting)){
				$arrHasUserSetting = array();
			}

			$arrHasUserSetting['system_admin'] = true;

			self::sendAll($arrResponseData,$arrHasUserSetting,$arrHasSocketData);
		}

		/**
		 * Send out a list of all active users on the server to the requesting socket
		 * @param $resUserSocket
		 * @param $arrData
		 * @return array
		 */
		public static function sendUserList($resUserSocket,$arrData){

			$arrUsers = $arrOut = array();

			foreach(self::getAll() as $arrEachUser){
				$arrUsers[$arrEachUser['name']] = array('user_id' => $arrEachUser['id'],'user_name' => $arrEachUser['name']);
			}

			//Sort the list and then rebuild with numerical keys
			ksort($arrUsers);
			foreach($arrUsers as $arrEachUser){
				$arrOut[] = $arrEachUser;
			}

			$arrResponse = array(
				'instance' => (array_key_exists('instance',$arrData)) ? $arrData['instance'] : null,
				'system' => 'twist',
				'action' => $arrData['action'],
				'message' => 'All active users',
				'data' => array('users' => $arrOut)
			);

			Sockets::writeJSON($arrResponse,$resUserSocket);
		}

		/**
		 * Get all the connections that belong to a particular user
		 * @param $intUserID
		 * @return array
		 */
		public static function getSockets($intUserID){

			$arrOut = array();

			foreach(Sockets::getAllConnected() as $arrEachSocket){
				//Check User ID for matches
				if($arrEachSocket['data']['user_id'] == $intUserID){
					$arrOut[] = $arrEachSocket['socket'];
				}
			}

			return $arrOut;
		}
	}
