<?php
/* *********************************************************************
 * This Original Work is copyright of 51 Degrees Mobile Experts Limited.
 * Copyright 2026 51 Degrees Mobile Experts Limited, Davidson House,
 * Forbury Square, Reading, Berkshire, United Kingdom RG1 3EU.
 *
 * This Original Work is licensed under the European Union Public Licence
 * (EUPL) v.1.2 and is subject to its terms as set out below.
 *
 * If a copy of the EUPL was not distributed with this file, You can obtain
 * one at https://opensource.org/licenses/EUPL-1.2.
 *
 * The 'Compatible Licences' set out in the Appendix to the EUPL (as may be
 * amended by the European Commission) shall be deemed incompatible for
 * the purposes of the Work and the provisions of the compatibility
 * clause in Article 5 of the EUPL shall not apply.
 *
 * If using the Work as, or as part of, a network application, by
 * including the attribution notice(s) required under Article 5 of the EUPL
 * in the end user terms of the application under an appropriate heading,
 * such notice(s) shall fulfill the requirements of that article.
 * ********************************************************************* */

namespace fiftyone\pipeline\core\tests;

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\PipelineBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Stands in for device detection, offering only the 'fetch' property.
 */
class FetchDeviceElement extends FlowElement
{
    public string $dataKey = 'device';

    public array $properties = [
        'fetch' => [
            'type' => 'bool'
        ]
    ];

    private $fetch;

    public function __construct($fetch)
    {
        $this->fetch = $fetch;
        parent::__construct();
    }

    public function processInternal($flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'fetch' => new AspectPropertyValue(null, $this->fetch)
        ]));
    }
}

/**
 * Every builder is expected to render the same script from the same
 * evidence, and the .NET builder is the reference. These tests pin the
 * places where this builder used to differ from it, being which evidence
 * becomes a parameter, how a parameter is written, the callback URL and the
 * choice between fetch and XMLHttpRequest.
 */
class JavaScriptBuilderParametersTests extends TestCase
{
    public function testOnlyQueryEvidenceBecomesAParameter()
    {
        $script = $this->render([
            'header.host' => 'example.com',
            'header.protocol' => 'https',
            'query.mark' => 'kept'
        ]);

        $this->assertSame(
            '{"mark":"kept"}',
            $this->parameters($script)
        );
    }

    /**
     * A key such as 'query.id.usage' keeps everything after the prefix. The
     * usage key a 51Did depends on is one of these, and cutting it at the
     * second dot sent it as 'id'.
     */
    public function testParameterKeyKeepsEverythingAfterThePrefix()
    {
        $script = $this->render([
            'header.host' => 'example.com',
            'query.id.usage' => 'personalized'
        ]);

        $this->assertSame(
            '{"id.usage":"personalized"}',
            $this->parameters($script)
        );
    }

    /**
     * The script puts the parameters into the request body as they are, so
     * they are rendered already encoded, with a space as '+' and the
     * characters '!', '*', '(' and ')' left alone, as the .NET builder does.
     */
    public function testParametersAreRenderedEncoded()
    {
        $script = $this->render([
            'header.host' => 'example.com',
            'query.mark' => 'a b&c=d!*()~',
            'query.odd key' => '1'
        ]);

        $this->assertSame(
            '{"mark":"a+b%26c%3Dd!*()%7E","odd+key":"1"}',
            $this->parameters($script)
        );
    }

    public function testNoParametersRendersAnEmptyObject()
    {
        $script = $this->render(['header.host' => 'example.com']);

        $this->assertSame('{}', $this->parameters($script));
    }

    /**
     * The parameters, the session id and the sequence all travel in the
     * request body, so the callback URL carries no query string of its own.
     * A web request's query string wins over its body when the evidence is
     * read back, so values rendered into the URL used to override the newer
     * values the script put in the body.
     */
    public function testCallbackUrlCarriesNoQueryString()
    {
        $script = $this->render([
            'header.host' => 'example.com',
            'query.mark' => 'kept'
        ]);

        $this->assertStringContainsString(
            "createCORSRequest('POST', 'https://example.com/json')",
            $script
        );
    }

    public function testCallbackUrlHasOneSlashBetweenHostAndEndpoint()
    {
        $noSlash = $this->render(['header.host' => 'example.com'], 'json');
        $twoSlashes = $this->render(['header.host' => 'example.com/'], '/json');

        $this->assertStringContainsString(
            "'https://example.com/json'",
            $noSlash
        );
        $this->assertStringContainsString(
            "'https://example.com/json'",
            $twoSlashes
        );
    }

    public function testFetchIsUsedWhereTheDeviceSupportsIt()
    {
        $script = $this->render(
            ['header.host' => 'example.com'],
            '/json',
            new FetchDeviceElement(true)
        );

        $this->assertStringContainsString(
            "fetch('https://example.com/json'",
            $script
        );
        $this->assertStringNotContainsString('createCORSRequest', $script);
    }

    public function testXmlHttpRequestIsUsedWhereTheDeviceDoesNot()
    {
        $without = $this->render(['header.host' => 'example.com']);
        $unsupported = $this->render(
            ['header.host' => 'example.com'],
            '/json',
            new FetchDeviceElement(false)
        );

        foreach ([$without, $unsupported] as $script) {
            $this->assertStringContainsString(
                "createCORSRequest('POST', 'https://example.com/json')",
                $script
            );
            $this->assertStringNotContainsString("fetch('", $script);
        }
    }

    private function render(
        array $evidence,
        string $endpoint = '/json',
        ?FlowElement $element = null
    ): string {
        $builder = new PipelineBuilder([
            'javascriptBuilderSettings' => [
                'endpoint' => $endpoint,
                'minify' => false
            ]
        ]);
        if ($element !== null) {
            $builder->add($element);
        }
        $flowData = $builder->build()->createFlowData();
        foreach ($evidence as $key => $value) {
            $flowData->evidence->set($key, $value);
        }
        $flowData->process();

        return $flowData->javascriptbuilder->javascript;
    }

    /**
     * The JSON the script is configured with, read from its one line.
     */
    private function parameters(string $script): string
    {
        $found = preg_match(
            '/var renderedParameters = function\(\) \{ return (.*); \};/',
            $script,
            $matches
        );
        $this->assertSame(1, $found, 'No parameters line in the script');

        return $matches[1];
    }
}
