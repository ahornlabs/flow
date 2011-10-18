<?php
namespace TYPO3\FLOW3\Package\MetaData;

/*                                                                        *
 * This script belongs to the FLOW3 framework.                            *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU Lesser General Public License, either version 3   *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */

use TYPO3\FLOW3\Annotations as FLOW3;

/**
 * Package person party meta model
 *
 * @FLOW3\Scope("prototype")
 */
class Person extends \TYPO3\FLOW3\Package\MetaData\AbstractParty {

	/**
	 * Company of the person
	 *
	 * @var string
	 */
	protected $company;

	/**
	 * Repository user name of the person
	 *
	 * @var string
	 */
	protected $repositoryUserName;

	/**
	 * Meta data person model constructor
	 *
	 * @param string $role
	 * @param string $name
	 * @param string $email
	 * @param string $website
	 * @param string $company
	 * @param string $repositoryUserName
	 */
	public function __construct($role, $name, $email = NULL, $website = NULL, $company = NULL, $repositoryUserName = NULL) {
		parent::__construct($role, $name, $email, $website);

		$this->company = $company;
		$this->repositoryUserName = $repositoryUserName;
	}

	/**
	 * @return string The company of the person
	 */
	public function getCompany() {
		return $this->company;
	}

	/**
	 * @return string The repository username
	 */
	public function getRepositoryUserName() {
		return $this->repositoryUserName;
	}

	/**
	 * @return string Party type "person"
	 */
	public function getPartyType() {
		return \TYPO3\FLOW3\Package\MetaDataInterface::PARTY_TYPE_PERSON;
	}
}
?>