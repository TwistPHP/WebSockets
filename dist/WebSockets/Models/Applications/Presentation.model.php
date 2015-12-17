<?php

class SocketPresentation{

	var $strSocketListenCommand = 'presentation';
	var $objParent = null;
	var $objNotifications = null;

	var $strCurrentSlide = '';
	var $strCurrentPointer = '';

	function __construct($resParentObject){
		$this->objParent = $resParentObject;
	}

	function processRequest($arrData,$intUserID){

		$strAction = $arrData['action'];
		$mxdData = (array_key_exists('data',$arrData)) ? $arrData['data'] : array();

		$arrResponse = array(
			'instance' => (array_key_exists('instance',$arrData)) ? $arrData['instance'] : null,
			'system' => $this->strSocketListenCommand,
			'action' => $arrData['action'],
			'message' => '',
			'data' => array()
		);

		switch($strAction){

			case'catchup':
				$arrResponse['data'] = array('uri' => $this->strCurrentSlide);
				$this->objParent->sendUser($intUserID, $arrResponse);
				break;

			case'goto':
				$this->strCurrentSlide = $mxdData['uri'];

				$arrResponse['data'] = $mxdData;
				foreach($this->objParent->objSocketUsers->arrUsers as $arrEachUser){
					if($arrEachUser['id'] != $intUserID){
						$this->objParent->sendUser($arrEachUser['id'], $arrResponse);
					}
				}

				break;

			default:
				$arrResponse['data'] = $mxdData;
				foreach($this->objParent->objSocketUsers->arrUsers as $arrEachUser){
					if($arrEachUser['id'] != $intUserID){
						$this->objParent->sendUser($arrEachUser['id'], $arrResponse);
					}
				}
				break;


		}
	}
}