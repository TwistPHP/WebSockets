/**
 * ================================================================================
 * Twist WebSockets
 * --------------------------------------------------------------------------------
 * Author:      Andrew Hosgood
 * Version:     5.0.0
 * Date:		13/05/2014
 * ================================================================================
 */

(
	function( window, document ) {
		try {
			var isBlank = function( mxdValue ) {
					return mxdValue.replace( /[\s\t\r\n]*/g, '' ) == '';
				},
			isInt = function( mxdValue ) {
					return ( parseFloat( mxdValue ) == parseInt( mxdValue ) ) && !isNaN( mxdValue );
				},
			isNull = function( mxdValue ) {
					return typeof( mxdValue ) === 'null' || mxdValue === null;
				},
			isSet = function( mxdValue ) {
					return mxdValue !== null && mxdValue !== undefined && typeof mxdValue !== 'null' && typeof mxdValue !== 'undefined' && mxdValue !== 'undefined';
				},
			randomString = function( intStringLength, mxdExtendedChars ) {
					intStringLength = isSet( intStringLength ) && isInt( intStringLength ) ? intStringLength : 16;
					var strChars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz',
							strRandomString = '';
					if( typeof mxdExtendedChars === 'string' ) {
						strChars = mxdExtendedChars;
					} else if( mxdExtendedChars === true ) {
						strChars += '!@£$%^&*()_-=+[]{};:|<>?/';
					}
					for( var intChar = 0; intChar < intStringLength; intChar++ ) {
						var intRand = Math.floor( Math.random() * strChars.length );
						strRandomString += strChars.substring( intRand, intRand + 1 );
					}
					return( strRandomString );
				},
			objTwistWebSocket = function( strSocketLocation, a, b ) {
					var objTwistWS = this;

					objTwistWS.log = function() {
							if( objTwistWS.objOptions.debug
									&& arguments.length
									&& window.console
									&& window.console.log ) {
								for( var intArguement in arguments ) {
									console.log( arguments[intArguement] );
								}
							}
						},
					objTwistWS.port = 1085,
					objTwistWS.status = 0;

					if( isBlank( strSocketLocation ) ) {
						objTwistWS.log( 'WebSocket location not defined' );
						return false;
					} else {
						objTwistWS.socketDomain = strSocketLocation;
					}

					var objUserOptions = {};

					if( typeof( a ) === 'number'
							|| ( typeof( a ) === 'string'
								&& a !== ''
								&& !isNaN( a ) ) ) {
						objTwistWS.port = a;
						if( typeof b === 'object' ) {
							objUserOptions = b;
						}
					} else {
						if( typeof a === 'object' ) {
							objUserOptions = a;
						}
					}

					objTwistWS.objOptions = {
							actions: {},
							activitytimeout: 10000,
							autoreconnects: 5,
							autoreconnectonclose: true,
							autoreconnectonerror: false,
							autoreconnectonoffline: true,
							autoreconnecttime: 1000,
							credentials: {
									uid: 'guest'
								},
							debug: false,
							onchangestatus: function() {},
							onclose: function() {},
							onconnect: function() {},
							ondebug: function() {},
							onerror: function() {},
							onlogin: function() {},
							onfirstlogin: function() {},
							onmessage: function() {},
							onoffline: function() {},
							ononline: function() {},
							onrestart: function() {},
							protocol: 'echo-protocol',
							queuemessages: true,
							quiet: true,
							shareactivity: false
						},
					objTwistWS.arrQueuedMessages = [],
					objTwistWS.blUserActive = false,
					objTwistWS.connect = function() {
							objTwistWS.status = 4;
							objTwistWS.reconnects++;

							if( objTwistWS.reconnects < objTwistWS.objOptions.autoreconnects ) {
								try {
									if( typeof MozWebSocket != 'undefined' ) {
										objTwistWS.webSocket = new MozWebSocket( objTwistWS.strSocketUrl, objTwistWS.objOptions.protocol );
										objTwistWS.log( 'Opening:   ' + objTwistWS.strSocketUrl + ' (Mozilla WebSocket)' );
									} else if( window.WebSocket ) {
										objTwistWS.webSocket = new WebSocket( objTwistWS.strSocketUrl, objTwistWS.objOptions.protocol );
										objTwistWS.log( 'Opening:   ' + objTwistWS.strSocketUrl );
									} else {
										if( !objTwistWS.objOptions.quiet ) {
											alert( 'WebSockets are not supported on this browser' );
										}
										objTwistWS.log( 'WebSockets are not supported on this browser' );
										objTwistWS.status = 0;
										return false;
									}

								} catch( ex ) {
									objTwistWS.log( ex );
								}

								objTwistWS.webSocket.onopen = function() {
										objTwistWS.status = this.readyState;
										objTwistWS.log( 'Status:    Connected' );
										objTwistWS.objOptions.autoreconnecttime = objTwistWS.intAutoreconnectTime;

										try {
											objTwistWS.objOptions.onchangestatus( objTwistWS.webSocket.readyState );
											objTwistWS.objOptions.onconnect();
											objTwistWS.login();
										} catch( ex ) {
											objTwistWS.log( ex );
										}
									},
								objTwistWS.webSocket.onmessage = function( objResponse ) {
										objTwistWS.status = this.readyState;
										try {
											var objResponseData = objTwistWS.jsonDecode( objResponse.data );

											if( objResponseData.system === 'twist' ) {
												switch( objResponseData.action ) {
													case 'clientDetails':
														objTwistWS.send( 'twist', 'clientDetails',
															{
																screen: {
																		width: screen.width,
																		height: screen.height
																	},
																window: {
																		width: $( window ).width(),
																		height: $( window ).height()
																	},
																document: {
																		width: $( document ).width(),
																		height: $( document ).height()
																	}
															}
														);
														break;

													case 'debug':
														objTwistWS.log( 'Twist:     ' + objResponse.data );
														objTwistWS.objOptions.ondebug( objResponseData );
														break;

													case 'echo':
														objTwistWS.send( 'twist', 'echo', objResponseData );
														break;

													case 'error':
														objTwistWS.log( 'Error:     ' + objResponse.data );
														break;

													case 'login':
														objTwistWS.log( '===============\nTWISTPHP\nWebsocket Server\n===============\n' + objResponseData.message );

														while( objTwistWS.arrQueuedMessages.length ) {
															var objPacket = objTwistWS.arrQueuedMessages[0];
															objTwistWS.send( objPacket.system, objPacket.action, objPacket.data );
															objTwistWS.arrQueuedMessages.shift();
														}

														objTwistWS.reconnects = 0;
														objTwistWS.objOptions.onfirstlogin();
														objTwistWS.objOptions.onfirstlogin = function() {};
														objTwistWS.objOptions.onlogin();
														break;

													case 'promptLogin':
														objTwistWS.login();
														break;

													case 'restart':
														objTwistWS.log( 'Restart:   ' + objResponseData.data.time + ' seconds' );
														objTwistWS.objOptions.onrestart( objResponseData );
														objTwistWS.intAutoreconnectTime = objTwistWS.objOptions.autoreconnecttime;
														objTwistWS.objOptions.autoreconnecttime = ( objResponseData.data.time + 2 ) * 1000;
														break;

													default:
														objTwistWS.log( 'Unknown:   ' + objResponse.data );
														break;
												}
											} else if( objResponseData.system === 'debug' ) {
												switch( objResponseData.action ) {
													case 'echo':
													case 'echoEveryone':
														var strConsoleTitle = ( objResponseData.action === 'echoEveryone' ) ? 'Echo:      ' : 'Echo All:  ';

														if( objTwistWS.objEchos[objResponseData.instance] ) {
															var fltPingTime = ( new Date() ).getTime() - objTwistWS.objEchos[objResponseData.instance];
															objTwistWS.log( strConsoleTitle + fltPingTime + 'ms' );
															delete objTwistWS.objEchos[objResponseData.instance];
														} else {
															objTwistWS.log( strConsoleTitle + objResponseData.instance );
														}

														objTwistWS.objOptions.onmessage( objResponseData );
														break;

													case 'echoEveryoneElse':
														objTwistWS.log( 'Echo EE:    ' + objResponseData.instance );
														objTwistWS.objOptions.onmessage( objResponseData );
														break;

													default:
														objTwistWS.log( 'Debug:     ' + objResponse.data );
														objTwistWS.objOptions.ondebug( objResponseData );
														break;
												}
											} else {
												objTwistWS.log( 'Received:  ' + objResponse.data );
												objTwistWS.objOptions.onmessage( objResponseData );

												if( isSet( objTwistWS.objCallbacks[objResponseData.instance] ) ) {
													var funCallback = objTwistWS.objCallbacks[objResponseData.instance];
													funCallback( objResponseData );
													delete objTwistWS.objCallbacks[objResponseData.instance];
												}

												if( isSet( objTwistWS.objOptions.actions[objResponseData.system] )
														&& typeof objTwistWS.objOptions.actions[objResponseData.system][objResponseData.action] === 'function' ) {
													objTwistWS.objOptions.actions[objResponseData.system][objResponseData.action]( objResponseData );
												}
											}
										} catch( ex ) {
											objTwistWS.log( ex );
										}
									},
								objTwistWS.webSocket.onerror = function( objError ) {
										objTwistWS.status = this.readyState;
										objTwistWS.log( 'Error:', objError );

										try {
											objTwistWS.objOptions.onchangestatus( objTwistWS.webSocket.readyState );
											objTwistWS.objOptions.onerror( objError );

											if( objTwistWS.objOptions.autoreconnectonerror
													&& ( objTwistWS.status === 0
														|| objTwistWS.status === 2
														|| objTwistWS.status === 3 ) ) {
												clearTimeout( objTwistWS.funReconnectTimeout );

												objTwistWS.funReconnectTimeout = setTimeout(
													function() {
														objTwistWS.log( 'Status:    Reconnecting...(' + ( objTwistWS.reconnects + 1 ) + ' of ' + objTwistWS.objOptions.autoreconnects + ')' );
														objTwistWS.connect();
													}, objTwistWS.objOptions.autoreconnecttime
												);
											}
										} catch( ex ) {
											objTwistWS.log( ex );
										}
									},
								objTwistWS.webSocket.onclose = function( objEvent ) {
										objTwistWS.status = this.readyState;
										objTwistWS.log( 'Status:    Connection closed' );

										try {
											objTwistWS.objOptions.onchangestatus( objTwistWS.webSocket.readyState );
											objTwistWS.objOptions.onclose( objEvent );

											if( objTwistWS.objOptions.autoreconnectonclose
													&& ( objTwistWS.status === 0
														|| objTwistWS.status === 2
														|| objTwistWS.status === 3 ) ) {
												clearTimeout( objTwistWS.funReconnectTimeout );

												objTwistWS.funReconnectTimeout = setTimeout(
													function() {
														objTwistWS.log( 'Status:    Reconnecting...(' + ( objTwistWS.reconnects + 1 ) + ' of ' + objTwistWS.objOptions.autoreconnects + ')' );
														objTwistWS.connect();
													}, objTwistWS.objOptions.autoreconnecttime
												);
											}
										} catch( ex ) {
											objTwistWS.log( ex );
										}
									};

								return true;
							}
						},
					objTwistWS.funActivityWatcher,
					objTwistWS.funReconnectTimeout,
					objTwistWS.getStatus = function() {
							var arrSocketStatus = {
									0: 'Not Connected',
									1: 'Connected',
									2: 'Connection Lost',
									3: 'Closed',
									4: 'Connecting...'
								};

							return arrSocketStatus[objTwistWS.status];
						},
					objTwistWS.jsonDecode = function( strRaw ) {
							if( JSON ) {
								try {
									return JSON.parse( strRaw );
								} catch( ex ) {
									return {
											failed: ex
										};
								}
							} else {
								return $.parseJSON( strRaw );
							}
						},
					objTwistWS.jsonEncode = function( objRaw ) {
							return JSON.stringify( objRaw );
						},
					objTwistWS.kill = function() {
							var blOriginalAutoreconnectOnClose = objTwistWS.objOptions.autoreconnectonclose;
							objTwistWS.objOptions.autoreconnectonclose = false;
							objTwistWS.objOptions.autoreconnectonerror = false;
							objTwistWS.log( objTwistWS.getStatus() );
							clearTimeout( objTwistWS.funReconnectTimeout );
							clearTimeout( objTwistWS.funActivityWatcher );
							objTwistWS.webSocket.close();
							objTwistWS.log( objTwistWS.getStatus() );
							objTwistWS.objOptions.autoreconnectonclose = blOriginalAutoreconnectOnClose;
						},
					objTwistWS.login = function() {
							objTwistWS.log( 'Log in:    ' + objTwistWS.jsonEncode( objTwistWS.objOptions.credentials ) );
							objTwistWS.send( 'twist', 'login', objTwistWS.objOptions.credentials );
						},
					objTwistWS.logout = function() {
							objTwistWS.send( 'twist', 'logout' );
						},
					objTwistWS.objCallbacks = {},
					objTwistWS.objEchos = {},
					objTwistWS.reconnects = 0,
					objTwistWS.send = function( strSystem, strAction, a, b ) {
							var intTime = ( new Date() ).getTime(),
							strPacketID = intTime + randomString( 32 ),
							mxdSendData = {},
							funCallback = function() {};

							if( strSystem === 'debug'
									&& ( strAction === 'echo'
										|| strAction === 'echoEveryone' ) ) {
								objTwistWS.objEchos[strPacketID] = intTime;
							}

							if( typeof a === 'object'
									|| typeof a === 'string'
									|| typeof a === 'number'
									|| typeof a === 'array' ) {
								mxdSendData = a;
								if( typeof b === 'function' ) {
									funCallback = b;
								}
							} else if( typeof a === 'function' ) {
								funCallback = a;
							}

							var objPacketRaw = {
									system: strSystem,
									action: strAction,
									data: mxdSendData,
									instance: strPacketID
								},
							strPacket = objTwistWS.jsonEncode( objPacketRaw );

							if( objTwistWS.status === 1 ) {
								objTwistWS.log( 'Sending:   ' + strPacket );
								try {
									objTwistWS.webSocket.send( strPacket );
									objTwistWS.objCallbacks[strPacketID] = funCallback;
								} catch( ex ) {
									if( objTwistWS.objOptions.queuemessages ) {
										objTwistWS.log( 'Queued:    ' + strPacket );
										objTwistWS.arrQueuedMessages.push( objPacketRaw );
									} else {
										objTwistWS.log( 'Failed:    ' + strPacket );
									}

									objTwistWS.log( ex );
									objTwistWS.objOptions.onerror();
									return false;
								}
							} else {
								if( objTwistWS.objOptions.queuemessages ) {
									objTwistWS.log( 'Queued:    ' + strPacket );
									objTwistWS.arrQueuedMessages.push( objPacketRaw );
								} else {
									objTwistWS.log( 'Failed:    ' + strPacket );
								}
								if( objTwistWS.status !== 4 ) {
									objTwistWS.connect();
									return false;
								}
							}

							return strPacketID;
						},
					objTwistWS.strSocketUrl = ( window.location.protocol === 'https:' ? 'wss' : 'ws' ) + '://' + objTwistWS.socketDomain + ':' + objTwistWS.port,
					objTwistWS.webSocket;

					var objOriginalEvents = {
							onblur: isNull( window.onblur ) ? function() {} : window.onblur,
							onfocus: isNull( window.onfocus ) ? function() {} : window.onfocus,
							onkeypress: isNull( window.onkeypress ) ? function() {} : window.onkeypress,
							onload: isNull( window.onload ) ? function() {} : window.onload,
							onmousemove: isNull( window.onmousemove ) ? function() {} : window.onmousemove,
							ononline: isNull( window.ononline ) ? function() {} : window.ononline,
							onoffline: isNull( window.onoffline ) ? function() {} : window.onoffline,
							onpopstate: isNull( window.onpopstate ) ? function() {} : window.onpopstate
						};

					window.ononline = function( e ) {
							objOriginalEvents.ononline( e );
							objTwistWS.objOptions.ononline();

							if( objTwistWS.objOptions.debug ) {
								objTwistWS.log( 'Browser came online' );
							}

							if( objTwistWS.objOptions.autoreconnectonoffline
									&& ( objTwistWS.status === 0
										|| objTwistWS.status === 2
										|| objTwistWS.status === 3 )
									&& objTwistWS.reconnects < objTwistWS.objOptions.autoreconnects ) {
								try {
									objTwistWS.connect();
								} catch( ex ) {
									objTwistWS.log( ex );
								}
							}
						},
					window.onoffline = function( e ) {
							objOriginalEvents.onoffline( e );
							objTwistWS.objOptions.onoffline();

							if( objTwistWS.objOptions.debug ) {
								objTwistWS.log( 'Browser went offline' );
							}

							objTwistWS.webSocket.close();
							objTwistWS.webSocket = null;
						};

					for( var strProperty in objUserOptions ) {
						objTwistWS.objOptions[strProperty] = objUserOptions[strProperty];
					}

					objTwistWS.intAutoreconnectTime = objTwistWS.objOptions.autoreconnecttime;

					if( objTwistWS.objOptions.shareactivity ) {
						var windowActivity = function() {
								if( !objTwistWS.blUserActive ) {
									objTwistWS.send( 'twist', 'active' );
									objTwistWS.send( 'twist', 'uri', window.location.href );
									objTwistWS.blUserActive = true;
								}

								clearTimeout( objTwistWS.funActivityWatcher );

								objTwistWS.funActivityWatcher = setTimeout(
									function() {
										objTwistWS.send( 'twist', 'inactive' );
										objTwistWS.send( 'twist', 'uri', window.location.href );
										objTwistWS.blUserActive = false;
									}, objTwistWS.objOptions.activitytimeout
								);
							};
						window.onload = function( e ) {
								objOriginalEvents.onload( e );
								windowActivity.call();
							},
						window.onmousemove = function( e ) {
								objOriginalEvents.onmousemove( e );
								windowActivity.call();
							},
						window.onkeypress = function( e ) {
								objOriginalEvents.onkeypress( e );
								windowActivity.call();
							},
						window.onpopstate = function( e ) {
								objOriginalEvents.onpopstate( e );
								objTwistWS.send( 'twist', 'uri', window.location.href );
							},
						window.onfocus = function( e ) {
								objOriginalEvents.onfocus( e );
								objTwistWS.send( 'twist', 'focus' );
							},
						window.onblur = function( e ) {
								objOriginalEvents.onblur( e );
								objTwistWS.send( 'twist', 'blur' );
							};
					}

					return objTwistWS.connect() ? objTwistWS : false;
				};

			window.TwistWebSocket = function( strSocketLocation, a, b ) {
					return new objTwistWebSocket( strSocketLocation, a, b );
				};
		} catch( err ) {
			if( window.console
					&& window.console.log ) {
				console.log( err );
			}
		}
	}
)( window, document );