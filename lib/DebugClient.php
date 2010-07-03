<?
/* -------------------------------------------------------------------------- */
/* PHP Client for DebugServer */
/* -------------------------------------------------------------------------- */

class DebugClientError extends Exception {}

class DebugClient {
  const METHOD_CLEAR = 1;
  const METHOD_MESSAGE = 2;
  const METHOD_DUMP = 3;
  const LEVEL_NONE = 0;
  const LEVEL_INFO = 1;
  const LEVEL_WARNING = 2;
  const LEVEL_ERROR = 3;
  
  protected static $instance;
  protected static $socket = null;
  protected static $connected = false;
  
  /* Get class instance */
  public static function instance() {
    if (isset(self::$instance) && (self::$instance instanceof self)) {
      return self::$instance;
    }
    else {
      self::$instance = new self();
      return self::$instance;
    }
  }
  
  /* Create connection to server */
  public static function connect($host='localhost', $port=9000) {
    self::$socket = socket_create(AF_INET, SOCK_STREAM, 0);
    if (self::$socket) {
      if (socket_connect(self::$socket, $host, $port)) {
        socket_set_block(self::$socket);
        self::$connected = true;
      }
      else {
        $err = socket_strerror(socket_last_error(self::$socket));
        throw new DebugClientError('Cannot connect to Debugger server! Reason: '.$err);
      }
    }
    else {
      throw new DebugClientError('Cannot initialize socket!');
    }
  }
  
  /* Close connection with server */
  public function close() {
    if ($this->connected) {
      socket_shutdown($this->socket);
      socket_close($this->socket);
      $this->socket = null;
    }
  }
  
  protected static function send_packet($method, $content=null) {
    if (!self::$connected) return;  
    $packet = array('method' => $method);
    if (!is_null($content)) $packet['content'] = $content;
    try {
      socket_write(self::$socket, json_encode($packet)."\n");
    }
    catch(Exception $ex) {
      throw DebugClientError("Error while sending data: ".$ex->getMessage()); 
    }
  }
  
  /* Send message packet to server*/
  protected static function send_message($message, $level=self::LEVEL_NONE) {
    $msg = array('level' => $level, 'message' => $message);
    self::send_packet(self::METHOD_MESSAGE, $msg);
  }
  
  /* Send object dump to server */
  protected static function send_dump($object, $name=null) {
    $item = array('object' => var_export($object, true));
    if (!is_null($name)) $item['name'] = $name;
    self::send_packet(self::METHOD_DUMP, $item);
  }
  
  /* ------------------------------------------------------------------------ */
  /* Publicly accessible methods */
  /* ------------------------------------------------------------------------ */
  
  /* Write plain message string */
  public static function message($str) {
    self::send_message($str); 
  }
  
  /* Write information message */
  public static function info($str) {
    self::send_message($str, self::LEVEL_INFO);
  }
  
  /* Write warning message */
  public static function warning($str) {
    self::send_message($str, self::LEVEL_WARNING);
  }
  
  /* Write error message */
  public static function error($str) {
    self::send_message($str, self::LEVEL_ERROR);
  }
  
  /* Write object dump */
  public static function dump($object, $name=null) {
    self::send_dump($object, $name);
  }
  
  /* Write clear command */
  public static function clear() {
    self::send_packet(self::METHOD_CLEAR);
  }
}

/* -------------------------------------------------------------------------- */
/* Base global functions */
/* -------------------------------------------------------------------------- */

function debug($msg)                    { DebugClient::message($msg); }
function debug_info($msg)               { DebugClient::info($msg); }
function debug_warning($msg)            { DebugClient::warning($msg); }
function debug_error($msg)              { DebugClient::error($msg); }
function debug_dump($obj, $name=null)   { DebugClient::dump($obj, $name); }
function debug_clear()                  { DebugClient::clear(); }

?>