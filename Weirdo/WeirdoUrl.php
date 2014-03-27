<?php
/**
 * @addtogroup Weirdo
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

require_once __DIR__ . "/Weirdo.php";

if ( !isset( $GLOBALS['gWeirdoDebug'] ) ) {
	$GLOBALS['gWeirdoDebug'] = 0;
	//$GLOBALS['gWeirdoDebug'] = 1;
}


class WeirdoUrl {

	/** */
	const VALID_ABSOLUTE = 1;

	/** */
	const VALID_RELATIVE = 2;

	/** */
	public static $schemeAttributes = array(
		'http'   => array( 'port' =>  80, 'pathType' => '/' ),
		'https'  => array( 'port' => 443, 'pathType' => '/' ),
		'ftp'    => array( 'port' =>  21, 'pathType' => '/' ),
		'ssh'    => array( 'port' =>  22, 'pathType' => '/' ),
	);

	/** Class constructor.
	 *
	 */
	public function __construct( $urlText = null ) {
		if ( $urlText !== null ) {
			$this->setText( $urlText );
		}
	}

	public function setText( $text ) {
		$this->_reset();
		if ( !( is_array( $text ) || is_object( $text ) ) ) {
			$this->_text = "$text";
		} else {
			if ( $text !== null ) {
				trigger_error(
					sprintf( '%s: invalid parameter', __METHOD__ ),
					E_USER_WARNING
				);
			}
		}
	}

	public function getText() {
		if ( ( $this->_text === null ) ) {
			if ( $this->_parsed === null ) {
				trigger_error(
					sprintf( '%s: reference to uninitialized value', __METHOD__ ),
					E_USER_WARNING
				);
			}
			$this->_text = self::unparse( $this->_parsed );
		}
		return $this->_text;
	}

	public function __toString() {
		return $this->getText();
	}

	public function setParsed( $parsed ) {
		$this->_reset();
		if ( is_array( $parsed ) ) {
			$this->_parsed = $parsed;
		} else {
			if ( $parsed !== null ) {
				trigger_error(
					sprintf( '%s: invalid parameter', __METHOD__ ),
					E_USER_WARNING
				);
			}
		}
	}

	public function getParsed() {
		if ( $this->_parsed === null ) {
			if ( $this->_text === null ) {
				trigger_error(
					sprintf( '%s: reference to uninitialized value', __METHOD__ ),
					E_USER_WARNING
				);
			}
			$this->_parsed = self::parse( $this->_text );
		}
		return $this->_parsed;
	}

	public function getValidity() {
		if ( $this->_validity === null ) {
			$this->_validity = self::evaluateValidity( $this->getParsed() );
		}
		return $this->_validity;
	}

	public function getAuthority() {
		if ( $this->_authority === null ) {
			$this->_authority = self::extractAuthority( $this->getParsed() );
		}
		return $this->_authority;
	}

	public function hasSameAuthority( $urlOrParts ) {
		if ( is_a( $urlOrParts, __CLASS__ ) ) {
			$urlOrParts = $urlOrParts->getParsed();
		}
		return self::haveSameAuthority( $this->getParsed(), $urlOrParts );
	}

	public function buildMerged( $baseUrlOrParts ) {
		if ( is_a( $baseUrlOrParts, __CLASS__ ) ) {
			$baseUrlOrParts = $baseUrlOrParts->getParsed();
		}
		return self::mergeUrls( $this->getParsed(), $baseUrlOrParts );
	}

	private static function __setBaseUrl( $baseUrlOrParts ) {
		if ( is_string( $baseUrlOrParts ) ) {
			$baseUrlOrParts = self::parse( $baseUrlOrParts );
		}
		if ( !$baseUrlOrParts ) {
			throw new ErrorException(
				sprintf( '%s: Invalid parameter', __METHOD__ )
			);
		}
		$this->baseUrlParts = $baseUrlOrParts;
	}

	/**
	 * Assemble URL parts into an authority component.
	 *
	 * This puts together what parse() took apart. cf. RFC 3986.
	 */
	public static function extractAuthority( $urlOrParts ) {
		if ( is_string( $urlOrParts ) ) {
			$urlOrParts = Weirdo::parse( $urlOrParts );
		}
		if ( !$urlOrParts ) {
			return false;
		}

		if ( !self::hasAuthority( $urlOrParts ) ) {
			return null;
		}
		if ( !isset( $urlOrParts['host'] ) ) {
			return null;
		}
		$authority = '';

		if ( isset( $urlOrParts['user'] ) ) {
			// user
			$authority .= $urlOrParts['user'];

			// password
			if ( isset( $urlOrParts['pass'] ) ) {
				// output the password separator/prefix and the password
				$authority .= ":{$urlOrParts['pass']}";
			}
			// user:password separator/suffix
			$authority .= '@';
		}

		// host
		$authority .= strtolower( $urlOrParts['host'] );

		// port
		if ( isset( $urlOrParts['port'] ) ) {
			// what's this scheme's default port?
			$scheme = isset( $urlOrParts['scheme'] ) ? $urlOrParts['scheme'] : null;
			$defaultPort = null;
			// fetch the default port for the given scheme
			if ( isset( self::$schemeAttributes[$scheme] ) && isset( self::$schemeAttributes[$scheme]['port'] ) ) {
				$defaultPort = self::$schemeAttributes[$scheme]['port'];
			}
			// default port specified?
			if ( ( (int)$urlOrParts['port'] ) !== $defaultPort ) {
				// output the port separator/prefix and the port
				$authority .= ':' . (int)$urlOrParts['port'];
			}
		}

		return $authority;
	}

	/**
	 * Assemble URL into a string from its component parts.
	 *
	 * This puts together what parse() took apart. cf. RFC 3986.
	 */
	public static function unparse( $urlParts ) {
		if ( is_a( $urlParts, __CLASS__ ) ) {
			$urlParts = $urlParts->getParsed();
		}
		$url = '';
		$defaultPort = null;

		// scheme (may be empty)
		if ( isset( $urlParts['scheme'] ) ) {
			$scheme = strtolower( $urlParts['scheme'] );
			// output the scheme and the scheme separator/suffix
			$url .= "$scheme:";

		}

		// authority
		$authority = self::extractAuthority( $urlParts, $defaultPort );
		if ( $authority !== null ) {
			// output the authority separator/prefix and the authority
			$url .= "//$authority";
		}

		// path
		if ( isset( $urlParts['path'] ) ) {
			$url .= $urlParts['path'];
		}

		// query
		if ( isset( $urlParts['query'] ) ) {
			// output the query separator/prefix and the query
			$url .= "?{$urlParts['query']}";
		}

		// fragment
		if ( isset( $urlParts['fragment'] ) ) {
			// output the fragment separator/prefix and the fragment
			$url .= "#{$urlParts['fragment']}";
		}

		return $url;
	}

	/**
	 * Parse a URL per RFC 3986 into its component parts.
	 *
	 * Unlike parse_url(), we don't require a path.
	 *
	 */
	public static function parse( $url ) {
		// start with the normal PHP parse_url, but suppress its warning messages
		set_error_handler( function ( $n, $s ) { return true; }, -1 );
		$urlParts = parse_url( $url );
		restore_error_handler();
		// bail if "seriously malformed"
		if ( !$urlParts ) {
			return false;
		}

		// we don't require a scheme
		if ( !isset( $urlParts['scheme'] ) ) {
			// we don't require an authority
			if ( substr( $url, 0, 2 ) === '//' ) {
				// If there's an authority without a scheme, add a scheme so that parse_url()
				// will correctly recognize the authority.
				set_error_handler( function ( $n, $s ) { return true; }, -1 );
				$urlParts = parse_url( "http:$url" );
				restore_error_handler();
			  // bail if PHP parse_url still can't parse it
			  if ( !$urlParts ) {
					return false;
				}
			}
			$urlParts['scheme'] = null;
		} else {
			// we must always return lower case of scheme
			// cf. RFC 3986, section 6.2.2.1., "Case Normalization"
			$urlParts['scheme'] = strtolower( $urlParts['scheme'] );
		}

		if ( isset( $urlParts['host'] ) ) {
			// we must always return lower case of host
			// cf. RFC 3986, section 6.2.2.1., "Case Normalization"
			$urlParts['host'] = strtolower( $urlParts['host'] );
		} elseif ( $urlParts['scheme']
							&& ( $urlParts['scheme'] === 'file' )
							&& isset( $urlParts['path'] )
							&& ( substr( $urlParts['path'], 0, 1 ) !== '/' ) ) {
			// Restore the leading '/' from the path if parse_url removed it,
			// believing that it should start with a Microsoft drive letter
			echo "******{$urlParts['path']}\n";
			$urlParts['path'] = "/{$urlParts['path']}";
		}

		// remove dot segments from path
		if ( isset( $urlParts['path'] ) ) {
			$urlParts['path'] = self::removeDotSegments( $urlParts['path'] );
		}

		// remove our scheme placeholder if there is no scheme in the URL
		if ( $urlParts['scheme'] === null ) {
			unset( $urlParts['scheme'] );
		}

		return $urlParts;
	}

	/**
	 * Remove dot segments from a URL path.
	 *
	 * If $eatRelativeDoubleDots is false, preserve leading '..' segments in relative paths.
	 * allowing merges with absolute paths in the way that browsers merge relative href
	 * attributes with a base URL.
	 *
	 * If $eatRelativeDoubleDots is true, this function behaves like the algorithm described
	 * in RFC 3986, which discards leading '..' segments in relative paths.
	 *
	 */
	public static function removeDotSegments( $urlPath, $eatRelativeDoubleDots = false ) {
		if ( !strlen( $urlPath ) ) {
			return $urlPath;  // degenerate case
		}
$GLOBALS['gWeirdoDebug'] && printf("%4u start=\"%s\"\n", __LINE__, $urlPath );
		$addTail = false;
		$input = array_reverse( explode( '/', $urlPath ) );
		if ( $urlPath[0] === '/' ) {
			$absPrefix = '/';
			$eatDoubleDots = true;
			array_pop( $input );
		} else {
			$eatDoubleDots = $eatRelativeDoubleDots;
			$absPrefix = '';
		}
		if ( count( $input ) ) {
			if ( $input[ count( $input ) - 1] === '' ) {
				$input[ count( $input ) - 1] = '.';
			}
		} else {
			$input[] = '.';
		}

		$output = array();
		while( count( $input ) ) {
			$segment = array_pop( $input );
			$lastSegment = count( $input ) == 0;
$GLOBALS['gWeirdoDebug'] && printf( "%4u ** apply \"%s\" to (\"%s\")\n", __LINE__, $segment, implode( '","', $output ) );
			if ( $segment === '' ) {
				$segment = '.';
			}
			if ( $segment === '..' ) {
$GLOBALS['gWeirdoDebug'] && printf( "%4u POP??\n", __LINE__ );
				$topOut = count( $output )
					? $output[count( $output ) - 1]
					: null
					;
				if ( $topOut == '' ) {
					$topOut = '.';
				}
$GLOBALS['gWeirdoDebug'] && printf( "%4u \$topOut=\"$topOut\"\n", __LINE__ );
				if ( $eatDoubleDots || ( ( $topOut !== '.' ) && ( $topOut !== '..' ) ) ) {
$GLOBALS['gWeirdoDebug'] && printf( "%4u POP??\n", __LINE__ );
					if ( ( count( $output ) != 1 ) || ( ( $topOut !== '.' ) && ( $topOut !== '..' ) ) ) {
						$addTail = $addTail || $lastSegment;
						$topOut = array_pop( $output );
$GLOBALS['gWeirdoDebug'] && printf( "%4u POP! %u \"%s\"\n", __LINE__, count( $input ), $topOut );
						if ( count( $output ) == 0 ) {
							$output[] = '.';
						}
					}
				} elseif ( $topOut === '.' ) {
					//$addTail = $addTail || $lastSegment;
					$output[max( 0, count( $output ) - 1 )] = '..';
				} else {
					$output[] = '..';
				}
			} elseif ( $segment !== '.' ) {
				//$addTail = $addTail || $lastSegment;
				if ( ( count( $output ) == 1 ) && ( $output[0] === '.' ) ) {
				  $output[0] = $segment;
				} else {
					$output[] = $segment;
				}
			} elseif ( ( count( $output ) == 0 ) || ( $output[count( $output ) - 1] !== '.' ) ) {
				$addTail = $addTail || $lastSegment;
				if ( count( $output ) == 0 ) {
				  $output[] = '.';
				}
			}
$GLOBALS['gWeirdoDebug'] && printf( "%4u ** produces (\"%s\")\n", __LINE__, implode( '","', $output ) );
$GLOBALS['gWeirdoDebug'] && printf( "%4u   ** keys: (\"%s\")\n", __LINE__, implode( '","', array_keys($output) ) );
		}
		while ( ( count( $output ) > 0 ) && ( $output[count( $output ) - 1] === '.' ) ) {
			if (0|| $absPrefix || ( count( $output ) > 1 ) ) {
$GLOBALS['gWeirdoDebug'] && printf( "%4u POP-A-DOT!\n", __LINE__ );
				$topOut = array_pop( $output ); //$output[count( $output ) - 1] = '';
$GLOBALS['gWeirdoDebug'] && printf( "%4u POP! \"%s\"\n", __LINE__, $topOut );
				$addTail = true;
			} else {
				$addTail = $output[count( $output ) - 1] !== '.' ;
$GLOBALS['gWeirdoDebug'] && printf( "%4u LAST-IS-DOT!\n", __LINE__ );
				break;
			}
		}
		if ( $absPrefix && ( count( $output ) == 0 ) ) {
			$addTail = false;
		}
		$result = $absPrefix . implode( '/', $output ) . ( $addTail ? '/' : '' );
$GLOBALS['gWeirdoDebug'] && printf( "%4u result=\"%s\"\n", __LINE__, $result);
		return $result;
	}

	/**
	 * Complete a possibly relative or even absent path from a base path.
	 */
	public static function mergePaths( $path, $basePath = null ) {

		// use the base path if the path is relative
		if ( substr( $path, 0, 1 ) !== '/' ) {
			if ( $basePath === null ) {
				$basePath = isset( self::$baseUrlParts['path'] ) ? self::$baseUrlParts['path'] : '';
			}
			/*///
			// If the base path is empty, use the root
			if ( $basePath == '' ) {
				$basePath = '/';
			}
			if ( substr( $basePath, 0, 1 ) !== '/' ) {
				// the base path must be absolute
				return null;
			}
			//*///
			// if the path is absent, replace it with the base path
			if ( ( $path === null ) || ( $path === '' ) ) {
				$path = $basePath;
			} else {
				// merge relative path with base path
				$lastSlash = strrpos( $basePath, '/' );
				if ( $lastSlash !== false ) {
					$path = substr( $basePath, 0, $lastSlash + 1 ) . $path;
				}
			}
		}

		return self::removeDotSegments( $path );
	}

	public static function hasAuthority( $urlOrParts ) {
		if ( is_string( $urlOrParts ) ) {
			$urlOrParts = Weirdo::parse( $urlOrParts );
		}
		if ( !$urlOrParts ) {
			return false;
		}
		return
				 isset( $urlOrParts['host'] )
			|| isset( $urlOrParts['pass'] )
			|| isset( $urlOrParts['user'] )
			|| isset( $urlOrParts['port'] )
		;
	}

	/**
	 * A valid authority must have a host. If it has a password, then it must also have a user
	 */
	public static function hasValidAuthority( $urlOrParts ) {
		if ( is_string( $urlOrParts ) ) {
			$urlOrParts = Weirdo::parse( $urlOrParts );
		}
		if ( !$urlOrParts ) {
			return false;
		}
		return
				 isset( $urlOrParts['host'] )
			&& ( ( !isset( $urlOrParts['pass'] ) ) || ( isset( $urlOrParts['user'] ) ) );
	}

	public static function haveSameAuthority( $urlOrParts1, $urlOrParts2 ) {
		$urlOrParts1 = is_string( $urlOrParts1 ) ? Weirdo::parse( $urlOrParts1 ) : $urlOrParts1;
		if ( !$urlOrParts1 ) {
			return false;
		}
		$urlOrParts2 = is_string( $urlOrParts2 ) ? Weirdo::parse( $urlOrParts2 ) : $urlOrParts2;
		if ( !$urlOrParts2 ) {
			return false;
		}
		foreach( array( 'user', 'pass', 'host', 'port' ) as $part ) {
			// both are defined or both are undefined
			if ( isset( $urlOrParts1[$part] ) !== isset( $urlOrParts2[$part] ) ) {
				return false;
			}
			// if defined, they have identical values
			if ( isset( $urlOrParts1[$part] ) && ( $urlOrParts1[$part] !== $urlOrParts2[$part] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get fully qualified URL parts from the given (possibly relative) URL.
	 */
	public static function mergeUrls( $urlOrParts, $baseUrlOrParts ) {
$GLOBALS['gWeirdoDebug'] && printf( "%4u %s url=\"%s\"\n", __LINE__, __METHOD__, $urlOrParts);
		if ( is_string( $urlOrParts ) ) {
			$urlOrParts = WeirdoUrl::parse( $urlOrParts );
		}
		if ( !$urlOrParts ) {
			return false;
		}

		if ( is_string( $baseUrlOrParts ) ) {
			$baseUrlOrParts = WeirdoUrl::parse( $baseUrlOrParts );
		}
		if ( !$baseUrlOrParts ) {
			return false;
		}

		// Start by merging the schemes (compatibility mode)
		if ( ( !isset( $urlOrParts['scheme'] ) ) || ( $urlOrParts['scheme'] === $baseUrlOrParts['scheme'] ) ) {
			$urlOrParts['scheme'] = $baseUrlOrParts['scheme'];

			// merge the authorities
			if ( ( !self::hasAuthority( $urlOrParts ) ) || ( self::haveSameAuthority( $urlOrParts, $baseUrlOrParts ) ) ) {
				foreach( array( 'user', 'pass', 'host', 'port' ) as $part ) {
					if ( isset( $baseUrlOrParts[$part] ) ) {
						$urlOrParts[$part] = $baseUrlOrParts[$part];
					}
				}

				// merge the paths
				if ( ( !isset( $urlOrParts['path'] ) ) || ( substr( $urlOrParts['path'], 0, 1 ) !== '/' ) ) {
					$path = isset( $urlOrParts['path'] ) ? $urlOrParts['path'] : null;
					if ( ( $path === null ) || ( $path === '' ) ) {
						if ( isset( $baseUrlOrParts['query'] ) && !isset( $urlOrParts['query'] ) ) {
							$urlOrParts['query'] = $baseUrlOrParts['query'];
						}
					}
					$urlOrParts['path'] = self::mergePaths(
						$path,
						isset( $baseUrlOrParts['path'] ) ? $baseUrlOrParts['path'] : '/'
					);
				}
			}
		}

		// Note: we ignore the base fragment

		return $urlOrParts;
	}

	public static function getSchemePathType( $scheme ) {
		return ( isset( self::$schemeAttributes[$scheme] )
					&& isset( self::$schemeAttributes[$scheme]['pathType'] ) )
			? self::$schemeAttributes[$scheme]['pathType']
			: null;
	}

	/**
	 * Indicate if the URL is, at the very least, valid.
	 *
	 * Just because we can parse a URL doesn't mean that it's valid or useful. This
	 * function goes one step further after parsing to determine if the URL is either
	 * a valid relative or absolute URL.
	 *
	 */
	public static function evaluateValidity( $urlOrParts ) {
		if ( $urlOrParts === null ) {
			$urlOrParts = self::parse( $url );
		}
		if ( !$urlOrParts ) {
			// not valid if we can't parse it
			return false;
		}

		$scheme = isset( $urlOrParts['scheme'] ) ? $urlOrParts['scheme'] : null;
		$hasAuthority = self::hasAuthority( $urlOrParts );
		$path = isset( $urlOrParts['path'] ) ? $urlOrParts['path'] : null;

		if ( $hasAuthority && !self::hasValidAuthority( $urlOrParts ) ) {
			// it has some authority subcomponents, but not a valid set to make the authority valid
			return false;
		}

		// a valid absolute URL must have a scheme, an authority and a path
		if ( $scheme && $hasAuthority && $path ) {
			return self::VALID_ABSOLUTE;
		}

		return VALID_RELATIVE;
	}

	private function _reset() {
		$this->_text = null;
		$this->_parsed = null;
		$this->_validity = null;
		$this->_authority = null;
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
		throw new ErrorException( 'Error: Attempt to invoke private method ' . __METHOD__ . '().' );
		if ( !isset( self::$_staticInitComplete ) ) {
		}
		else {
			throw new ErrorException( 'Error: Attempt to invoke private method ' . __METHOD__ . '().' );
		}
	}

	private $_text;

	private $_parsed;

	private $_validity;

	private $_authority;

	private $_baseUrl;

}
// Once-only static initialization
//WeirdoUrl::_initStatic();

/** @}*/
