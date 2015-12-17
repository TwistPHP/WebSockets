<?php

	namespace Packages\WebSockets\Controllers;

	/**
	 * Class Manager Controller
	 * @package Packages\WebSockets\Controllers
	 */
	class Manager extends \Twist\Core\Controllers\Base{

		public function _index(){

			$arrConnectionData = array(
				'ws_host' => (array_key_exists('ws_host',$_GET)) ? $_GET['ws_host'] : \Twist::framework()->setting('WS_SERVER_HOST'),
				'ws_port' => (array_key_exists('ws_port',$_GET)) ? $_GET['ws_port'] : \Twist::framework()->setting('WS_SERVER_PORT'),
				'session_key' => \Twist::Session()->data('user-session_key')
			);

			return $this->_view('manager/overview.tpl',$arrConnectionData);
		}

		/**
		 * Override the default view function to append the web sockets view path when required
		 * We do this rather than reset the view path as it has to work alongside the Manager which already has a view path set
		 * @param $dirView
		 * @param null $arrViewTags
		 * @param bool $blRemoveUnusedTags
		 * @return string
		 */
		protected function _view($dirView,$arrViewTags = null,$blRemoveUnusedTags = false){

			if(!file_exists($dirView) && substr($dirView,0,1) != '/' && substr($dirView,0,2) != './' && substr($dirView,0,3) != '../'){
				$dirView = WEBSOCKETS_VIEWS.'/'.$dirView;
			}

			return parent::_view($dirView,$arrViewTags,$blRemoveUnusedTags);
		}
	}