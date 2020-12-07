<?php
namespace Neos\Flow\Core\Migrations;

/*
 * This file is part of the Neos.Flow package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

/**
 * Adjust code to deprecation of ComponentContext/ComponentParameters
 *
 * - remove "use Neos\Flow\Http\Component\SetHeaderComponent;" and "use Neos\Flow\Http\Component\ReplaceHttpResponseComponent;"
 * - use "->setHttpHeader('Foo', 'bar')" instead of "->setComponentParameter(SetHeaderComponent::class, 'Foo', 'bar')"
 * - use "->replaceHttpResponse($response)" instead of "->setComponentParameter(ReplaceHttpResponseComponent::class, ReplaceHttpResponseComponent::PARAMETER_RESPONSE, $response)"
 * - use "->getHttpRequest()" instead of "->getComponentContext()->getHttpRequest()"
 * - use "->setHttpRequest(...)" instead of "->getComponentContext()->replaceHttpRequest(...)"
 */
class Version20201207104500 extends AbstractMigration
{
    /**
     * @return string
     */
    public function getIdentifier()
    {
        return 'Neos.Flow-20201207104500';
    }

    /**
     * @return void
     */
    public function up()
    {
        $this->searchAndReplace('use Neos\Flow\Http\Component\SetHeaderComponent;', '', ['php']);
        $this->searchAndReplaceRegex('/->setComponentParameter\(\\?(:?[A-Za-z]\\)*SetHeaderComponent::class, (.*)\);/', '->setHttpHeader($1);', ['php']);
        $this->searchAndReplace('use Neos\Flow\Http\Component\ReplaceHttpResponseComponent;', '', ['php']);
        $this->searchAndReplaceRegex('/->setComponentParameter\(\\?(:?[A-Za-z]\\)*ReplaceHttpResponseComponent::class, \\?(:?[A-Za-z]\\)*ReplaceHttpResponseComponent::PARAMETER_RESPONSE, (.*)\);/', '->replaceHttpResponse($1);', ['php']);
        $this->searchAndReplace('->getComponentContext()->getHttpRequest()', '->getHttpRequest()', ['php']);
        $this->searchAndReplace('->getComponentContext()->replaceHttpRequest(', '->setHttpRequest(', ['php']);
    }
}
