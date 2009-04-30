<?php
declare(ENCODING = 'utf-8');
namespace F3\FLOW3\Validation\Validator;

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
 * @subpackage Tests
 * @version $Id$
 */

/**
 * Testcase for the text validator
 *
 * @package FLOW3
 * @subpackage Tests
 * @version $Id$
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 */
class TextValidatorTest extends \F3\Testing\BaseTestCase {

	/**
	 * @test
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function textValidatorReturnsTrueForASimpleString() {
		$textValidator = new \F3\FLOW3\Validation\Validator\TextValidator();
		$this->assertTrue($textValidator->isValid('this is a very simple string'));
	}

	/**
	 * @test
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function textValidatorReturnsFalseForAStringWithHTML() {
		$textValidator = $this->getMock('F3\FLOW3\Validation\Validator\TextValidator', array('addError'), array(), '', FALSE);
		$this->assertFalse($textValidator->isValid('<span style="color: #BBBBBB;">a nice text</span>'));
	}

	/**
	 * @test
	 * @author Karsten Dambekalns <karsten@typo3.org>
	 */
	public function textValidatorReturnsFalseForAStringWithPercentEncodedHTML() {
		$textValidator = $this->getMock('F3\FLOW3\Validation\Validator\TextValidator', array('addError'), array(), '', FALSE);
		$this->assertFalse($textValidator->isValid('%3cspan style="color: #BBBBBB;"%3ea nice text%3c/span%3e'));
	}

	/**
	 * @test
	 * @author Andreas Förthner <andreas.foerthner@netlogix.de>
	 */
	public function textValidatorCreatesTheCorrectErrorIfTheSubjectContainsHTMLEntities() {
		$textValidator = $this->getMock('F3\FLOW3\Validation\Validator\TextValidator', array('addError'), array(), '', FALSE);
		$textValidator->expects($this->once())->method('addError')->with('The given subject was not a valid text (contained XML tags). Got: "<span style="color: #BBBBBB;">a nice text</span>"', 1221565786);
		$textValidator->isValid('<span style="color: #BBBBBB;">a nice text</span>');
	}
}

?>