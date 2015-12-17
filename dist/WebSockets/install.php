<?php

	Twist::framework()->package()->install();

	//Optional Line: Add this line if you are adding database tables
	Twist::framework()->package()->importSQL(sprintf('%s/Install/websockets.sql',dirname(__FILE__)));

	//Optional Line: Add this line if you are adding framework settings
	Twist::framework()->package()->importSettings(sprintf('%s/Install/settings.json',dirname(__FILE__)));

	/**
	 * Install all Lavish Shopping Hooks for the system
	 */
	\Twist::framework()->hooks()->register('TWIST_MANAGER_ROUTE','websockets-manager',dirname(__FILE__).'/Hooks/manager.php',true);