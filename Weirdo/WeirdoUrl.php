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

$GLOBALS['bugbug'] = 0;
//$GLOBALS['bugbug'] = 1;
require_once __DIR__ . "/Weirdo.php";


class WeirdoUrl {

	/** */
	const URL_SCHEME_HTTP = 'http';

	/** */
	const URL_SCHEME_HTTPS = 'https';

	/** */
	const URL_SCHEME_FOLLOW = '//';

	/** */
	public $baseUrl;

	/** */
	public $baseUrlParts;

	/** */
	public static $schemeAttributes = array(
		'http'   => array( 'port' =>  80, 'pathType' => '/' ),
		'https'  => array( 'port' => 443, 'pathType' => '/' ),
		'ftp'    => array( 'port' =>  21, 'pathType' => '/' ),
	);

	/** Class constructor.
	 *
	 */
	public function __construct( $baseUrl, $baseUrlParts = null ) {
		$this->setBaseUrl( $baseUrl, $baseUrlParts );
	}

	public function setBaseUrl( $baseUrl, $baseUrlParts = null ) {
		if ( $baseUrlParts === null ) {
			$baseUrlParts = self::parse( $baseUrl );
		}
		if ( !$baseUrlParts ) {
			throw new ErrorException(
				sprintf( '%s: Invalid parameter', __METHOD__ )
			);
		}
		$this->baseUrl = $baseUrl;
		$this->baseUrlParts = $baseUrlParts;
	}

	public static function isLocal( $url ) {
	}

	/**
	 * Assemble URL parts into an authority component.
	 *
	 * This puts together what parse() took apart. cf. RFC 3986.
	 */
	public static function authorityFromParts( $urlParts, $defaultPort = null ) {
		if ( !self::hasAuthorityInParts( $urlParts ) ) {
			return null;
		}
		if ( !isset( $urlParts['host'] ) ) {
			return null;
		}
		$authority = '';

		if ( isset( $urlParts['user'] ) ) {
			// user
			$authority .= $urlParts['user'];

			// password
			if ( isset( $urlParts['pass'] ) ) {
				// output the password separator/prefix and the password
				$authority .= ":{$urlParts['pass']}";
			}
			// user:password separator/suffix
			$authority .= '@';
		}

		// host
		$authority .= strtolower( $urlParts['host'] );

		// port
		if ( isset( $urlParts['port'] ) && ( ( (int)$urlParts['port'] ) !== $defaultPort ) ) {
			// output the port separator/prefix and the port
			$authority .= ':' . (int)$urlParts['port'];
		}

		return $authority;
	}

	/**
	 * Assemble URL parts into a string.
	 *
	 * This puts together what parse() took apart. cf. RFC 3986.
	 */
	public static function urlFromParts( $urlParts ) {
		$url = '';
		$defaultPort = null;

		// scheme (may be empty)
		if ( isset( $urlParts['scheme'] ) ) {
			$scheme = strtolower( $urlParts['scheme'] );
			// output the scheme and the scheme separator/suffix
			$url .= "$scheme:";

			// note the default port for this scheme
			if ( isset( self::$schemeAttributes[$scheme] ) && isset( self::$schemeAttributes[$scheme]['port'] ) ) {
				$defaultPort = self::$schemeAttributes[$scheme]['port'];
			}
		}

		// authority
		$authority = self::authorityFromParts( $urlParts, $defaultPort );
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
	 * Parse a URL per RFC 3986.
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
	 * Remove dot segments from url path.
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
$GLOBALS['bugbug'] && printf("%4u start=\"%s\"\n", __LINE__, $urlPath );
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
$GLOBALS['bugbug'] && printf( "%u ** apply \"%s\" to (\"%s\")\n", __LINE__, $segment, implode( '","', $output ) );
			if ( $segment === '' ) {
				$segment = '.';
			}
			if ( $segment === '..' ) {
$GLOBALS['bugbug'] && printf( "%u POP??\n", __LINE__ );
				$topOut = count( $output )
					? $output[count( $output ) - 1]
					: null
					;
				if ( $topOut == '' ) {
					$topOut = '.';
				}
$GLOBALS['bugbug'] && printf( "%u \$topOut=\"$topOut\"\n", __LINE__ );
				if ( $eatDoubleDots || ( ( $topOut !== '.' ) && ( $topOut !== '..' ) ) ) {
$GLOBALS['bugbug'] && printf( "%u POP??\n", __LINE__ );
					if ( ( count( $output ) != 1 ) || ( ( $topOut !== '.' ) && ( $topOut !== '..' ) ) ) {
            $addTail = $addTail || $lastSegment;
						$topOut = array_pop( $output );
$GLOBALS['bugbug'] && printf( "%u POP! %u \"%s\"\n", __LINE__, count( $input ), $topOut );
						if ( count( $output ) == 0 ) {
							$output[] = '.';
						}
					}
				} elseif ( $topOut === '.' ) {
          //$addTail = $addTail || $lastSegment;
					$output[max(0,count( $output ) - 1)] = '..';
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
$GLOBALS['bugbug'] && printf( "%u ** produces (\"%s\")\n", __LINE__, implode( '","', $output ) );
$GLOBALS['bugbug'] && printf( "%u   ** keys: (\"%s\")\n", __LINE__, implode( '","', array_keys($output) ) );
		}
		while ( ( count( $output ) > 0 ) && ( $output[count( $output ) - 1] === '.' ) ) {
			if (0|| $absPrefix || ( count( $output ) > 1 ) ) {
$GLOBALS['bugbug'] && printf( "%u POP-A-DOT!\n", __LINE__ );
				$topOut = array_pop( $output ); //$output[count( $output ) - 1] = '';
$GLOBALS['bugbug'] && printf( "%u POP! \"%s\"\n", __LINE__, $topOut );
        $addTail = true;
			} else {
				$output[count( $output ) - 1] = '.';
        $addTail = true;
$GLOBALS['bugbug'] && printf( "%u ADD-A-DOT!\n", __LINE__ );
				break;
			}
		}
    if ( $absPrefix && ( count( $output ) == 0 ) ) {
      $addTail = false;
    }
		$result = $absPrefix . implode( '/', $output ) . ( $addTail ? '/' : '' );
$GLOBALS['bugbug'] && printf( "%u result=\"%s\"\n", __LINE__, $result);
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

	public static function hasAuthorityInParts( $urlParts ) {
		return
				 isset( $urlParts['user'] )
			|| isset( $urlParts['pass'] )
			|| isset( $urlParts['host'] )
			|| isset( $urlParts['port'] )
    ;
	}

	public static function haveSameAuthorityInParts( $urlParts1, $urlsParts2 ) {
		foreach( array( 'user', 'pass', 'host', 'port' ) as $part ) {
			// both are defined or both are undefined
			if ( isset( $urlParts1[$part] ) !== isset( $urlParts2[$part] ) ) {
				return false;
			}
			// if defined, they have identical values
			if ( isset( $urlParts1[$part] ) && ( $urlParts1[$part] !== $urlParts1[$part] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Get fully qualified URL parts from the given (possibly relative) URL.
	 */
	public static function mergeUrlsFromParts( $urlParts, $baseUrlParts ) {
		if ( !$urlParts ) {
			return false;
		}

		// Start by merging the schemes
		if ( ( !isset( $urlParts['scheme'] ) ) || ( $urlParts['scheme'] === $baseUrlParts['scheme'] ) ) {
			$urlParts['scheme'] = $baseUrlParts['scheme'];

			// merge the authorities
			if ( (!self::hasAuthorityInParts( $urlParts )) || ( self::haveSameAuthorityInParts( $urlParts, $baseUrlParts ) ) ) {
				foreach( array( 'user', 'pass', 'host', 'port' ) as $part ) {
					if ( isset( $baseUrlParts[$part] ) ) {
						$urlParts[$part] = $baseUrlParts[$part];
					}
				}

				// merge the paths
				if ( (!isset( $urlParts['path'] ) ) || ( substr( $urlParts['path'], 0, 1 ) !== '/' ) ) {
					$path = isset( $urlParts['path'] ) ? $urlParts['path'] : null;
					if ( ( $path === null ) || ( $path === '' ) ) {
						if ( isset( $baseUrlParts['query'] ) && !isset( $urlParts['query'] ) ) {
							$urlParts['query'] = $baseUrlParts['query'];
						}
					}
					$urlParts['path'] = self::mergePaths(
						$path,
						isset( $baseUrlParts['path'] ) ? $baseUrlParts['path'] : '/'
					);
				}
			}
		}

		// Note: we don't modify the fragment.

		return $urlParts;
	}

	public static function getSchemePathType( $scheme ) {
		return ( isset( self::$schemeAttributes[$scheme] )
					&& isset( self::$schemeAttributes[$scheme]['pathType'] ) )
			? self::$schemeAttributes[$scheme]['pathType']
			: null;
	}

	/**
	 * Indicate if the target URL is, at the very least, valid.
	 *
	 * Just because we can parse a URL doesn't mean that it's valid or useful. This
	 * function goes one step further after parsing to determine if the URL is either
	 * a valid relative or absolute URL.
	 *
	 */
	public static function isValidTargetUrl( $url, $urlParts = null ) {
		if ( $urlParts === null ) {
			$urlParts = self::parse( $url );
		}
		if ( !$urlParts ) {
			return false;
		}
		$scheme = isset( $urlParts['scheme'] ) ? $urlParts['scheme'] : null;
		$authority = self::authorityFromParts( $url, $urlParts );
		$path = isset( $urlParts['path'] ) ? $urlParts['path'] : null;

		// a valid absolute URL must have a scheme, an authority and a path
		if ( $scheme && $authority && $path ) {
			return true;
		} else {
			// if the URL has only a partial authority component, it's not a valid relative URL
			if ( $authority
						 || !( isset( $urlParts['user'] )
								|| isset( $urlParts['pass'] )
								|| isset( $urlParts['host'] )
								|| isset( $urlParts['port'] ) ) ) {
				return true;
			}
		}
		return false;
	}

	/** Recursively convert an array to an object */
	public static function objectFromArray( $array ) {
		$o = (object) null;
		foreach ( $array as $k => $v ) {
			// replace null array key with a valid object field name
			if ( $k === '' ) {
				$k = '_null_' . __FUNCTION__;
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
		if ( !isset( self::$_staticInitComplete ) ) {
		}
		else {
			throw new ErrorException( 'Error: Attempt to invoke private method ' . __METHOD__ . '().' );
		}
	}

}
// Once-only static initialization
WeirdoUrl::_initStatic();

/** @}*/
