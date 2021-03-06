<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Framework\Api\Code\Generator;

use Magento\Framework\Code\Generator\DefinedClasses;
use Magento\Framework\Code\Generator\Io;
use Magento\Framework\Api\SimpleDataObjectConverter;

/**
 * Code generator for data object extensions.
 */
class ExtensionAttributesGenerator extends \Magento\Framework\Code\Generator\EntityAbstract
{
    const ENTITY_TYPE = 'extension';

    const EXTENSION_SUFFIX = 'Extension';

    /**
     * @var \Magento\Framework\Api\Config\Reader
     */
    protected $configReader;

    /**
     * @var array
     */
    protected $allCustomAttributes;

    /**
     * Initialize dependencies.
     *
     * @param \Magento\Framework\Api\Config\Reader $configReader
     * @param string|null $sourceClassName
     * @param string|null $resultClassName
     * @param Io $ioObject
     * @param \Magento\Framework\Code\Generator\CodeGeneratorInterface $classGenerator
     * @param DefinedClasses $definedClasses
     */
    public function __construct(
        \Magento\Framework\Api\Config\Reader $configReader,
        $sourceClassName = null,
        $resultClassName = null,
        Io $ioObject = null,
        \Magento\Framework\Code\Generator\CodeGeneratorInterface $classGenerator = null,
        DefinedClasses $definedClasses = null
    ) {
        $sourceClassName .= 'Interface';
        $this->configReader = $configReader;
        parent::__construct(
            $sourceClassName,
            $resultClassName,
            $ioObject,
            $classGenerator,
            $definedClasses
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function _getDefaultConstructorDefinition()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    protected function _getClassProperties()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    protected function _getClassMethods()
    {
        $methods = [];
        foreach ($this->getCustomAttributes() as $attributeName => $attributeType) {
            $propertyName = SimpleDataObjectConverter::snakeCaseToCamelCase($attributeName);
            $getterName = 'get' . ucfirst($propertyName);
            $setterName = 'set' . ucfirst($propertyName);
            $methods[] = [
                'name' => $getterName,
                'body' => "return \$this->_get('{$attributeName}');",
                'docblock' => ['tags' => [['name' => 'return', 'description' => $attributeType]]],
            ];
            $methods[] = [
                'name' => $setterName,
                'parameters' => [['name' => $propertyName]],
                'body' => "\$this->setData('{$attributeName}', \${$propertyName});" . PHP_EOL . "return \$this;",
                'docblock' => [
                    'tags' => [
                        [
                            'name' => 'param',
                            'description' => "{$attributeType} \${$propertyName}"
                        ],
                        [
                            'name' => 'return',
                            'description' => '$this'
                        ]
                    ]
                ],
            ];
        }
        return $methods;
    }

    /**
     * {@inheritdoc}
     */
    protected function _validateData()
    {
        $classNameValidationResults = $this->validateResultClassName();
        return parent::_validateData() && $classNameValidationResults;
    }

    /**
     * {@inheritdoc}
     */
    protected function _generateCode()
    {
        $this->_classGenerator->setImplementedInterfaces([$this->_getResultClassName() . 'Interface']);
        $this->_classGenerator->setExtendedClass($this->getExtendedClass());
        return parent::_generateCode();
    }

    /**
     * Get class, which should be used as a parent for generated class.
     *
     * @return string
     */
    protected function getExtendedClass()
    {
        return '\Magento\Framework\Api\AbstractSimpleObject';
    }

    /**
     * Retrieve a list of attributes associated with current source class.
     *
     * @return array
     */
    protected function getCustomAttributes()
    {
        if (!isset($this->allCustomAttributes)) {
            $this->allCustomAttributes = $this->configReader->read();
        }
        $dataInterface = ltrim($this->getSourceClassName(), '\\');
        if (isset($this->allCustomAttributes[$dataInterface])) {
            foreach ($this->allCustomAttributes[$dataInterface] as $attributeName => $attributeType) {
                if (strpos($attributeType, '\\') !== false) {
                    /** Add preceding slash to class names, while leaving primitive types as is */
                    $attributeType = $this->_getFullyQualifiedClassName($attributeType);
                    $this->allCustomAttributes[$dataInterface][$attributeName] =
                        $this->_getFullyQualifiedClassName($attributeType);
                }
            }
            return $this->allCustomAttributes[$dataInterface];
        } else {
            return [];
        }
    }

    /**
     * Ensure that result class name corresponds to the source class name.
     *
     * @return bool
     */
    protected function validateResultClassName()
    {
        $result = true;
        $sourceClassName = $this->getSourceClassName();
        $resultClassName = $this->_getResultClassName();
        $interfaceSuffix = 'Interface';
        $expectedResultClassName = substr($sourceClassName, 0, -strlen($interfaceSuffix)) . self::EXTENSION_SUFFIX;
        if ($resultClassName !== $expectedResultClassName) {
            $this->_addError(
                'Invalid extension name [' . $resultClassName . ']. Use ' . $expectedResultClassName
            );
            $result = false;
        }
        return $result;
    }
}
