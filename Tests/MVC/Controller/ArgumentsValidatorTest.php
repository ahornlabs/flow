<?php
declare(ENCODING = 'utf-8');
namespace F3\FLOW3\MVC\Controller;

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
 * @subpackage MVC
 * @version $Id$
 */

/**
 * Testcase for the Controller Arguments Validator
 *
 * @package FLOW3
 * @subpackage MVC
 * @version $Id$
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class ArgumentsValidatorTest extends \F3\Testing\BaseTestCase {

	/**
	 * @test
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function isValidReturnsFALSEIfAtLeastOneArgumentIsInvalid() {
		$mockArgument1 = $this->getMock('F3\FLOW3\MVC\Controller\Argument', array(), array(), '', FALSE);
		$mockArgument1->expects($this->any())->method('getName')->will($this->returnValue('foo'));

		$mockArgument2 = $this->getMock('F3\FLOW3\MVC\Controller\Argument', array(), array(), '', FALSE);
		$mockArgument2->expects($this->any())->method('getName')->will($this->returnValue('bar'));

		$arguments = new \F3\FLOW3\MVC\Controller\Arguments($this->getMock('F3\FLOW3\Object\FactoryInterface'));
		$arguments->addArgument($mockArgument1);
		$arguments->addArgument($mockArgument2);

		$validator = $this->getMock('F3\FLOW3\MVC\Controller\ArgumentsValidator', array('isPropertyValid'), array(), '', FALSE);
		$validator->expects($this->at(0))->method('isPropertyValid')->with($arguments, 'foo')->will($this->returnValue(FALSE));

		$this->assertFalse($validator->isValid($arguments));
	}

	/**
	 * @test
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function isValidReturnsTRUEIfAllArgumentsAreValid() {
		$mockArgument1 = $this->getMock('F3\FLOW3\MVC\Controller\Argument', array(), array(), '', FALSE);
		$mockArgument1->expects($this->any())->method('getName')->will($this->returnValue('foo'));

		$mockArgument2 = $this->getMock('F3\FLOW3\MVC\Controller\Argument', array(), array(), '', FALSE);
		$mockArgument2->expects($this->any())->method('getName')->will($this->returnValue('bar'));

		$arguments = new \F3\FLOW3\MVC\Controller\Arguments($this->getMock('F3\FLOW3\Object\FactoryInterface'));
		$arguments->addArgument($mockArgument1);
		$arguments->addArgument($mockArgument2);

		$validator = $this->getMock('F3\FLOW3\MVC\Controller\ArgumentsValidator', array('isPropertyValid'), array(), '', FALSE);
		$validator->expects($this->at(0))->method('isPropertyValid')->with($arguments, 'foo')->will($this->returnValue(TRUE));
		$validator->expects($this->at(1))->method('isPropertyValid')->with($arguments, 'bar')->will($this->returnValue(TRUE));

		$this->assertTrue($validator->isValid($arguments));
	}

	/**
	 * @test
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function canValidateIsOnlyTrueForArgumentsObjects() {
		$validator = new \F3\FLOW3\MVC\Controller\ArgumentsValidator();

		$this->assertTrue($validator->canValidate($this->getMock('F3\FLOW3\MVC\Controller\Arguments', array(), array(), '', FALSE)));
		$this->assertFalse($validator->canValidate(new \stdClass));
		$this->assertFalse($validator->canValidate('foo'));
		$this->assertFalse($validator->canValidate(42));
	}

	/**
	 * @test
	 * @expectedException \InvalidArgumentException
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function isPropertyValidOnlyAcceptsArgumentsObjects() {
		$validator = new \F3\FLOW3\MVC\Controller\ArgumentsValidator();
		$validator->isPropertyValid(new \stdClass, 'foo');
	}

	/**
	 * @test
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function isPropertyValidChecksValidatorConjunctionDefinedInAnArgument() {
		$mockValidatorChain = $this->getMock('F3\FLOW3\Validation\Validator\ValidatorInterface');

		$mockArgument = $this->getMock('F3\FLOW3\MVC\Controller\Argument', array(), array(), '', FALSE);
		$mockArgument->expects($this->any())->method('getName')->will($this->returnValue('foo'));
		$mockArgument->expects($this->any())->method('getValidator')->will($this->returnValue($mockValidatorChain));
		$mockArgument->expects($this->any())->method('getDataType')->will($this->returnValue('FooDataType'));
		$mockArgument->expects($this->any())->method('getValue')->will($this->returnValue('fooValue'));
		$mockArgument->expects($this->any())->method('isRequired')->will($this->returnValue(TRUE));

		$arguments = new \F3\FLOW3\MVC\Controller\Arguments($this->getMock('F3\FLOW3\Object\FactoryInterface'));
		$arguments->addArgument($mockArgument);

		$validator = new \F3\FLOW3\MVC\Controller\ArgumentsValidator();

		$mockValidatorChain->expects($this->at(0))->method('isValid')->with('fooValue')->will($this->returnValue(TRUE));
		$mockValidatorChain->expects($this->at(1))->method('isValid')->with('fooValue')->will($this->returnValue(FALSE));

		$this->assertTrue($validator->isPropertyValid($arguments, 'foo'));
		$this->assertFalse($validator->isPropertyValid($arguments, 'foo'));
	}

	/**
	 * @test
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function isPropertyValidReturnsTrueIfTheArgumentHasTheDefaultValueAndIsNotRequired() {
		$mockValidatorChain = $this->getMock('F3\FLOW3\Validation\Validator\ValidatorInterface');

		$mockArgument = $this->getMock('F3\FLOW3\MVC\Controller\Argument', array(), array(), '', FALSE);
		$mockArgument->expects($this->any())->method('getName')->will($this->returnValue('foo'));
		$mockArgument->expects($this->any())->method('getValidator')->will($this->returnValue($mockValidatorChain));
		$mockArgument->expects($this->any())->method('getDataType')->will($this->returnValue('FooDataType'));
		$mockArgument->expects($this->any())->method('getDefaultValue')->will($this->returnValue('defaultValue'));
		$mockArgument->expects($this->any())->method('getValue')->will($this->returnValue('defaultValue'));
		$mockArgument->expects($this->any())->method('isRequired')->will($this->returnValue(FALSE));

		$arguments = new \F3\FLOW3\MVC\Controller\Arguments($this->getMock('F3\FLOW3\Object\FactoryInterface'));
		$arguments->addArgument($mockArgument);

		$validator = new \F3\FLOW3\MVC\Controller\ArgumentsValidator();

		$mockValidatorChain->expects($this->never())->method('isValid');

		$this->assertTrue($validator->isPropertyValid($arguments, 'foo'));
	}
}
?>