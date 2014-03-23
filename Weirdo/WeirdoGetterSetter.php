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

/**
 * This class provides getter and setter functions
 *
 * It can be used either statically or via its singleton, WeirdoGetterSetter::$self.
 *
 */
class WeirdoGetterSetter {

	/** Output an undefined message to the log. */
	private function _triggerUndefinedPropertyNotice( $name = null ) {
		if ( !( error_reporting() & E_NOTICE ) ) {
			return;
		}

		$callerFrame = Weirdo::getCallStackFrame( 2 );
		if ( $name === null ) {
			$name = $callerFrame['args']['0'];
		}
		$msg = sprintf( "Undefined property: %s->{'%s'}", get_class( $this ), $name );

		Weirdo::logCallerError( $msg, E_USER_NOTICE, 2 );
		trigger_error( $msg, E_USER_NOTICE );
	}

	/** Convert a public property name to a private property name. */
	protected function _privateNameFromPublicName( $publicName ) {
		$privateName = "_PUBLIC_$publicName";
		if ( !property_exists( $this, $privateName ) ) {
			$this->_triggerUndefinedPropertyNotice( $publicName );
			return null;
		}
		return $privateName;
	}

	/** Property setter. See http://php.net/__set for details. */
	public function __set( $name, $value ) {
		$privateName = $this->_privateNameFromPublicName( $name );
		if ( $privateName ) {
			$this->{$privateName} = $value;
		}
	}

	/** Property getter. See http://php.net/__get for details. */
	public function __get( $name ) {
		$privateName = $this->_privateNameFromPublicName( $name );
		if ( $privateName ) {
			return $this->{$privateName};
		}
	}

}

/** @}*/
