<?php

class SocketDebug{

    var $strSocketListenCommand = 'debug';
	var $objParent = null;
	var $objNotifications = null;

	function __construct($resParentObject){
		$this->objParent = $resParentObject;
	}

	function processRequest($arrData,$intUserID){

		$strAction = $arrData['action'];
		$mxdData = (array_key_exists('data',$arrData)) ? $arrData['data'] : array();

		$mxdData['from'] = sprintf('%s [%s]',$this->objParent->objSocketUsers->arrUsers[$intUserID]['name'],$intUserID);

		$arrResponse = array(
            'instance' => (array_key_exists('instance',$arrData)) ? $arrData['instance'] : null,
            'system' => $this->strSocketListenCommand,
            'action' => $arrData['action'],
            'message' => '',
            'data' => array()
        );

		switch($strAction){

			//Echo message back to you
			case'echo':
				$arrResponse['data'] = $mxdData;
				$this->objParent->sendUser($intUserID, $arrResponse);
			break;

			//Echo message to all connected users
			case'echoEveryone':
				$arrResponse['data'] = $mxdData;
				foreach($this->objParent->objSocketUsers->arrUsers as $arrEachUser){
					$this->objParent->sendUser($arrEachUser['id'], $arrResponse);
				}
			break;

			//Echo message to all connected users except yourself
			case'echoEveryoneElse':
				$arrResponse['data'] = $mxdData;
				foreach($this->objParent->objSocketUsers->arrUsers as $arrEachUser){
					if($arrEachUser['id'] != $intUserID){
						$this->objParent->sendUser($arrEachUser['id'], $arrResponse);
					}
				}
			break;

			case'setInterval':
				$this->objParent->registerCron('10s','debug','echo',$mxdData,$intUserID);
				break;

			case'cancelInterval':
				$this->objParent->cancelCron('debug','echo',$intUserID);
				break;

			//Ping/Pong the server will respond
			case'ping':
				$arrResponse['message'] = 'pong';
				$this->objParent->sendUser($intUserID, $arrResponse);
			break;
		}
	}
}