<?php

	Twist::framework()->package()->uninstall();

	//Optional Line: Add this line if you are uninstalling database tables
	Twist::framework()->package()->importSQL(sprintf('%s/Install/uninstall-websockets.sql',dirname(__FILE__)));

	//Optional Line: Add this line if you are removing all package settings
	Twist::framework()->package()->removeSettings();

	/**
	 * Remove all Lavish Shopping Hooks for the system
	 */
	\Twist::framework()->hooks()->cancel('TWIST_MANAGER_ROUTE','websockets-manager',true);