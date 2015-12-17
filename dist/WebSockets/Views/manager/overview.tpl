{resource:websockets,min}
<script type="text/javascript">
    var strHost = '{data:ws_host}';
    var intPort = {data:ws_port};
    var mxdUserSessionKey = '{data:session_key}';
</script>
<div class="grid-100 tablet-grid-100 mobile-grid-100">

	<style type="text/css">
		.userBox{
			display:block;
			margin:6px;
			padding:2px;
			border:1px solid #666;
			border-radius: 5px;
			background-color: #eaeaea;
		}

		.active{
			background-color: #bdeccc !important;
		}

		.inactive{
			background-color: #ecb6bd !important;
		}

		.focus{
			margin:5px;
			border:2px solid #666 !important;
		}

		.blur{
			margin:6px;
			border:1px solid #666 !important;
		}
	</style>

    <h2 class="no-top-padding">Web Sockets Manager</h2>
	<a id="restartServer" href="#restart" class="button fat blue float-right"><i class="fa fa-refresh"></i> Restart</a>
	<a id="monitorServer"  href="#monitor" class="button fat blue float-right">Monitor</a>

	<h3>Debug Tools</h3>
	<p><a id="debugEcho" href="#debugEcho" onclick="objSocket.send( 'debug','echo', {test:'test'} );">Echo</a> |
		<a id="debugEchoEveryone"  href="#debugEchoEveryone" onclick="objSocket.send( 'debug','echoEveryone', {test:'test'} );">Echo Everyone</a> |
		<a id="debugEchoEveryoneElse"  href="#debugEchoEveryoneElse" onclick="objSocket.send( 'debug','echoEveryoneElse', {test:'test'} );">Echo Everyone Else</a> |
		<a id="debugSetInterval"  href="#mondebugSetIntervalitor" onclick="objSocket.send( 'debug','setInterval', {test:'test interval 10s'} );">Set Interval (10s)</a> |
		<a id="debugCancelInterval"  href="#debugCancelInterval" onclick="objSocket.send( 'debug','cancelInterval', {test:'test'} );">Cancel Interval</a>
	</p>

	<h3>Monitor</h3>
	<pre id="stats" style="padding:10px; border:1px solid #666;"></pre>

	<h3>Responses</h3>
	<pre id="responses" style="width:96%; height:580px; overflow:auto; padding:10px; border:1px solid #666;"></pre>

	<script>
		var instanceID = null;
		var objSocket;

		objSocket = TwistWebSocket( strHost,intPort,
				{
					actions: {
						notifications: {
							push: function( objResponse ) {
								//notify();
								shdw.log( objResponse.message );
							}
						}
					},
					credentials: {
						uid: mxdUserSessionKey
					},
					debug: true,
					shareactivity: true,
					ondebug: function( objResponse ){
						var strUsers = '';
						var resResponseData = objResponse.data;

						$.each( resResponseData.users,
								function( intIndex, arrUser ) {

									var viewStatus = '-';
									var activeStatus = '-';

									var strConnection = '';
									$.each( arrUser.connections, function(intIndex, arrConnectionUser){

										switch(arrConnectionUser.viewStatus.status){
											case'focus':
												viewStatus = 'Focused';
												break;
											case'blur':
												viewStatus = 'Blured';
												break;
										}
										switch(arrConnectionUser.activeStatus.status){
											case'active':
												arrConnectionUser.activeStatus.updated = 'now';
												activeStatus = 'Active';
												break;
											case'inactive':
												activeStatus = 'Inactive';
												break;
										}

										//strConnection += '<span>Connection #'+intIndex+': '+viewStatus+'/'+activeStatus+'</span><br><span>URL: '+arrConnectionUser.currentURI+'</span><br><span>IP: '+arrConnectionUser.ip+':'+arrConnectionUser.port+'</span><br>';
										strConnection += '<span>#'+intIndex+': ['+arrConnectionUser.ip+':'+arrConnectionUser.port+'] '+arrConnectionUser.currentURI+' - '+viewStatus+'/'+activeStatus+'<br>';
									});

									strUsers +='<div class="userBox"><strong>'+arrUser.name+' ['+arrUser.connections.length+' Connection(s)]</strong><br>'+strConnection+'</div>';
								}
						);

						$( '#stats' ).html( '<strong>SERVER</strong>' + "\n" +
						'Uptime:        ' + resResponseData.server.uptime.format + "\n" +
						'Requests:      ' + resResponseData.server.request_in.format + ' in/' + resResponseData.server.request_out.format + ' out' + "\n" +
						'Traffic:       ' + resResponseData.server.traffic_in.format + ' in/' + resResponseData.server.traffic_out.format + ' out' + "\n" +
						'Bandwidth In:  ' + resResponseData.server.current_bandwidth_in.format + "\n" +
						'Bandwidth Out: ' + resResponseData.server.current_bandwidth_out.format + "\n" +
						'Memory:        ' + resResponseData.server.mem_usage.format + "\n" +
						'Users:         ' + resResponseData.server.users.format + "\n" +
						'Connections:   ' + resResponseData.server.connections.format + "\n" +
						'Rooms:         ' + ((typeof resResponseData.server.rooms == 'undefined') ? 'Not Active\n\n' : resResponseData.server.rooms.format + "\n\n") +
						strUsers );
					},
					onmessage: function( objResponse ){

					},
					onlogin: function( objResponse ){
						objSocket.send( 'twist', 'debug' );
					},
					onrestart: function( objResponse ){
						alert( 'Notifications system will restart in ' + objResponse.data.time + ' seconds' );
					}
				}
		);

		$( '#restartServer' ).on( 'click',
				function( e ) {
					e.preventDefault();
					objSocket.send( 'twist','restart' );
				}
		),
				$( '#monitorServer' ).on( 'click',
						function( e ) {
							e.preventDefault();
							objSocket.send( 'twist','debug' );
						}
				);

		/**$( document ).mousemove(
		 function( e ) {
							objCursorPosition = {
									x: e.pageX,
									y: e.pageY
								};
							if( typeof objLastCursorPosition === 'undefined' ) {
								objLastCursorPosition = objCursorPosition;
							}
						}
		 );

		 setInterval(
		 function() {
							if( typeof objCursorPosition !== 'undefined'
									&& ( objCursorPosition.x !== objLastCursorPosition.x
										|| objCursorPosition.y !== objLastCursorPosition.y ) ) {
								objLastCursorPosition = objCursorPosition;
								objSocket.send( 'presentation', 'cursor', objCursorPosition );
							}
						}, 250
		 );**/
		/**}
		 );**/
	</script>
</div>