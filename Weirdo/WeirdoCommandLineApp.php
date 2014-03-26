<?php

abstract class WeirdoCommandLineApp {

  public function __construct( $args, $env ) {
    $this->_argsDefault = $args;
    $this->_envDefault = $env;
  }
 
  public function run( $args = null, $env = null ) {
    if ( !isset( $this ) ) {
      $s = new static( $args, $env );
      return $s->run();
    }
    if ( $args === null ) {
      $this->_args = $this->_argsDefault;
    }
    if ( $env === null ) {
      $this->_env = $this->_envDefault;
    }
    
    return $this->_run( $this->_args, $this->_env );
  }
 
  public static function _initStatics() {
  }
  
  protected abstract function _run( $args, $env ) ;
  
  private $_args, $_argsDefault;
  private $_env, $_envDefault;
 
}
