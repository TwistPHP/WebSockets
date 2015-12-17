<?php

	\Twist::define('WEBSOCKETS_VIEWS',dirname(__FILE__).'/../Views');

	$this -> controller( '/websockets/%', 'Packages\WebSockets\Controllers\Manager' );