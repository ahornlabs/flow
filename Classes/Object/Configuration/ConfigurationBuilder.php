<?php
declare(ENCODING = 'utf-8');
namespace F3\FLOW3\Object\Configuration;

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
 * Object Configuration Builder which can build object configuration objects
 * from information collected by reflection combined with arrays of configuration
 * options as defined in an Objects.yaml file.
 *
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License, version 3 or later
 * @proxy disable
 */
class ConfigurationBuilder {

	/**
	 * @var \F3\FLOW3\Reflection\ReflectionService
	 */
	protected $reflectionService;

	/**
	 * @var \F3\FLOW3\Log\SystemLoggerInterface
	 */
	protected $systemLogger;

	/**
	 * @param \F3\FLOW3\Reflection\ReflectionService $reflectionService
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function injectReflectionService(\F3\FLOW3\Reflection\ReflectionService $reflectionService) {
		$this->reflectionService = $reflectionService;
	}

	/**
	 * @param \F3\FLOW3\Log\SystemLoggerInterface $systemLogger
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function injectSystemLogger(\F3\FLOW3\Log\SystemLoggerInterface $systemLogger) {
		$this->systemLogger = $systemLogger;
	}

	/**
	 * Traverses through the given class and interface names and builds a base object configuration
	 * for all of them. Then parses the provided extra configuration and merges the result
	 * into the overall configuration. Finally autowires dependencies of arguments and properties
	 * which can be resolved automatically.
	 *
	 * @param array $availableClassNames An array of available class names
	 * @param array $rawObjectconfigurationsByPackages An array of package keys and their raw (ie. unparsed) object configurations
	 * @return array<F3\FLOW3\Object\Configuration\Configuration> Object configurations
	 * @author Robert Lemke <robert@typo3.org>
	 */
	public function buildObjectConfigurations(array $availableClassNames, array $rawObjectConfigurationsByPackages) {
		$objectConfigurations = array();

		foreach ($availableClassNames as $className) {
			$objectName = $className;

			if (interface_exists($className)) {
				$className = $this->reflectionService->getDefaultImplementationClassNameForInterface($className);
				if ($className === FALSE) {
					continue;
				}
			}
			$rawObjectConfiguration = array('className' => $className);

			if ($this->reflectionService->isClassTaggedWith($className, 'scope')) {
				$rawObjectConfiguration['scope'] = implode('', $this->reflectionService->getClassTagValues($className, 'scope'));
			}
			if ($this->reflectionService->isClassTaggedWith($className, 'autowiring')) {
				$rawObjectConfiguration['autowiring'] = implode('', $this->reflectionService->getClassTagValues($className, 'autowiring'));
			}
			$objectConfigurations[$objectName] = $this->parseConfigurationArray($objectName, $rawObjectConfiguration, 'automatically registered class');
		}

		foreach ($rawObjectConfigurationsByPackages as $packageKey => $rawObjectConfigurations) {
			foreach ($rawObjectConfigurations as $objectName => $rawObjectConfiguration) {
				$objectName = str_replace('_', '\\', $objectName);
				if (!is_array($rawObjectConfiguration)) {
					throw new \F3\FLOW3\Object\Exception\InvalidObjectConfigurationException('Configuration of object "' . $objectName . '" in package "' . $packageKey. '" is not an array, please check your Objects.yaml for syntax errors.', 1295954338);
				}

				$existingObjectConfiguration = (isset($objectConfigurations[$objectName])) ? $objectConfigurations[$objectName] : NULL;
				$newObjectConfiguration = $this->parseConfigurationArray($objectName, $rawObjectConfiguration, 'configuration of package ' . $packageKey . ', definition for object "' . $objectName . '"', $existingObjectConfiguration);

				if (!isset($objectConfigurations[$objectName]) && !interface_exists($objectName, TRUE) && !class_exists($objectName, FALSE)) {
					throw new \F3\FLOW3\Object\Exception\InvalidObjectConfigurationException('Tried to configure unknown object "' . $objectName . '" in package "' . $packageKey. '". Please check your Objects.yaml.', 1184926175);
				}

				if ($objectName !== $newObjectConfiguration->getClassName() && !interface_exists($objectName, TRUE)) {
					throw new \F3\FLOW3\Object\Exception\InvalidObjectConfigurationException('Tried to set a differing class name for class "' . $objectName . '" in the object configuration of package "' . $packageKey . '". Setting "className" is only allowed for interfaces, please check your Objects.yaml."', 1295954589);
				}

				$objectConfigurations[$objectName] = $newObjectConfiguration;
			}
		}

		$this->autowireArguments($objectConfigurations);
		$this->autowireProperties($objectConfigurations);

		return $objectConfigurations;
	}

	/**
	 * Builds an object configuration object from a generic configuration container.
	 *
	 * @param string $objectName Name of the object
	 * @param array configurationArray The configuration array with options for the object configuration
	 * @param string configurationSourceHint A human readable hint on the original source of the configuration (for troubleshooting)
	 * @param \F3\FLOW3\Object\Configuration\Configuration existingObjectConfiguration If set, this object configuration object will be used instead of creating a fresh one
	 * @return \F3\FLOW3\Object\Configuration\Configuration The object configuration object
	 * @author Robert Lemke <robert@typo3.org>
	 */
	protected function parseConfigurationArray($objectName, array $rawConfigurationOptions, $configurationSourceHint = '', $existingObjectConfiguration = NULL) {
		$className = (isset($rawConfigurationOptions['className']) ? $rawConfigurationOptions['className'] : $objectName);
		$objectConfiguration = ($existingObjectConfiguration instanceof \F3\FLOW3\Object\Configuration\Configuration) ? $existingObjectConfiguration : new \F3\FLOW3\Object\Configuration\Configuration($objectName, $className);
		$objectConfiguration->setConfigurationSourceHint($configurationSourceHint);

		foreach ($rawConfigurationOptions as $optionName => $optionValue) {
			switch ($optionName) {
				case 'scope':
					$objectConfiguration->setScope($this->parseScope($optionValue));
				break;
				case 'properties':
					if (is_array($optionValue)) {
						foreach ($optionValue as $propertyName => $propertyValue) {
							if (isset($propertyValue['value'])) {
								$property = new \F3\FLOW3\Object\Configuration\ConfigurationProperty($propertyName, $propertyValue['value'], \F3\FLOW3\Object\Configuration\ConfigurationProperty::PROPERTY_TYPES_STRAIGHTVALUE);
							} elseif (isset($propertyValue['object'])) {
								$property = $this->parsePropertyOfTypeObject($propertyName, $propertyValue['object'], $configurationSourceHint);
							} elseif (isset($propertyValue['setting'])) {
								$property = new \F3\FLOW3\Object\Configuration\ConfigurationProperty($propertyName, $propertyValue['setting'], \F3\FLOW3\Object\Configuration\ConfigurationProperty::PROPERTY_TYPES_SETTING);
							} else {
								throw new \F3\FLOW3\Object\Exception\InvalidObjectConfigurationException('Invalid configuration syntax. Expecting "value", "object" or "setting" as value for property "' . $propertyName . '", instead found "' . (is_array($propertyValue) ? implode(', ', array_keys($propertyValue)) : $propertyValue) . '" (source: ' . $objectConfiguration->getConfigurationSourceHint() . ')', 1230563249);
							}
							$objectConfiguration->setProperty($property);
						}
					}
				break;
				case 'arguments':
					if (is_array($optionValue)) {
						foreach ($optionValue as $argumentName => $argumentValue) {
							if (isset($argumentValue['value'])) {
								$argument = new \F3\FLOW3\Object\Configuration\ConfigurationArgument($argumentName, $argumentValue['value'], \F3\FLOW3\Object\Configuration\ConfigurationArgument::ARGUMENT_TYPES_STRAIGHTVALUE);
							} elseif (isset($argumentValue['object'])) {
								$argument = $this->parseArgumentOfTypeObject($argumentName, $argumentValue['object'], $configurationSourceHint);
							} elseif (isset($argumentValue['setting'])) {
								$argument = new \F3\FLOW3\Object\Configuration\ConfigurationArgument($argumentName, $argumentValue['setting'], \F3\FLOW3\Object\Configuration\ConfigurationArgument::ARGUMENT_TYPES_SETTING);
							} else {
								throw new \F3\FLOW3\Object\Exception\InvalidObjectConfigurationException('Invalid configuration syntax. Expecting "value", "object" or "setting" as value for argument "' . $argumentName . '", instead found "' . (is_array($argumentValue) ? implode(', ', array_keys($argumentValue)) : $argumentValue) . '" (source: ' . $objectConfiguration->getConfigurationSourceHint() . ')', 1230563250);
							}
							$objectConfiguration->setArgument($argument);
						}
					}
				break;
				case 'className':
				case 'factoryObjectName' :
				case 'factoryMethodName' :
				case 'lifecycleInitializationMethodName':
				case 'lifecycleShutdownMethodName':
					$methodName = 'set' . ucfirst($optionName);
					$objectConfiguration->$methodName(trim($optionValue));
				break;
				case 'autowiring':
					$objectConfiguration->setAutowiring($this->parseAutowiring($optionValue));
				break;
				default:
					throw new \F3\FLOW3\Object\Exception\InvalidObjectConfigurationException('Invalid configuration option "' . $optionName . '" (source: ' . $objectConfiguration->getConfigurationSourceHint() . ')', 1167574981);
			}
		}
		return $objectConfiguration;
	}

	/**
	 * Parses the value of the option "scope"
	 *
	 * @param  string $value Value of the option
	 * @return integer The scope translated into a scope constant
	 * @throws \F3\FLOW3\Object\Exception\InvalidObjectConfigurationException if an invalid scope has been specified
	 * @author Robert Lemke <robert@typo3.org>
	 */
	protected function parseScope($value) {
		switch ($value) {
			case 'singleton':
				return \F3\FLOW3\Object\Configuration\Configuration::SCOPE_SINGLETON;
			case 'prototype':
				return \F3\FLOW3\Object\Configuration\Configuration::SCOPE_PROTOTYPE;
			case 'session':
				return \F3\FLOW3\Object\Configuration\Configuration::SCOPE_SESSION;
			default:
				throw new \F3\FLOW3\Object\Exception\InvalidObjectConfigurationException('Invalid scope', 1167574991);
		}
	}

	/**
	 * Parses the value of the option "autowiring"
	 *
	 * @param  string $value Value of the option
	 * @return boolean The autowiring option translated into a boolean
	 * @throws \F3\FLOW3\Object\Exception\InvalidObjectConfigurationException if an invalid option has been specified
	 * @author Robert Lemke <robert@typo3.org>
	 */
	static protected function parseAutowiring($value) {
		switch ($value) {
			case 'on':
			case \F3\FLOW3\Object\Configuration\Configuration::AUTOWIRING_MODE_ON:
				return \F3\FLOW3\Object\Configuration\Configuration::AUTOWIRING_MODE_ON;
			case 'off':
			case \F3\FLOW3\Object\Configuration\Configuration::AUTOWIRING_MODE_OFF:
				return \F3\FLOW3\Object\Configuration\Configuration::AUTOWIRING_MODE_OFF;
			default:
				throw new \F3\FLOW3\Object\Exception\InvalidObjectConfigurationException('Invalid autowiring declaration', 1283866757);
		}
	}

	/**
	 * Parses the configuration for properties of type OBJECT
	 *
	 * @param string $propertyName Name of the property
	 * @param mixed $objectNameOrConfiguration Value of the "object" section of the property configuration - either a string or an array
	 * @param string configurationSourceHint A human readable hint on the original source of the configuration (for troubleshooting)
	 * @return \F3\FLOW3\Object\Configuration\ConfigurationProperty A configuration property of type object
	 * @author Robert Lemke <robert@typo3.org>
	 */
	protected function parsePropertyOfTypeObject($propertyName, $objectNameOrConfiguration, $configurationSourceHint) {
		if (is_array($objectNameOrConfiguration)) {
			if (isset($objectNameOrConfiguration['name'])) {
				$objectName = $objectNameOrConfiguration['name'];
				unset($objectNameOrConfiguration['name']);
			} else {
				if (isset($objectNameOrConfiguration['factoryObjectName'])) {
					$objectName = NULL;
				} else {
					throw new \F3\FLOW3\Object\Exception\InvalidObjectConfigurationException('Object configuration for property "' . $propertyName . '" contains neither object name nor factory object name in '. $configurationSourceHint, 1297097815);
				}
			}
			$objectConfiguration = $this->parseConfigurationArray($objectName, $objectNameOrConfiguration, $configurationSourceHint . ', property "' . $propertyName .'"');
			$property = new \F3\FLOW3\Object\Configuration\ConfigurationProperty($propertyName, $objectConfiguration, \F3\FLOW3\Object\Configuration\ConfigurationProperty::PROPERTY_TYPES_OBJECT);
		} else {
			$property = new \F3\FLOW3\Object\Configuration\ConfigurationProperty($propertyName, $objectNameOrConfiguration, \F3\FLOW3\Object\Configuration\ConfigurationProperty::PROPERTY_TYPES_OBJECT);
		}
		return $property;
	}

	/**
	 * Parses the configuration for arguments of type OBJECT
	 *
	 * @param string $argumentName Name of the argument
	 * @param mixed $objectNameOrConfiguration Value of the "object" section of the argument configuration - either a string or an array
	 * @param string configurationSourceHint A human readable hint on the original source of the configuration (for troubleshooting)
	 * @return \F3\FLOW3\Object\Configuration\ConfigurationArgument A configuration argument of type object
	 * @author Robert Lemke <robert@typo3.org>
	 */
	protected function parseArgumentOfTypeObject($argumentName, $objectNameOrConfiguration, $configurationSourceHint) {
		if (is_array($objectNameOrConfiguration)) {
			$objectName = $objectNameOrConfiguration['name'];
			unset($objectNameOrConfiguration['name']);
			$objectConfiguration = $this->parseConfigurationArray($objectName, $objectNameOrConfiguration, $configurationSourceHint . ', argument "' . $argumentName .'"');
			$argument = new \F3\FLOW3\Object\Configuration\ConfigurationArgument($argumentName,  $objectConfiguration, \F3\FLOW3\Object\Configuration\ConfigurationArgument::ARGUMENT_TYPES_OBJECT);
		} else {
			$argument = new \F3\FLOW3\Object\Configuration\ConfigurationArgument($argumentName,  $objectNameOrConfiguration, \F3\FLOW3\Object\Configuration\ConfigurationArgument::ARGUMENT_TYPES_OBJECT);
		}
		return $argument;
	}

	/**
	 * If mandatory constructor arguments have not been defined yet, this function tries to autowire
	 * them if possible.
	 *
	 * @param array
	 * @return void
	 * @author Robert Lemke <robert@typo3.org>
	 */
	protected function autowireArguments(array &$objectConfigurations) {
		foreach ($objectConfigurations as $objectConfiguration) {
			$className = $objectConfiguration->getClassName();
			$arguments = $objectConfiguration->getArguments();

			if ($this->reflectionService->hasMethod($className, '__construct')) {
				foreach ($this->reflectionService->getMethodParameters($className, '__construct') as $parameterInformation) {
					$index = $parameterInformation['position'] + 1;
					if (!isset($arguments[$index])) {
						if ($parameterInformation['class'] !== NULL && isset($objectConfigurations[$parameterInformation['class']])) {
							$arguments[$index] = new \F3\FLOW3\Object\Configuration\ConfigurationArgument($index, $parameterInformation['class'], \F3\FLOW3\Object\Configuration\ConfigurationArgument::ARGUMENT_TYPES_OBJECT);
						} elseif ($parameterInformation['optional'] === TRUE) {
							$defaultValue = (isset($parameterInformation['defaultValue'])) ? $parameterInformation['defaultValue'] : NULL;
							$arguments[$index] = new \F3\FLOW3\Object\Configuration\ConfigurationArgument($index, $defaultValue, \F3\FLOW3\Object\Configuration\ConfigurationArgument::ARGUMENT_TYPES_STRAIGHTVALUE);
						} elseif ($parameterInformation['allowsNull'] === TRUE) {
							$arguments[$index] = new \F3\FLOW3\Object\Configuration\ConfigurationArgument($index, NULL, \F3\FLOW3\Object\Configuration\ConfigurationArgument::ARGUMENT_TYPES_STRAIGHTVALUE);
						}

						$methodTagsAndValues = $this->reflectionService->getMethodTagsValues($className, '__construct');
						if (isset ($arguments[$index]) && ($objectConfiguration->getAutowiring() === \F3\FLOW3\Object\Configuration\Configuration::AUTOWIRING_MODE_OFF
								|| isset($methodTagsAndValues['autowiring']) && $methodTagsAndValues['autowiring'] === array('off'))) {
							$arguments[$index]->setAutowiring(\F3\FLOW3\Object\Configuration\Configuration::AUTOWIRING_MODE_OFF);
							$arguments[$index]->set($index, NULL);
						}
					}
				}
			}
			$objectConfiguration->setArguments($arguments);
		}
	}

	/**
	 * This function tries to find yet unmatched dependencies which need to be injected via "inject*" setter methods.
	 *
	 * @param array
	 * @return void
	 * @throws \F3\FLOW3\Object\Exception\CannotBuildObjectException if a required property could not be autowired.
	 * @author Robert Lemke <robert@typo3.org>
	 */
	protected function autowireProperties(array &$objectConfigurations) {
		foreach ($objectConfigurations as $objectConfiguration) {
			$className = $objectConfiguration->getClassName();
			$properties = $objectConfiguration->getProperties();

			foreach (get_class_methods($className) as $methodName) {
				if (substr($methodName, 0, 6) === 'inject') {
					$propertyName = strtolower(substr($methodName, 6, 1)) . substr($methodName, 7);
					if ($methodName === 'injectSettings') {
						$classNameParts = explode('\\', $className);
						if (count($classNameParts) > 1) {
							$properties[$propertyName] = new \F3\FLOW3\Object\Configuration\ConfigurationProperty($propertyName, $classNameParts[1], \F3\FLOW3\Object\Configuration\ConfigurationProperty::PROPERTY_TYPES_SETTING);
						}
					} else {
						if (array_key_exists($propertyName, $properties)) {
							continue;
						}
						$methodParameters = $this->reflectionService->getMethodParameters($className, $methodName);
						if (count($methodParameters) !== 1) {
							$this->systemLogger->log(sprintf('Could not autowire property %s because %s() expects %s instead of exactly 1 parameter.', "$className::$propertyName", $methodName, (count($methodParameters) ?: 'none')), LOG_DEBUG);
							continue;
						}
						$methodParameter = array_pop($methodParameters);
						if ($methodParameter['class'] === NULL) {
							$this->systemLogger->log(sprintf('Could not autowire property %s because the method parameter in %s() contained no type hint.', "$className::$propertyName", $methodName), LOG_DEBUG);
							continue;
						}
						$properties[$propertyName] = new \F3\FLOW3\Object\Configuration\ConfigurationProperty($propertyName, $methodParameter['class'], \F3\FLOW3\Object\Configuration\ConfigurationProperty::PROPERTY_TYPES_OBJECT);
					}

					$methodTagsAndValues = $this->reflectionService->getMethodTagsValues($className, $methodName);
					if ($objectConfiguration->getAutowiring() === \F3\FLOW3\Object\Configuration\Configuration::AUTOWIRING_MODE_OFF ||
							  isset($methodTagsAndValues['autowiring']) && $methodTagsAndValues['autowiring'] === array('off')) {
						$properties[$propertyName]->setAutowiring(\F3\FLOW3\Object\Configuration\Configuration::AUTOWIRING_MODE_OFF);
						$properties[$propertyName]->set($propertyName, NULL);
					}
				}
			}

			foreach ($this->reflectionService->getPropertyNamesByTag($className, 'inject') as $propertyName) {
				if (!array_key_exists($propertyName, $properties)) {
					$objectName = trim(implode('', $this->reflectionService->getPropertyTagValues($className, $propertyName, 'var')), ' \\');
					$properties[$propertyName] =  new \F3\FLOW3\Object\Configuration\ConfigurationProperty($propertyName, $objectName, \F3\FLOW3\Object\Configuration\ConfigurationProperty::PROPERTY_TYPES_OBJECT);
				}
			}

			$objectConfiguration->setProperties($properties);
		}
	}
}
?>