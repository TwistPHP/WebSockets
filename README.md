# TwistPHP Package - WebSockets
Bring you website or project to life with real time bespoke interactivity.

## Getting Started
To get started using the WebSockets module you can follow the a couple of simple steps to create a basic chat room. We will be using the 'template' and 'resource' module in this example.

* First create a file called 'socketServer.php', this will be your server
* Place the below example code in your 'socketServer.php' file:
    &lt;?php

	//Full path to the twist framework is required for Shell and Command line running of this script
	require_once '/full/path/to/my/site/twist/twist.php';

	//Never use level 0 in a real situation, ensure the level is set to your admin users level
	Twist::framework() -&gt; WebSockets -&gt; setAdminLevel(0);
	Twist::framework() -&gt; WebSockets -&gt; server(Twist::settings() -&gt; get('SITE_HOST'),1085);
* Now create two template files 'socket-user.tpl' and 'socket-admin.tpl' in your templates folder
* Place the below example code in your 'socket-user.tpl' file, this code will allow the user to send and receive interactive messages
* Place the below example code in your 'socket-admin.tpl' file, this code will allow the admin to monitor the servers stats
* Now create a PHP file 'sockets.php' in your website root to serve the 2 templates
* Place the below example code in your 'sockets.php' file
* Start up the socket server, to do this you will either need to temporarily run the server by calling the below command in an SSH Shell on your server
    php -q /full/path/to/my/site/socketServer.php
    Alternatively you can set the socket server up to run and restart automatically by using a cronJob. Setup a cronjob with the following options:
    * * * * * php -q /full/path/to/my/site/socketServer.php
* Now go to 'sockets.php' in your web-browser and enjoy
