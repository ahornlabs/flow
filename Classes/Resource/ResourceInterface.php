<?php
declare(ENCODING = 'utf-8');
namespace F3\FLOW3\Resource;

/*                                                                        *
 * This script belongs to the FLOW3 framework.                            *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License as published by the *
 * Free Software Foundation, either version 3 of the License, or (at your *
 * option) any later version.                                             *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser       *
 * General Public License for more details.                               *
 *                                                                        *
 * You should have received a copy of the GNU Lesser General Public       *
 * License along with the script.                                         *
 * If not, see http://www.gnu.org/licenses/lgpl.html                      *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

/**
 * @package FLOW3
 * @subpackage Resource
 * @version $Id$
 */

/**
 *
 *
 * @package FLOW3
 * @subpackage Resource
 * @version $Id$
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
interface ResourceInterface {

	/**
	 * Returns the type of source the resource originates
	 *
	 * @return string Type, e.g. file, http, ftp, ...
	 * @internal
	 */
	public function getDataSourceType();

	/**
	 * The URI representing the resource
	 *
	 * @return \F3\FLOW3\Property\DataType\URI
	 * @internal
	 */
	public function getURI();

	/**
	 * Returns the name the resource was obtained from
	 *
	 * @return string
	 * @internal
	 */
	public function getName();

	/**
	 * Returns the path the resource was obtained from
	 *
	 * @return string
	 * @internal
	 */
	public function getPath();

	/**
	 * Returns the path the resource was obtained from including file name
	 *
	 * @return string
	 * @internal
	 */
	public function getPathAndFileName();

	/**
	 * Returns the media type of the resource
	 *
	 * @return string
	 * @internal
	 */
	public function getMediaType();

	/**
	 * Returns the MIME type of the resource
	 *
	 * @return string
	 * @internal
	 */
	public function getMIMEType();


	/**
	 * Returns the content represented by the resource object
	 *
	 * @return string|binary
	 * @internal
	 */
	public function getContent();
}

?>
