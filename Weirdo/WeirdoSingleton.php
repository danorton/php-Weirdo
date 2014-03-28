<?php
/**
 * @addtogroup Weirdo
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

/**
 * This abstract class provides singleton capability.
 *
 * The only means of accessing the singleton is via the singleton() method.
 *
 */
abstract class WeirdoSingleton {

	/**
	 * Constructor for class's singleton object.
	 *
	 * Throws ErrorException if singleton already created.
	 */
	public function __construct() {
		if ( self::$_self || !self::$_singleton ) {
      $msg = 'Invalid attempt to instantiate static/singleton class ' . get_class($this) . '.';
      $phpVersion = (int) vsprintf( '%u%02u%02u', explode( '.', phpversion() ) );
      if ( $phpVersion >= 50100 ) {
        throw new ErrorException( $msg );
      } else {
        trigger_error( $msg, E_USER_ERROR );
      }
      // will never reach here
      exit(1);
		}
	}

	/**
	 * Get class's singleton object.
	 *
	 * @returns     WeirdoSingleton instance.
	 */
	public static function singleton() {
		if ( !self::$_self ) {
			self::$_singleton = true;
			self::$_self = new self();
		}
		return self::$_self;
	}

	/** Reference to singleton instance. */
	private static $_self;

	/** Flag set when singleton() invoked. */
	private static $_singleton;

}

/** @}*/
