# TwistPHP Package - WebSockets
Bring you website or project to life with real time bespoke interactivity, for use within the [TwistPHP MVC framework](https://twistphp.com).

## Getting Started
To get started using the TwistPHP WebSockets package it will need to be installed into your copy of the TwistPHP framework. To do this you can either search for "WebSockets" on the packages page of your framework manager and click install or download and manually install the package.

* Update the **WS_HOST** and **WS_PORT** settings in your manager or database
    * **WS_HOST** - IP address or domain name that the socket server will listen on
    * **WS_PORT** - Port that the socket server will listen on
* Start the socket server, you will need to setup a cron/scheduled task to manage the server. The task should run once an minute, if the server is already running nothing more will be done.
    * `* * * * * php -q /packages/WebSockets/cronjobs/server.php`
* Once started you can manage and view the stats of your socket server in the manager, a new item "WebSockets" should have appeared in the menu.

## Setting up a Socket Client
To setup a client connection to the socket server we have provided a JS library which can be included with the View Tag {resource:websockets,version=min}. Below is an example of how to initiate the JS library and connect to the socket server.
```html
{resource:websockets,version=min}

<script type="text/javascript">
    var strHost = '{setting:WS_HOST}';
    var intPort = {setting:WS_PORT};
    var mxdUserSessionKey = 'guest';
    
    var instanceID = null;
    var objSocket;
    objSocket = TwistWebSocket( strHost,intPort,{
        credentials: {
            uid: mxdUserSessionKey
        },
        shareactivity: true,
        onmessage: function( objResponse ){
            alert(objResponse.message);
        },
        onlogin: function( objResponse ){
            alert(objResponse.message);
        },
        onrestart: function( objResponse ){
            alert( 'Notifications system will restart in ' + objResponse.data.time + ' seconds' );
        }
    });
</script>
```

## Manual Package Installation
To manually install the package into your copy of TwistPHP follow the below steps:

* Download a copy of the WebSockets package and extract its contents
* Upload the `WebSockets` folder located in `\dist` to your frameworks `\packages` folder
* Load the framework manager, go the packages page and click "Install" on the WebSockets package

For more information and guides on the installation of packages please visit https://twistphp.com/docs
