<?php
/**
 *
 * If not using actual command line args:
 *   php -d register_argc_argv=false
 *
 * On autostart first line of shell scripts:
 *   #!/usr/bin/php -d register_argc_argv=false
 *
 */

abstract class WeirdoCommandLineApp {

  public function __construct( $argv = null, $env = null ) {
    $this->init( $argv, $env );
  }
  
  public function run( $argv = null, $env = null ) {
    $_this = isset( $this ) ? $this : new static();
    $_this->init( $argv, $env );
    return $_this->_run();
  }
  
  public function init( $argvParam = null, $env = null ) {
    if ( $argvParam === null ) {
      global $argv;
      $argvParam = isset( $argv ) ? $argv : array( $_SERVER['SCRIPT_NAME'] );
    }
    if ( $env === null ) {
      $env = $_ENV;
    }
    $this->_argv = $argvParam;
    $this->_env = $env;
  }
  
  protected function _getopt( $optargs = null /*, ... */ ) {
    $functionArgs = func_get_args();
    if ( !isset( $functionArgs[0] ) ) {
      $functionArgs[0] = $this->_optargs;
    }
    if ( ( !isset( $functionArgs[1] ) ) && isset( $this->_longopts ) )  {
      // n.b. getopt before PHP 5.3 might trigger an error with this parameter
      $functionArgs[1] = $this->_longopts;
    }
    
    // TODO: get a better getopt(), where this isn't necessary
    global $argv;
    $save_argv = $argv;
    $argv = $this->_argv;
    
    $this->_options = call_user_func_array( 'getopt', $functionArgs );
    
    // remove all options from the argument array
    // TODO: remove only valid options; fail on invalid options via $this->_usage()
    // TODO: move this inside a better getopt()
    $argc = count( $argv );
    for ( $i = 1; $i < $argc ; $i++ ) {
      $v = $argv[$i];
      if ( isset( $argv[$i][0] ) && ( $argv[$i][0] === '-' ) ) {
        unset( $argv[$i] );
        if ( $v === '--' ) {
          break;
        }
      }
    }
    // renumber the array keys
    $argv = array_values( $argv );
 
    $this->_argv = $argv;
    $argv = $save_argv;
    return $this->_options;
  }
  
  protected function _getEnv( $name = null ) {
    if ( $name === null ) {
      return $this->_env;
    }
    return isset( $this->_env[$name] ) ? $this->_env[$name] : null;
  }
 
  protected function _usage() {
    trigger_error(
      sprintf( "%s: Unrecognized command line option(s).\n", basename( $this->_argv[0] ) ),
      E_USER_ERROR
    );
  }
 
  protected abstract function _run();
  
  /** command line single-character options */
  protected $_optargs = 'v::';
 
  /** command line long-name options */
  protected $_longopts = array( 'verbose::' );
 
  /** command line arguments */
  protected $_argv;
  
  /** environment */
  protected $_env;
  
  /** options provided on the command line */
  protected $_options;
 
}
