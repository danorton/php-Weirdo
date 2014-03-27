<?php
/**
 * @addtogroup Weirdo
 *
 * This module provides the Weirdo PHP and "missing" PHP functions.
 *
 * @section Requirements
 *  - PHP 4.1
 *
 * @section Limitations
 *  - None
 * @{
 *
 * @file
 * @{
 * @copyright
 *   © 2014 Daniel Norton d/b/a WeirdoSoft - www.weirdosoft.com
 *
 * @section License
 * **GPL v3**\n
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * \n\n
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * \n\n
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 * @}
 */
 
require_once __DIR__ . "/WeirdoSingleton.php";

/**
 * This class provides various utility functions.
 *
 * It can be used either statically or via its singleton.
 *
 */
class Weirdo extends WeirdoSingleton {

	/** See http://php.net/debug_backtrace for semantics. */
	const DEBUG_BACKTRACE_PROVIDE_OBJECT = 0x0001;

	/** See http://php.net/debug_backtrace for semantics. */
	const DEBUG_BACKTRACE_IGNORE_ARGS    = 0x0002;

	/** See http://php.net/reserved.constants for semantics. */
	public static $K = array(
		'PHP_VERSION_ID'    => null,
		'E_USER_ERROR'      => 0x0100,
		'E_USER_WARNING'    => 0x0200,
		'E_USER_NOTICE'     => 0x0400,
		'E_USER_DEPRECATED' => 0x4000,
	);

	public static $userErrorTypeText = array(
		0x0100 => 'Fatal error',
	);

	/** */
	public static function wordsFromIdName( $idName ) {
		// replace underscores with spaces and reduce multiple spaces
		$words = preg_replace( '/([ _]+)/', ' ', $idName );
		// if the name had no lower case letters, make all but the first character lower case
		if ( strtoupper( $words ) === $words ) {
			$words = ucfirst( strtolower( $words ) );
		}
		$words = trim( $words );
		return $words;
	}

	/** */
	public static function logCallerError( $error_msg, $error_type = E_USER_NOTICE, $callerDepth = 0, $msg_format = null ) {
		if ( is_int( $error_type ) ) {
			$intErrorType = $error_type;
		} else {
			$re = '/^((0x[0-9a-f]+)|-?0*([0-9]+(\.[0-9]*)?))$/i';
			if ( preg_match( $re, $error_type ) ) {
				$intErrorType = preg_replace( $re, '$2$3', $error_type );
			} else {
				self::trigger_error( 'A non well formed numeric value encountered', E_USER_NOTICE, 1 );
				$intErrorType = intval( $error_type, 0 );
			}
		}
		if ( isset( self::$userErrorTypeText[ $intErrorType ] ) ) {
			$typeText = self::$userErrorTypeText[ $intErrorType ];
			if ( !( error_reporting() & $intErrorType ) ) {
				return true;
			}
		} else {
			$typeText = 'UNKNOWN_ERROR_TYPE';
		}

		$callerFrame = self::getCallStackFrame( $callerDepth + 1 );
		if ( !$callerFrame ) {
			self::logCallerError( 'Invalid caller depth when invoking ' . __METHOD__ . '; returning our caller\'s frame', E_USER_WARNING );
			$callerFrame = self::getCallStackFrame( 0 );
		}

		if ( $msg_format === null ) {
			$msg_format = 'PHP %s:  %s';
		}
		error_log(
			sprintf( "$msg_format in %s on line %u",
				$typeText,
				$error_msg,
				$callerFrame['file'],
				$callerFrame['line']
			),
			0
		);
		return true;
	}

	/** */
	public static function debugBacktrace( $options, $limit = 0, $start = 0 ) {
		// if given a Boolean, convert it to an int
		if ( is_bool( $options ) ) {
			$options = ( (int)$options );
		}

		// if debug_backtrace supports the $limit parameter, pass it along
		if ( self::$_backtraceHasLimit ) {
			$stack = debug_backtrace( $options, $limit ? ( $limit + 1 ) : 0 );
		} else {
			if ( self::$K['PHP_VERSION_ID'] >= 50306 ) {
				$stack = debug_backtrace( $options );
				$options &= ~self::DEBUG_BACKTRACE_IGNORE_ARGS;
			} else {
				$stack = debug_backtrace( (bool)( $options & self::DEBUG_BACKTRACE_PROVIDE_OBJECT ) );
			}
		}
		// remove requested number of stack frames, but not ALL of them!
		if ( $start >= 0 ) {
			array_splice( $stack, 0, $start + 1 );
		}

		// limit the stack size if this version of PHP didn't already do that for us
		if ( $limit && ( $limit < count( $stack ) ) ) {
			array_splice( $stack, $limit );
		}

		// Did the caller request his own calling frame?
		if ( $start < 0 ) {
			// remove junk from the frame
			unset( $stack[0]['function'] );
			unset( $stack[0]['class'] );
			unset( $stack[0]['type'] );
			// reindex starting at -1
			$stack = array( -1 => $stack[0] ) + array_slice( $stack, 1 );
		}

		// strip out the args if this version of PHP didn't already do that for us
		if ( $options & self::DEBUG_BACKTRACE_IGNORE_ARGS ) {
			foreach ( $stack as &$frame ) {
				unset( $frame['args'] );
			}
		}

		return $stack;
	}

	/** */
	public static function getCallStackFrame( $depth = 0, $options = null ) {
		if ( $options === null ) {
			$options = self::DEBUG_BACKTRACE_PROVIDE_OBJECT;
		}

		$frame = array_pop( self::debugBacktrace( $options, 1, max( -1, $depth ) + 1 ) );

		// remove junk from the frame
		unset( $frame['function'] );
		unset( $frame['class'] );
		unset( $frame['type'] );

		return $frame;
	}
 
  /** Recursively convert an array to an object */
  public static function objectFromArray( $array ) {
    $o = (object) null;
    foreach ( $array as $k => $v ) {
      // replace null array key with a valid object field name
      if ( $k === '' ) {
        $k = '_null_' . __METHOD__;
      }
      if ( is_array( $v ) ) {
        $o->{$k} = self::objectFromArray( $v ); // recurse on arrays
      } else {
        $o->{$k} = $v;
      }
    }
    return $o;
  }

	/**
	 * Initialize this class's static properties.
	 * @private
	 *
	 * PHP only allows variable declarations with simple constants, so we have this
	 * function for more complex initialization of statics. Although "public" in
	 * construction, it is usable in this source file, only, immediately after this
	 * class is declared. Any attempt to invoke this method a second time will throw
	 * a WMException.
	 */
	public static function _initStatic() {
		if ( !isset( self::$_self ) ) {
			if ( defined( 'PHP_VERSION_ID' ) ) {
				self::$K['PHP_VERSION_ID'] = PHP_VERSION_ID;
			} else {
				self::$K['PHP_VERSION_ID'] = (int) vsprintf( '%u%02u%02u', explode( '.', phpversion() ) );
			}
			if ( self::$K['PHP_VERSION_ID'] < 40400 ) {
				trigger_error( 'The ' . __CLASS__ . ' class is not supported for PHP verions earlier than PHP 4.4.', E_USER_WARNING );
			}

			self::$_backtraceHasLimit = ( self::$K['PHP_VERSION_ID'] >= 50400 );

			$coreConstants = get_defined_constants( true );
			$coreConstants = $coreConstants['Core'];
			$userErrorTypes = array();
			foreach ( $coreConstants as $k => $v ) {
				if( substr( $k, 0, 7 ) === 'E_USER_' ) {
					$userErrorTypes[$k] = $v;
				}
			}
			unset( $coreConstants );
			self::$K += $userErrorTypes;

			foreach ( $userErrorTypes as $k => $v ) {
				if ( !isset( self::$userErrorTypeText[$v] ) ) {
					self::$userErrorTypeText[$v] = self::wordsFromIdName( substr( $k, 7 ) );
				}
			}

		} else {
			throw new ErrorException( __METHOD__ . ' is not callable' );
		}
	}

	/** */
	private static $_backtraceHasLimit;

}
// Once-only invocation to initialize static properties
Weirdo::_initStatic();

/** @}*/
