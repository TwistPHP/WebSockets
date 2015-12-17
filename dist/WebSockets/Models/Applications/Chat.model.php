<?php

class SocketChat{

    var $strSocketListenCommand = 'chat';
	var $objParent = null;

	var $intFixedRoomCount = 0;
	var $intDynamicRoomCount = 1000;
	var $arrChatRooms = array();

	function __construct($resParentObject){

		$this->objParent = $resParentObject;

		/** IMPORT ALL THE ROOMS TO THE SERVER CACHE */
		$this->importRooms();
	}

	function createChat($strRoomName,$blFixedRoom = false){

		if($blFixedRoom){
			$this->intFixedRoomCount++;
			$intRoomID = $this->intFixedRoomCount;
		}else{
			$this->intDynamicRoomCount++;
			$intRoomID = $this->intDynamicRoomCount;
		}

		//Create the room as a dynamic room
		$this->arrChatRooms[$intRoomID] = array(
			'id' => $intRoomID,
			'name' => $strRoomName,
			'key' => '',
			'users' => array(),
			'private' => false,
			'private_group' => false,
			'fixed' => $blFixedRoom
		);

		$this->objParent->serverLog(sprintf("Creating room: %d",$intRoomID));

		/** Store the room in the database so that it can be restored if the server goes down */
		$this->storeRoom($intRoomID);

		return $intRoomID;
	}

	function copyRoom($intRoomID,$strRoomName = 'New Room'){

		$this->intDynamicRoomCount++;

		$this->arrChatRooms[$this->intDynamicRoomCount] = $this->arrChatRooms[$intRoomID];
		$this->arrChatRooms[$this->intDynamicRoomCount]['fixed'] = false;
		$this->arrChatRooms[$this->intDynamicRoomCount]['private'] = false;
		$this->arrChatRooms[$this->intDynamicRoomCount]['private_group'] = true;
		$this->arrChatRooms[$this->intDynamicRoomCount]['name'] = $strRoomName;

		return $this->intDynamicRoomCount;
	}

	function updateRoom($intRoomID){

		/** Set the room to private only when their are 2 users in the room and "fixed = false" */
		$this->arrChatRooms[$intRoomID]['private'] = ($this->arrChatRooms[$intRoomID]['private_group'] == false && $this->arrChatRooms[$intRoomID]['fixed'] == false && count($this->arrChatRooms[$intRoomID]['users']) == 2) ? true : $this->arrChatRooms[$intRoomID]['private'];

		//Add a room key for private chat, 2 user rooms only
		ksort($this->arrChatRooms[$intRoomID]['users']);
		$strRoomKey = sha1(implode('-',$this->arrChatRooms[$intRoomID]['users']));
		$this->arrChatRooms[$intRoomID]['key'] = ($this->arrChatRooms[$intRoomID]['fixed'] == false && count($this->arrChatRooms[$intRoomID]['users']) == 2) ? $strRoomKey : '';

		/** Store the room in the database so that it can be restored if the server goes down */
		$this->storeRoom($intRoomID);

	}

	function removeRoom($intRoomID){

		if($this->deadRoom($intRoomID) && $this->arrChatRooms[$intRoomID]['fixed'] == false){

			//Drop the room from the database
			$this->dropRoom($intRoomID);

			//Remove the history from private chat rooms
			if($this->arrChatRooms[$intRoomID]['private_group'] == true){
				$this->dropHistory($intRoomID);
			}

			//Remove the room from the server cache
			unset($this->arrChatRooms[$intRoomID]);
		}
	}

	function addUser($intRoomID,$intUserID){

		if(!array_key_exists($intUserID,$this->arrChatRooms[$intRoomID]['users'])){

			if($this->arrChatRooms[$intRoomID]['fixed'] == false && count($this->arrChatRooms[$intRoomID]['users']) == 2){

				//Send a system message to let the user know a new room has been created
				$this->chatSystemMessage($intRoomID,"New group chat created.");

				//Take a copy of the current room (spawn) and then add user
				$intRoomID = $this->copyRoom($intRoomID,"Group Chat");
				$this->arrChatRooms[$intRoomID]['users'][$intUserID] = $intUserID;

				/** Send create command to all users in the new room */
				$this->pushRoomOpen($intRoomID);
			}else{

				//Standard add user to room
				$this->arrChatRooms[$intRoomID]['users'][$intUserID] = $intUserID;

				$arrUserData = $this->objParent->objSocketUser->getUser($intUserID);
				$this->chatSystemMessage($intRoomID,sprintf('%s has joined the room!',$arrUserData['name']));
			}
		}

		$this->updateRoom($intRoomID);
	}

	function removeUser($intRoomID,$intUserID){

		if(array_key_exists($intUserID,$this->arrChatRooms[$intRoomID]['users'])){
			unset($this->arrChatRooms[$intRoomID]['users'][$intUserID]);
		}

		$this->updateRoom($intRoomID);
	}

	function userInRoom($intRoomID,$intUserID){

		return (array_key_exists($intUserID,$this->arrChatRooms[$intRoomID]['users'])) ? true : false;
	}

	function deadRoom($intRoomID){

		return (is_array($this->arrChatRooms[$intRoomID]['users']) && count($this->arrChatRooms[$intRoomID]['users']) > 0) ? false : true;
	}

	function existingRoomByKey($strRoomKey){

		$intRoomID = null;

		//Check through all the matching rooms to find an existing one
		foreach($this->arrChatRooms as $arrEachRoom){
			if($arrEachRoom['key'] == $strRoomKey){
				$intRoomID = $arrEachRoom['id'];
				break;
			}
		}

		return $intRoomID;
	}

	/********* DATABASE CACHE FUNCTIONALITY *********/


	function importRooms(){

		$objDB = Twist::Database();

		//Check to see if the room exists before adding a record for it
		$strSQL = sprintf("SELECT `id`,`name`,`key`,`users`,`private`,`private_group`,`fixed`
							FROM `%s`.`chat_rooms`",
			DATABASE_NAME
		);

		if($objDB->query($strSQL) && $objDB->getNumberRows()){
			$arrAllRooms = $objDB->getFullArray();

			foreach($arrAllRooms as $arrEachRoom){

				$arrUsers = array();

				//Explode all the users and build up an indexed array of the user Data
				$arrUsersExplode = explode(',',$arrEachRoom['users']);
				foreach($arrUsersExplode as $intEachUserID){
					$arrUsers[$intEachUserID] = $intEachUserID;
				}

				/** @var $arrChatRooms SET ALL THE ROOM DATA BACK TO THE SERVER CACHE */
				$this->arrChatRooms[$arrEachRoom['id']] = array(
					'id' => $arrEachRoom['id'],
					'name' => $arrEachRoom['name'],
					'key' => $arrEachRoom['key'],
					'users' => $arrUsers,
					'private' => ($arrEachRoom['private']) ? true : false,
					'private_group' => ($arrEachRoom['private_group']) ? true : false,
					'fixed' => ($arrEachRoom['fixed']) ? true : false
				);

				/** @var $intFixedRoomCount UPDATE THE FIXED ROOM COUNTER */
				$this->intFixedRoomCount = ($arrEachRoom['fixed'] &&  $arrEachRoom['id'] > $this->intFixedRoomCount) ?  $arrEachRoom['id'] : $this->intFixedRoomCount;

				/** @var $intDynamicRoomCount UPDATE THE DYNAMIC ROOM COUNTER */
				$this->intDynamicRoomCount = (!$arrEachRoom['fixed'] &&  $arrEachRoom['id'] > $this->intDynamicRoomCount) ?  $arrEachRoom['id'] : $this->intDynamicRoomCount;
			}

			$this->objParent->serverHeader(sprintf("Rooms Imported : %d",count($this->arrChatRooms)));
		}
	}

	function storeRoom($intRoomID){

		$objDB = Twist::Database();

		//Check to see if the room exists before adding a record for it
		$strSQL = sprintf("SELECT `id`
							FROM `%s`.`chat_rooms`
							WHERE `id` = %d
							LIMIT 1",
			DATABASE_NAME,
			$objDB->escapeString($intRoomID)
		);

		if($objDB->query($strSQL) && $objDB->getNumberRows()){

			//Build the update query for the room
			$strSQL = sprintf("UPDATE `%s`.`chat_rooms`
								SET `name` = '%s',
									`key` = '%s',
									`users` = '%s',
									`private` = '%s',
									`private_group` = '%s',
									`fixed` = '%s'
								WHERE `id` = %d
								LIMIT 1",
				DATABASE_NAME,
				$objDB->escapeString($this->arrChatRooms[$intRoomID]['name']),
				$objDB->escapeString($this->arrChatRooms[$intRoomID]['key']),
				$objDB->escapeString(implode(',',$this->arrChatRooms[$intRoomID]['users'])),
				$objDB->escapeString(($this->arrChatRooms[$intRoomID]['private']) ? '1' : '0'),
				$objDB->escapeString(($this->arrChatRooms[$intRoomID]['private_group']) ? '1' : '0'),
				$objDB->escapeString(($this->arrChatRooms[$intRoomID]['fixed']) ? '1' : '0'),
				$objDB->escapeString($intRoomID)
			);
		}else{

			//Build the insert query for the room
			$strSQL = sprintf("INSERT INTO `%s`.`chat_rooms`
								SET `id` = %d,
									`name` = '%s',
									`key` = '%s',
									`users` = '%s',
									`private` = '%s',
									`private_group` = '%s',
									`fixed` = '%s'",
				DATABASE_NAME,
				$objDB->escapeString($intRoomID),
				$objDB->escapeString($this->arrChatRooms[$intRoomID]['name']),
				$objDB->escapeString($this->arrChatRooms[$intRoomID]['key']),
				$objDB->escapeString(implode(',',$this->arrChatRooms[$intRoomID]['users'])),
				$objDB->escapeString(($this->arrChatRooms[$intRoomID]['private']) ? '1' : '0'),
				$objDB->escapeString(($this->arrChatRooms[$intRoomID]['private_group']) ? '1' : '0'),
				$objDB->escapeString(($this->arrChatRooms[$intRoomID]['fixed']) ? '1' : '0')
			);
		}

		$objDB->query($strSQL);
	}

	function dropRoom($intRoomID){

		$objDB = Twist::Database();

		$strSQL = sprintf("DELETE FROM `%s`.`chat_rooms`
					WHERE `id` = %d",
			DATABASE_NAME,
			$objDB->escapeString($intRoomID)
		);

		$objDB->query($strSQL);
	}

	function storeHistory($intRoomID,$intUserID,$strMessage){

		$objDB = Twist::Database();

		if($this->arrChatRooms[$intRoomID]['private'] == true){

			//Check through the user IDs until we find the receipt
			foreach($this->arrChatRooms[$intRoomID]['users'] as $intReceiptID){
				if($intReceiptID != $intUserID){
					break;
				}
			}

			$strSQL = sprintf("INSERT INTO `%s`.`chat_history`
								SET `room_id` = NULL,
									`user_id` = %d,
									`receipt_id` = %d,
									`message` = '%s',
									`created` = NOW()",
				DATABASE_NAME,
				$objDB->escapeString($intUserID),
				$objDB->escapeString($intReceiptID),
				$objDB->escapeString($strMessage)
			);

		}else{

			$strSQL = sprintf("INSERT INTO `%s`.`chat_history`
								SET `room_id` = %d,
									`user_id` = %d,
									`receipt_id` = NULL,
									`message` = '%s',
									`created` = NOW()",
				DATABASE_NAME,
				$objDB->escapeString($intRoomID),
				$objDB->escapeString($intUserID),
				$objDB->escapeString($strMessage)
			);
		}

		$objDB->query($strSQL);
	}

	function importHistory($intRoomID){

		$arrOut = array();
		$objDB = Twist::Database();

		$strSQL = sprintf("SELECT `history`.`message`, `history`.`user_id`, CONCAT(`users`.`firstname`,' ',`users`.`surname`) AS `user_name`, `history`.`created`
							FROM `%s`.`chat_history` AS `history`
							JOIN `%s`.`users` AS `users`
							ON `users`.`id` = `history`.`user_id`
							WHERE `history`.`room_id` = %d
							AND `history`.`created` > '%s'",
			DATABASE_NAME,
			DATABASE_NAME,
			$objDB->escapeString($intRoomID),
			$objDB->escapeString(date('Y-m-d\TH:i:s\Z',strtotime('-1 Hour')))
		);

		if($objDB->query($strSQL) && $objDB->getNumberRows()){
			$arrOut = $objDB->getFullArray();
		}

		return $arrOut;
	}

	function importHistoryPrivate($intUserID,$intReceiptID){

		$arrOut = array();
		$objDB = Twist::Database();

		$strSQL = sprintf("SELECT `history`.`message`, `history`.`user_id`, CONCAT(`users`.`firstname`,' ',`users`.`surname`) AS `user_name`, `history`.`created`
							FROM `%s`.`chat_history` AS `history`
							JOIN `%s`.`users` AS `users`
							ON `users`.`id` = `history`.`user_id`
							WHERE ((`history`.`user_id` = %d AND `history`.`receipt_id` = %d) OR (`history`.`user_id` = %d AND `history`.`receipt_id` = %d))
							AND `history`.`created` > '%s'",
			DATABASE_NAME,
			DATABASE_NAME,
			$objDB->escapeString($intUserID),
			$objDB->escapeString($intReceiptID),
			$objDB->escapeString($intReceiptID),
			$objDB->escapeString($intUserID),
			$objDB->escapeString(date('Y-m-d\TH:i:s\Z',strtotime('-1 Hour')))
		);

		if($objDB->query($strSQL) && $objDB->getNumberRows()){
			$arrOut = $objDB->getFullArray();
		}

		return $arrOut;
	}

	function dropHistory($intRoomID){

		$objDB = Twist::Database();

		$strSQL = sprintf("DELETE FROM `%s`.`chat_history`
					WHERE `room_id` = %d",
			DATABASE_NAME,
			$objDB->escapeString($intRoomID)
		);

		$objDB->query($strSQL);
	}


	/********* PROCESS CHATROOM REQUEST *************/

	function pushRoomOpenUser($intUserID){

		$arrResponse['type'] = 'chat';
		$arrResponse['action'] = 'create';

		foreach($this->arrChatRooms as $intRoomID => $arrRoomData){
			if($this->userInRoom($intRoomID,$intUserID)){

				//Set the room ID in the push data
				$arrResponse['id'] = $intRoomID;
				$arrResponse['data'] = array();

				//All History for the room to be sent back
				if($arrRoomData['private']){

					//Get the other user form the room
					foreach($arrRoomData['users'] as $intOtherUser){
						if($intUserID != $intOtherUser){
							break;
						}
					}

					$arrHistory = $this->importHistoryPrivate($intUserID,$intOtherUser);
				}else{
					$arrHistory = $this->importHistory($intRoomID);
				}

				//If their is history then process the names and the emoticon filter... and by default use the profanity filter
				if(is_array($arrHistory) && count($arrHistory) > 0){
					foreach($arrHistory as $arrEachHistory){

						$arrEachHistory['user_name'] = ($arrEachHistory['user_id'] == $intUserID) ? 'You' : $arrEachHistory['user_name'];
						$arrEachHistory['you'] = ($arrEachHistory['user_id'] == $intUserID) ? '1' : '0';

						/** @todo Add profanity filter here - fliping bumhole (fucking asshole) */
						$arrEachHistory['message'] = $this->emoticonFilter($arrEachHistory['message']);
						$arrEachHistory['message'] = $this->profanityFilter($arrEachHistory['message']);
						$arrEachHistory['message'] = str_replace("\n","<br />",$arrEachHistory['message']);
						
						$arrResponse['data'][] = $arrEachHistory;
					}
				}

				//Send a response back to the creating user
				$this->objParent->sendJSON($intUserID, $arrResponse);

				$this->objParent->adminRoomStats();

				$this->sendActiveUsers($intRoomID,$intUserID,true);
				$this->sendInactiveUsers($intRoomID,$intUserID,true);
			}
		}
	}

	function pushRoomOpen($intRoomID){

		$arrResponse['type'] = 'chat';
		$arrResponse['action'] = 'create';
		$arrResponse['id'] = $intRoomID;

		foreach($this->arrChatRooms[$intRoomID]['users'] as $intChatUserID){

			//Send a response back to the creating user
			$this->objParent->sendJSON($intChatUserID, $arrResponse);
		}

		$this->sendActiveUsers($intRoomID,$intChatUserID,true);
		$this->sendInactiveUsers($intRoomID,$intChatUserID,true);
	}

	function processRequest($arrData,$intUserID){

		$strAction = $arrData['action'];
		$intRoomID = $arrData['id'];
		$mxdData = $arrData['data'];

		switch($strAction){

			case'create':

				//Use the smallest ID first to make sure ky is always the same
				$strRoomKey = sha1(sprintf('%d-%d',
					($intUserID < $mxdData) ? $intUserID : $mxdData,
					($intUserID < $mxdData) ? $mxdData : $intUserID
				));

				$intExistingRoomID = $this->existingRoomByKey($strRoomKey);

				//Only create the room if it is not already existing
				if(is_null($intExistingRoomID)){

					$intRoomID = $this->createChat('Private Chat');

					//Add both the users to the new chat room
					$this->addUser($intRoomID,$intUserID);
					$this->addUser($intRoomID,$mxdData);

					$arrResponse['type'] = 'chat';
					$arrResponse['action'] = 'create';
					$arrResponse['id'] = $intRoomID;

					//Send a response back to the creating user
					$this->objParent->sendJSON($intUserID, $arrResponse);

					//Now send to the added user to create Chat window
					$this->objParent->sendJSON($mxdData, $arrResponse);

					$this->objParent->adminRoomStats();

					$this->sendActiveUsers($intRoomID,$intUserID,true);
					$this->sendInactiveUsers($intRoomID,$intUserID,true);
				}else{
					//Users are already in a room, push room open
					$this->pushRoomOpen($intExistingRoomID);
				}

				break;

			case'add':

				if(array_key_exists($intRoomID,$this->arrChatRooms)){

					$this->addUser($intRoomID, $mxdData);

					$arrResponse['type'] = 'chat';
					$arrResponse['action'] = 'create';
					$arrResponse['id'] = $intRoomID;

					//Now send to the added user to create Chat window
					$this->objParent->sendJSON($mxdData, $arrResponse);

					$this->sendActiveUsers($intRoomID, $intUserID, true);
					$this->sendInactiveUsers($intRoomID, $intUserID, true);

					$this->objParent->adminRoomStats();
				}
				break;
			
			case'remove':

				if(array_key_exists($intRoomID,$this->arrChatRooms)){

					$this->removeUser($intRoomID, $intUserID);

					$arrResponse['type'] = 'chat';
					$arrResponse['action'] = 'remove';
					$arrResponse['id'] = $intRoomID;

					//Send the remove request ot the client the requested it
					$this->objParent->sendJSON($intUserID, $arrResponse);

					//Remove room of the room is dead
					if($this->deadRoom($intRoomID)){
						$this->removeRoom($intRoomID);
					}else{

						$arrUserData = $this->objParent->objSocketUser->getUser($intUserID);
						$this->chatSystemMessage($intRoomID,sprintf('%s has left the room',$arrUserData['name']));

						$this->sendActiveUsers($intRoomID, $intUserID, true);
						$this->sendInactiveUsers($intRoomID, $intUserID, true);
					}

					$this->objParent->adminRoomStats();
				}
				break;

			case'message':

				if(array_key_exists($intRoomID,$this->arrChatRooms)){

					$arrUserData = $this->objParent->objSocketUser->getUser($intUserID);

					//Store the chat history for this message
					$this->storeHistory($intRoomID,$intUserID,$mxdData);

					foreach($this->arrChatRooms[$intRoomID]['users'] as $intChatUserID){

						$arrResponse['type'] = 'chat';
						$arrResponse['action'] = 'message';
						$arrResponse['id'] = $intRoomID;

						$arrResponse['user_id'] = $intUserID;
						$arrResponse['user_name'] = ($intChatUserID == $intUserID) ? 'You' : $arrUserData['name'];
						$arrResponse['created'] = date('Y-m-d\TH:i:s\Z');
						$arrResponse['you'] = ($intChatUserID == $intUserID) ? '1' : '0';

						/** @todo Add profanity filter here - fliping bumhole (fucking asshole) */
						$arrResponse['message'] = $this->emoticonFilter($mxdData);
						$arrResponse['message'] = $this->profanityFilter($arrResponse['message']);
						$arrResponse['message'] = str_replace("\n","<br />",$arrResponse['message']);

						$this->objParent->sendJSON($intChatUserID, $arrResponse);

						//Send new message notification via growl
						if($intChatUserID != $intUserID && $arrUserData['settings']['growl_new_message'] == true){

							$arrData = array(
								'action' => 'growl',
								'id' => $intChatUserID,
								'data' => sprintf('You have received a chat new message from %s.',$arrResponse['user_name'])
							);
							$this->objParent->objSocketNotification->processRequest($arrData,$intChatUserID);
						}

					}
				}

				break;

			case'active':

				$this->sendActiveUsers($intRoomID, $intUserID);
				break;

			case'inactive':

				$this->sendInactiveUsers($intRoomID, $intUserID);
				break;
		}
	}

	function sendActiveUsers($intRoomID, $intUserID, $blSentToAll = false){

		$arrResponse['type'] = 'chat';
		$arrResponse['action'] = 'active';
		$arrResponse['id'] = $intRoomID;

		//Get a list of all the users that are in the room
		$arrResponse['users'] = array();

		foreach($this->arrChatRooms[$intRoomID]['users'] as $intChatUserID){

			//Get the users data and add to the response data
			$arrUserData = $this->objParent->objSocketUser->getUser($intChatUserID);
			$arrResponse['users'][] = array('user_id' => $arrUserData['id'],'user_name' => $arrUserData['name']);
		}

		if($blSentToAll){

			//Now send to all the users in the given chat room
			foreach($this->arrChatRooms[$intRoomID]['users'] as $intChatUserID){
				$this->objParent->sendJSON($intChatUserID, $arrResponse);
			}
		}else{
			$this->objParent->sendJSON($intUserID, $arrResponse);
		}
	}

	function sendInactiveUsers($intRoomID, $intUserID, $blSentToAll = false){

		$arrResponse['type'] = 'chat';
		$arrResponse['action'] = 'inactive';
		$arrResponse['id'] = $intRoomID;

		//Get a list of all the users that are not in the room
		$arrResponse['users'] = array();

		foreach($this->objParent->objSocketUser->arrUsers as $arrEachUser){

			if(!in_array($arrEachUser['id'],$this->arrChatRooms[$intRoomID]['users'])){

				//Get the users data and add to the response data
				$arrUserData = $this->objParent->objSocketUser->getUser($arrEachUser['id']);
				$arrResponse['users'][] = array('user_id' => $arrUserData['id'],'user_name' => $arrUserData['name']);
			}
		}

		if($blSentToAll){

			//Now send to all the users in the given chat room
			foreach($this->arrChatRooms[$intRoomID]['users'] as $intChatUserID){
				$this->objParent->sendJSON($intChatUserID, $arrResponse);
			}
		}else{
			$this->objParent->sendJSON($intUserID, $arrResponse);
		}
	}

	function profanityFilter($strMessage){

		$arrClientFilter = array(
			'fuck' => 'flip',
			'shit' => 'poo',
			'ass' => 'bum',
			'cock' => 'penis',
			'twat' => 'idiot',
			'piss' => 'urinate',
			'cunt' => 'vagina',
			'tosser' => 'masturbater',
			'tossoff' => 'masturbate',
			'wanker' => 'masturbater',
			'wank' => 'masturbate',
			'bollocks' => 'balls',
			'bastard' => 'idiot',
			'retard' => 'special',
		);

		foreach($arrClientFilter as $strKey => $strReplacment){
			$strMessage = preg_replace(sprintf("#(%s)#i",$strKey),$strReplacment,$strMessage);
		}

		return $strMessage;
	}

	function emoticonFilter($strMessage){

		$arrClientFilter = array(
			'\(alien\)' => '/images/emoticons/16/alien.png',
			'8\)' => '/images/emoticons/16/alien.png',

			'\(angry\)' => '/images/emoticons/16/angry.png',
			'\>\:\(' => '/images/emoticons/16/angry.png',
			
			'\(sad\)' => '/images/emoticons/16/sad.png',
			'\:\(' => '/images/emoticons/16/sad.png',

			'\(scared\)' => '/images/emoticons/16/scared.png',
			'\:\|' => '/images/emoticons/16/scared.png',

			'\(smile\)' => '/images/emoticons/16/smile.png',
			'\:D' => '/images/emoticons/16/smile.png',

			'\(suprised\)' => '/images/emoticons/16/suprised.png',
			'\:0' => '/images/emoticons/16/suprised.png',

			'\(wink\)' => '/images/emoticons/16/wink.png',
			'\;\)' => '/images/emoticons/16/wink.png',
		);

		foreach($arrClientFilter as $strKey => $strReplacment){
			$strMessage = preg_replace(sprintf("#(%s)#i",$strKey),sprintf('<img src="%s" />',$strReplacment),$strMessage);
		}

		return $strMessage;
	}

	function chatSystemMessage($intRoomID, $strMessage){

		$arrResponse['type'] = 'chat';
		$arrResponse['action'] = 'system';
		$arrResponse['id'] = $intRoomID;
		$arrResponse['message'] = $strMessage;

		foreach($this->arrChatRooms[$intRoomID]['users'] as $intUserID){
			$this->objParent->sendJSON($intUserID, $arrResponse);
		}
	}


}

?>