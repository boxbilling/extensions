<?php

/**
 * <pre>
 * Invision Power Services
 * IP.Board v3.2.3
 * XML-RPC Server: Send, receive and process XML-RPC requests
 * Last Updated: $Date: 2011-05-09 18:07:59 -0400 (Mon, 09 May 2011) $
 * </pre>
 *
 * @author 		$Author: bfarber $
 * @copyright	(c) 2001 - 2009 Invision Power Services, Inc.
 * @license		http://www.hackdatabase.com/community/board/license.html
 * @package		IP.Board
 * @subpackage	Kernel
 * @link		http://www.hackdatabase.com
 * @since		6th January 2006
 * @version		$Revision: 8688 $
 *
 *
 * Example Usage:
 * <code>
 * SENDING XML-RPC Request
 * (Optional)
 * $xmlrpc->map_type_to_key['key']  = 'string';
 * $xmlrpc->map_type_to_key['key2'] = 'base64';
 * $return = $xmlrpc->sendXmlRpc( 'http://domain.com/xml-rpc_server.php', 'methodNameHere', array( 'key' => 'value', 'key2' => 'value2' ) );
 * if ( $xmlrpc->errors )
 * {
 * 	print_r( $xmlrpc->errors );
 * }
 * 
 * Decoding XML-RPC
 * $xmlrpc->decode( $raw_xmlrpc_text );
 * 
 * print_r( $xmlrpc->xmlrpc_params );
 * RETURN
 * $xmlrpc->returnTrue();
 * </code>
 *
 */
 
class classXmlRpc
{
	/**
	 * XML header
	 *
	 * @access	public
	 * @var 		string
	 */
	public $header			= "";
	
	/**
	 * DOC type
	 *
	 * @access	public
	 * @var 		string
	 */
	public $doc_type		= 'UTF-8';
	
	/**
	 * Error array
	 *
	 * @access	public
	 * @var 		array
	 */
	public $errors			= array();
	
	/**
	 * Var types
	 *
	 * @access	public
	 * @var 		array
	 */
	public $var_types		= array('string', 'int', 'i4', 'double', 'dateTime.iso8601', 'base64', 'boolean');
	
	/**
	 * Extracted xmlrpc params
	 *
	 * @access	public
	 * @var		array
	 */
	public $xmlrpc_params	= array();
	
	/**
	 * Optionally map types to key
	 *
	 * @access	public
	 * @var 		array
	 */
	public $map_type_to_key	= array();
	
	/**
	 * Auth required
	 *
	 * @access	public
	 * @var 		string
	 */
	public $auth_user 		= '';
	public $auth_pass 		= '';
	
	/**
	 * Decode an XML RPC document
	 *
	 * @access	public
	 * @param	string		XML-RPC data
	 * @return	string		Decoded document
	 */
	public function decodeXmlRpc( $_xml )
	{
		$xml_parser	= new xmlRpcParser();
		$data		= $xml_parser->parse( $_xml );
	
		if ( isset( $data['methodResponse']['fault'] ) )
		{
			$tmp			= $this->adjustValue( $data['methodResponse']['fault']['value'] );
			$this->errors[] = $tmp['faultString'];
		}
		
		$this->xmlrpc_params	  = $this->getParams( $data );
		$this->xmlrpc_method_call = $this->getMethodName( $data );
		
		//-----------------------------------------
		// Debug?
		//-----------------------------------------
		
		if ( IPS_XML_RPC_DEBUG_ON )
		{
			$this->addDebug( "DECODING XML data: " . $_xml );
			$this->addDebug( "DECODE RESULT XML data: " . var_export( $data, TRUE ) );
		}

		return $data;
	}
	
	/**
	 * Adjust value of parameter
	 *
	 * @access	public
	 * @param	string		Curernt node
	 * @return	mixed		Proper cast value
	 */
	public function & adjustValue( &$current_node )
	{
		if ( is_array( $current_node ) )
		{
			if ( isset($current_node['array']) )
			{
				if ( ! is_array($current_node['array']['data']) )
				{
					$temp = array();
				}
				else
				{
					$temp = &$current_node['array']['data']['value'];
	
					if ( is_array($temp) and array_key_exists(0, $temp) )
					{
						$count = count($temp);
	
						for( $n = 0 ; $n < $count ; $n++ )
						{
							$temp2[$n] = & $this->adjustValue($temp[$n]);
						}
	
						$temp = &$temp2;
	
					}
					else
					{
						$temp2 = & $this->adjustValue($temp);
						$temp = array(&$temp2);
					}
				}
			}
			elseif ( isset($current_node['struct']) )
			{
				if ( ! is_array($current_node['struct']) )
				{
					return array();
				}
				else
				{
					$temp = &$current_node['struct']['member'];
	
					if ( is_array($temp) and array_key_exists(0, $temp) )
					{
						$count = count($temp);
	
						for( $n = 0 ; $n < $count ; $n++ )
						{
							$temp2[$temp[$n]['name']] = & $this->adjustValue($temp[$n]['value']);
						}
					}
					else
					{
						$temp2[$temp['name']] = & $this->adjustValue($temp['value']);
					}
					$temp = &$temp2;
				}
			}
			else
			{
				$got_it = false;
	
				foreach( $this->var_types as $type )
				{
					if ( array_key_exists($type, $current_node) )
					{
						$temp   = &$current_node[$type];
						$got_it = true;
						break;
					}
				}
	
				if ( ! $got_it )
				{
					$type = 'string';
					
				}
	
				switch ($type)
				{
					case 'int':
	 				case 'i4':
					case 'integer':
					case 'integar':
						$temp = (int)	$temp;
						break;
					case 'string':
						$temp = (string) $temp;
						break;
					case 'double':
						$temp = (double) $temp; 
						break;
					case 'boolean':
						$temp = (bool)   $temp;
						break;
					case 'base64':
						$temp = base64_decode(trim($temp));
						break;
				}
			}
		}
		else
		{
			$temp = (string) $current_node;
		}
	
		return $temp;
	}
	
	/**
	 * Get the params from the XML RPC return
	 *
	 * @access	public
	 * @param	array		Request data
	 * @return	array		Params
	 */
	public function getParams( $request )
	{
		if ( isset( $request['methodCall']['params'] ) AND is_array( $request['methodCall']['params'] ) )
		{
			$temp = & $request['methodCall']['params']['param'];
		}
		else if ( isset( $request['methodResponse']['params'] ) AND is_array( $request['methodResponse']['params'] ) )
		{
			$temp = & $request['methodResponse']['params']['param'];
		}
		else
		{
			return array();
		}
	   
		if ( is_array( $temp ) and array_key_exists( 0, $temp ) )
		{
			$count = count($temp);

			for( $n = 0 ; $n < $count ; $n++)
			{
				$temp2[$n] = & $this->adjustValue($temp[$n]['value']);
			}
		}
		else
		{
			$temp2[0] = & $this->adjustValue($temp['value']);
		}

		$temp = &$temp2;

		return $temp;
	}
	
	/**
	 * Get RPC method name
	 *
	 * @access	public
	 * @param	array		Request params
	 * @return	string		Method name
	 */
	public function getMethodName( $request )
	{
		return isset( $request['methodCall']['methodName'] ) ? $request['methodCall']['methodName'] : '';
	}
	
	/**
	 * Create and send an XML document
	 *
	 * @access	public
	 * @param	string		URL to send XML-RPC data to
	 * @param	string		Method name to request
	 * @param	array   	Array of fields to send (must be in key => value pairings)
	 * @return	boolean		Sent successfully
	 */
	public function sendXmlRpc( $url, $method_name='', $data_array=array() )
	{
		//-----------------------------------------
		// Build RPC request
		//-----------------------------------------
		
		$xmldata = $this->buildDocument( $data_array, $method_name );
		
		if ( $xmldata )
		{
			//-----------------------------------------
			// Debug?
			//-----------------------------------------
			
			if ( IPS_XML_RPC_DEBUG_ON )
			{
				$this->addDebug( "SENDING XML data: " . $xmldata );
			}
			
			//-----------------------------------------
			// Continue
			//-----------------------------------------
			
			return $this->post( $url, $xmldata );
		}
		else
		{
			return false;
		}
	}
	
	/**
	 * Prints a true document and exits
	 *
	 * @access	public
	 * @param	string		Value
	 * @return	@e void
	 */
	public function returnValue( $value )
	{
		$this->generateHeader();
		
		$to_print = $this->header."<methodResponse>
		   <params>
			  <param>
				 <value>{$value}</value>
				 </param>
			  </params>
		   </methodResponse>";

		@header( "Connection: close" );
		@header( "Content-length: " . strlen($to_print) );
		@header( "Content-type: text/xml" );
		@header( "Date: " . date("r") );
		print $to_print;
		
		$this->addDebug( "ReturnValue: {$to_print}" );

		exit();
	}

	/**
	 * Creates an XML-RPC complex document
	 *
	 * @access	public
	 * @param	array   	Array of fields to send (must be in key => value pairings)
	 * @param	string		Method name (optional)
	 * @return	string		finished document
	 */
	public function buildDocument( $data_array, $method_name='' )
	{
		//-----------------------------------------
		// INIT
		//-----------------------------------------
		
		$xmldata  = "";
		$root_tag = 'methodCall';
		
		//-----------------------------------------
		// Test
		//-----------------------------------------
		
		if ( ! is_array( $data_array ) or ! count( $data_array ) )
		{
			return false;
		}
		
		if ( ! $method_name )
		{
			$root_tag = 'methodResponse';
		}
		
		$this->generateHeader();
		
		$xmldata  = $this->header . "\n";
		$xmldata .= "<".$root_tag.">\n";
		
		if ( $method_name )
		{
			$xmldata .= "\t<methodName>".$method_name."</methodName>\n";
		}
		
		$xmldata .= "\t<params>\n";
		$xmldata .= "\t\t<param>\n";
		$xmldata .= "\t\t\t<value>\n";

		if ( isset( $data_array[0] ) AND is_array( $data_array[0] ) )
		{
 			$xmldata .= "\t\t\t<array>\n";
			$xmldata .= "\t\t\t\t<data>\n";

			foreach( $data_array as $k => $v )
			{
				$xmldata .= "\t\t\t\t\t<value>\n";
				$xmldata .= "\t\t\t\t\t\t<struct>\n";
				
				foreach( $v as $k2 => $v2 )
				{
					$_type = $this->map_type_to_key[ $k2 ] ? $this->map_type_to_key[ $k2 ] : $this->getStringType( $v2 );

					$xmldata .= "\t\t\t\t\t\t\t<member>\n";
					$xmldata .= "\t\t\t\t\t\t\t\t<name>".$k2."</name>\n";
					
					if ( strpos( $v2, '>' ) !== false OR strpos( $v2, '<' ) !== false OR strpos( $v2, '&' ) !== false )
					{
						$xmldata .= "\t\t\t\t\t\t\t\t<value><base64>" . base64_encode($v2) . "</base64></value>\n";
					}
					else
					{
						$xmldata .= "\t\t\t\t\t\t\t\t<value><".$_type.">" . htmlspecialchars($v2) . "</".$_type."></value>\n";
					}

					$xmldata .= "\t\t\t\t\t\t\t</member>\n";
				}
				
				$xmldata .= "\t\t\t\t\t\t</struct>\n";
				$xmldata .= "\t\t\t\t\t</value>\n";
			}

			$xmldata .= "\t\t\t\t</data>\n";
			$xmldata .= "\t\t\t</array>\n";
		}
		else
		{
			$xmldata .= "\t\t\t<struct>\n";

			foreach( $data_array as $k => $v )
			{
				if ( is_array( $v ) )
				{
					$xmldata .= $this->_buildDocumentRecurse( "", $k, $v, 4 );
				}
				else
				{
					$_type = isset( $this->map_type_to_key[ $k ] ) ? $this->map_type_to_key[ $k ] : $this->getStringType( $v );

					$xmldata .= "\t\t\t\t<member>\n";
					$xmldata .= "\t\t\t\t\t<name>".$k."</name>\n";
					
					if ( strpos( $v, '>' ) !== false OR strpos( $v, '<' ) !== false OR strpos( $v, '&' ) !== false )
					{
						$xmldata .= "\t\t\t\t\t\t\t\t<value><base64>" . base64_encode($v) . "</base64></value>\n";
					}
					else
					{
						$xmldata .= "\t\t\t\t\t<value><".$_type.">" . htmlspecialchars($v) . "</".$_type."></value>\n";
					}
					
					$xmldata .= "\t\t\t\t</member>\n";
				}
			}

			$xmldata .= "\t\t\t</struct>\n";
		}

		$xmldata .= "\t\t\t</value>\n";
		$xmldata .= "\t\t</param>\n";
		$xmldata .= "\t</params>\n";
		$xmldata .= "</".$root_tag.">";
		
		return $xmldata;
	}
	
	/**
	 * Recursive method to build document
	 *
	 * @access	protected
	 * @param	string		XML Data
	 * @param	string		Key
	 * @param	string		Value
	 * @param	integer		Depth
	 * @return	string		finished document
	 */
	protected function _buildDocumentRecurse( $xmldata, $k, $v, $depth=4 )
	{
		$xmldata .= "\t<member>\n";
		$xmldata .= "\t\t<name>".$k."</name>\n";
		$xmldata .= "\t\t<value>\n";
		$xmldata .= "\t\t\t<array>\n";
		$xmldata .= "\t\t\t\t<data>\n";
		$xmldata .= "\t\t\t\t\t<value>\n";
		$xmldata .= "\t\t\t\t\t\t<struct>\n";
		
		foreach( $v as $_k => $_v )
		{
			if ( is_array( $_v ) )
			{
				$depth++;
				$xmldata .= $this->_buildDocumentRecurse( $xmldata, $k, $v, $depth );
			}
			else
			{
				$_type = isset( $this->map_type_to_key[ $_k ] ) ? $this->map_type_to_key[ $_k ] : $this->getStringType( $_v );
				
				$xmldata .= "\t\t\t\t\t\t\t<member>\n";
				$xmldata .= "\t\t\t\t\t\t\t\t<name>".$_k."</name>\n";
				
				if ( strpos( $_v, '>' ) !== false OR strpos( $_v, '<' ) !== false OR strpos( $_v, '&' ) !== false )
				{
					$xmldata .= "\t\t\t\t\t\t\t\t<value><base64>" . base64_encode($_v) . "</base64></value>\n";
				}
				else
				{
					$xmldata .= "\t\t\t\t\t<value><".$_type.">" . htmlspecialchars($_v) . "</".$_type."></value>\n";
				}

				$xmldata .= "\t\t\t\t\t\t\t</member>\n";
			}
		}
		
		$xmldata .= "\t\t\t\t\t\t</struct>\n";
		$xmldata .= "\t\t\t\t\t</value>\n";
		$xmldata .= "\t\t\t\t</data>\n";
		$xmldata .= "\t\t\t</array>\n";
		$xmldata .= "\t\t</value>\n";
		$xmldata .= "\t</member>\n";
		
		return $xmldata;
	}
	
	/**
	 * Prints a true document and exits
	 *
	 * @access	public
	 * @return	@e void
	 */
	public function returnTrue()
	{
		$this->generateHeader();
		
		$to_print = $this->header."
		<methodResponse>
		   <params>
			  <param>
				 <value><boolean>1</boolean></value>
				 </param>
			  </params>
		   </methodResponse>";
		
		@header( "Connection: close" );
		@header( "Content-length: ".strlen($to_print) );
		@header( "Content-type: text/xml" );
		@header( "Date: " . date("r") ); 
		print $to_print;
		
		$this->addDebug( "ReturnTrue: {$to_print}" );
		
		exit();
	}
	
	/**
	 * Prints a document and exits
	 *
	 * @access	public
	 * @param	array  		Array of params to return in key => value pairs
	 * @return	@e void
	 */
	public function returnParams( $data_array )
	{
		$to_print = $this->buildDocument( $data_array );
		@header( "Connection: close" );
		@header( "Content-length: ".strlen($to_print) );
		@header( "Content-type: text/xml" );
		@header( "Date: " . date("r") );
		@header( "Pragma: no-cache" );
		@header( "Cache-Control: no-cache" );
		print $to_print;
		
		$this->addDebug( "ReturnParams: {$to_print}" );
		
		exit();
	}
	
	/**
	 * Prints a true document and exits
	 *
	 * @access	public
	 * @param	int			Error code
	 * @param	string		Error Message
	 * @return	@e void
	 */
	public function returnError( $error_code, $error_msg )
	{
		$this->generateHeader();
		
		$to_print = $this->header . "
		<methodResponse>
		   <fault>
			  <value>
				 <struct>
					<member>
					   <name>faultCode</name>
					   <value>
						  <int>".intval($error_code)."</int>
						  </value>
					   </member>
					<member>
					   <name>faultString</name>
					   <value>
						  <string>".$error_msg."</string>
						  </value>
					   </member>
					</struct>
				 </value>
					</fault>
		   </methodResponse>";
		
		@header( "Connection: close" );
		@header( "Content-length: ".strlen($to_print) );
		@header( "Content-type: text/xml" );
		@header( "Date: " . date("r") ); 
		print $to_print;
		
		exit();
	}

	/**
	 * Create and send an XML document
	 *
	 * @access	public
	 * @param	string		URL to send XML-RPC data to
	 * @param	array   	XML-RPC data
	 * @return	string		Decoded data
	 */
	public function post( $file_location, $xmldata='' )
	{
		//-----------------------------------------
		// INIT
		//-----------------------------------------

		$data			= null;
		$fsocket_timeout = 10;
		$header		  = "";
		
		//-----------------------------------------
		// Send it..
		//-----------------------------------------

		$url_parts = parse_url($file_location);
		
		if ( ! $url_parts['host'] )
		{
			$this->errors[] = "No host found in the URL '{$file_location}'!";
			return false;
		}
		
		//-------------------------------
		// Finalize
		//-------------------------------
		
		$host = $url_parts['host'];
		$_host = $url_parts['scheme'] == 'https' ? 'ssl://' . $host : $host;
	  	
		$port = ( isset($url_parts['port']) ) ? $url_parts['port'] : ( $url_parts['scheme'] == 'https' ? 443 : 80 );
	  	
		//-----------------------------------------
		// User and pass?
		//-----------------------------------------
		
		if ( ! $this->auth_user AND $url_parts['user'] )
		{
			$this->auth_user = $url_parts['user'];
			$this->auth_pass = $url_parts['pass'];
		}
		
	  	//-------------------------------
	  	// Tidy up path
	  	//-------------------------------
	  	
	  	if ( ! empty( $url_parts["path"] ) )
		{
			$path = $url_parts["path"];
		}
		else
		{
			$path = "/";
		}
 
		if ( ! empty( $url_parts["query"] ) )
		{
			$path .= "?" . $url_parts["query"];
		}
		
		if ( ! $fp = @fsockopen( $_host, $port, $errno, $errstr, $fsocket_timeout ) )
		{
			$this->errors[] = "CONNECTION REFUSED FROM {$host}";
			return FALSE;
		
		}
		else
		{
			$header  = "POST $path HTTP/1.0\r\n";
			$header .= "User-Agent: IPS XML-RPC Client Library (\$Revision: 8688 $)\r\n";
			$header .= "Host: $host\r\n";
			
			if ( $this->auth_user && $this->auth_pass )
			{
				$this->addDebug( "Authorization: Basic Performed" );

				$header .= "Authorization: Basic ".base64_encode("{$this->auth_user}:{$this->auth_pass}")."\r\n";
			}
			
			$header .= "Connection: close\r\n";
			$header .= "Content-Type: text/xml\r\n";
			$header .= "Content-Length: " . strlen($xmldata) . "\r\n\r\n";
			
			if ( ! fputs( $fp, $header . $xmldata ) )
			{
				$this->errors[] = "Unable to send request to $host!";
				return FALSE;
			}
		 }

		 @stream_set_timeout($fp, $fsocket_timeout);
		
		 $status = @socket_get_status($fp);
		
		 while( ! feof($fp) && ! $status['timed_out'] )		 
		 {
			$data  .= fgets ( $fp, 8192 );
			$status = socket_get_status($fp);
		 }
		
		fclose ($fp);
	   
		//-------------------------------
		// Strip headers
		//-------------------------------
		
		$tmp  = explode("\r\n\r\n", $data, 2);
		$data = trim($tmp[1]);

		//-----------------------------------------
		// Debug?
		//-----------------------------------------
		
		if ( IPS_XML_RPC_DEBUG_ON )
		{
			if( $this->auth_pass )
			{
				$_pass = str_repeat( 'x', strlen( $this->auth_pass ) - 1 ) . substr( $this->auth_pass, -1 );
			}
			else
			{
				$_pass = '';
			}

			$this->addDebug( "POST RESPONSE to {$this->auth_user}:{$_pass}@$host{$path}: " . $data );
		}
		
		//-----------------------------------------
		// Continue
		//-----------------------------------------
		
		return $this->decodeXmlRpc( $data );
	}
	
	/**
	 * Get the XML-RPC string type
	 *
	 * @access	public
	 * @param	string		String
	 * @return	string		XML-RPC String Type
	 */
	public function getStringType( $string )
	{
		$type = gettype( $string );
		
		switch( $type )
		{
			default:
			case 'string':
				$type = 'string';
				break;
			case 'integer':
				$type = 'int';
				break;
			case 'double':
				$type = 'double';
				break;
			case 'null':
			case 'boolean':
				$type = 'boolean';
				break;
		}
		
		return $type;
	}
	
	/**
	 * Add debug message
	 *
	 * @access	public
	 * @param	string		Log message
	 * @return	boolean		Saved successful
	 */
	public function addDebug( $msg )
	{
		if ( IPS_XML_RPC_DEBUG_FILE AND IPS_XML_RPC_DEBUG_ON )
		{
			$full_msg = "==================================================================\n"
					   . "SCRIPT NAME: " . $_SERVER["SCRIPT_NAME"] . "\n"
					   . gmdate( 'r' ) . ' - ' . $_SERVER['REMOTE_ADDR'] . ' - ' . $msg . "\n"
					   . "==================================================================\n";
			
			if ( $FH = @fopen( IPS_XML_RPC_DEBUG_FILE, 'a+' ) )
			{
				fwrite( $FH, $full_msg, strlen( $full_msg ) );
				fclose( $FH );
			}
		}
		
		return true;
	}
	
	/**
	 * Create the XML header
	 *
	 * @access	protected
	 * @return	@e void
	 */
	protected function generateHeader()
	{
		$this->header	= '<?xml version="1.0" encoding="' . $this->doc_type . '" ?>';
	}
}

class xmlRpcParser
{
	/**
	 * Parser object
	 *
	 * @access	public
	 * @var		object
	 */
	public $parser;
	
	/**
	 * Current document
	 *
	 * @access	public
	 * @var		string
	 */
	public $document;
	
	/**
	 * Current tag
	 *
	 * @access	public
	 * @var		string
	 */
	public $current;
	
	/**
	 * Parent tag
	 *
	 * @access	public
	 * @var		string
	 */
	public $parent;
	
	/**
	 * Parents
	 *
	 * @access	public
	 * @var		array
	 */
	public $parents;
	
	/**
	 * Last opened tag
	 *
	 * @access	public
	 * @var		string
	 */
	public $last_opened_tag;
	
	/**
	 * Constructor
	 *
	 * @access	public
	 * @param	string		Data
	 * @return	@e void
	 */
	public function __construct( $data=null )
	{
		$this->parser = xml_parser_create();

		xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 0);
		xml_set_object(			   $this->parser, $this);
		xml_set_element_handler(	  $this->parser, "_rpcOpen", "_rpcClose");
		xml_set_character_data_handler($this->parser, "_rpcData");
	}
	
	/**
	 * Destructor
	 *
	 * @access	public
	 * @return	@e void
	 */
	public function __destruct()
	{
		xml_parser_free( $this->parser );
	}
	
	/**
	 * Parse the XML data
	 *
	 * @access	public
	 * @param	string		Data
	 * @return	string		Parsed data
	 */
	public function parse( $data )
	{
		//-----------------------------------------
		// INIT
		//-----------------------------------------
		
		$this->document		= array();
		$this->parent		  = &$this->document;
		$this->parents		 = array();
		$this->last_opened_tag = NULL;

		//-----------------------------------------
		// Parse
		//-----------------------------------------
		
		xml_parse($this->parser, $data);
		
		//-----------------------------------------
		// Return...
		//-----------------------------------------
		$tmp = $this->document;
		return $tmp;
	}
	
	/**
	 * Open handler for XML object
	 *
	 * @access	protected
	 * @param	object		Parser reference
	 * @param	string		Tag
	 * @param	array 		Attributes
	 * @return	@e void
	 */
	protected function _rpcOpen($parser, $tag, $attributes)
	{
		//-----------------------------------------
		// INIT
		//-----------------------------------------
		
		$this->data			= "";
		$this->last_opened_tag = $tag;

		if ( array_key_exists( $tag, $this->parent ) )
		{
			if ( is_array( $this->parent[$tag] ) and array_key_exists( 0, $this->parent[$tag] ) )
			{
				$key = is_array( $this->parent[$tag] ) ? count( array_filter( array_keys($this->parent[$tag]), 'is_numeric' ) ) : 0;
			}
			else
			{
				$temp = &$this->parent[$tag];
				unset($this->parent[$tag]);

				$this->parent[$tag][0] = &$temp;

				if ( array_key_exists( $tag ." attr", $this->parent ) )
				{
					$temp = &$this->parent[ $tag ." attr" ];
					unset($this->parent[ $tag ." attr" ]);
					$this->parent[$tag]["0 attr"] = &$temp;
				}

				$key = 1;
			}

			$this->parent = &$this->parent[$tag];
		}
		else
		{
			$key = $tag;
		}

		if ( $attributes )
		{
			$this->parent[ $key ." attr" ] = $attributes;
		}

		$this->parent[$key] = array();
		$this->parent	   = &$this->parent[$key];

		//array_unshift($this->parents, &$this->parent);
		$this->_arrayUnshiftReference($this->parents, $this->parent);
	}
	
	/**
	 * Array unshift wrapper
	 *
	 * @access	protected
	 * @param	array		Array
	 * @param	string		Value
	 * @return	array 		New array
	 */
	protected function _arrayUnshiftReference(&$array, &$value)
	{
	   $return = array_unshift($array,'');
	   $array[0] =& $value;
	   return $return;
	}	

	/**
	 * XML data handler
	 *
	 * @access	protected
	 * @param	object		Parser reference
	 * @param	string		Data
	 * @return	@e void
	 */
	protected function _rpcData($parser, $data)
	{
		if ( $this->last_opened_tag != NULL )
		{
			$this->data .= $data;
		}
	}
	
	/**
	 * XML close handler
	 *
	 * @access	protected
	 * @param	object		Parser reference
	 * @param	string		Tag
	 * @return	@e void
	 */
	protected function _rpcClose($parser, $tag)
	{
		if ( $this->last_opened_tag == $tag )
		{
			$this->parent = $this->data;
			$this->last_opened_tag = NULL;
		}

		array_shift($this->parents);

		$this->parent = &$this->parents[0];
	}
}