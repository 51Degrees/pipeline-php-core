<?php
/* *********************************************************************
 * This Original Work is copyright of 51 Degrees Mobile Experts Limited.
 * Copyright 2023 51 Degrees Mobile Experts Limited, Davidson House,
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
use fiftyone\pipeline\core\JavascriptBuilderElement;
use fiftyone\pipeline\core\SequenceElement;
use fiftyone\pipeline\core\JsonBundlerElement;
use PHPUnit\Framework\TestCase;

class CookieElement extends FlowElement
{
    public string $dataKey = 'cookie';

    public array $properties = [
        'javascript' => [
            'type' => 'javascript'
        ]
    ];

    public function processInternal($flowData): void
    {
        $contents = [];

        $contents['javascript'] = "document.cookie = 'some cookie value'";
        $contents['normal'] = true;

        $data = new ElementDataDictionary($this, $contents);

        $flowData->setElementData($data);
    }
}

class EnableCookiesTests extends TestCase
{
    public function provider_testJavaScriptCookies()
    {
        return [
            [false, false, false],
            [true, false, false],
            [false, true, true],
            [true, true, true]
        ];
    }

    /**
     * Test that the cookie settings are respected correctly.
     * 
     * @dataProvider provider_testJavaScriptCookies
     * @param mixed $enableInConfig
     * @param mixed $enableInEvidence
     * @param mixed $expectCookie
     */
    public function testJavaScriptCookies($enableInConfig, $enableInEvidence, $expectCookie)
    {
        $jsElement = new JavascriptBuilderElement([
            'enableCookies' => $enableInConfig
        ]);

        $pipeline = (new PipelineBuilder())
            ->add(new CookieElement())
            ->add(new SequenceElement())
            ->add(new JsonBundlerElement())
            ->add($jsElement)
            ->build();

        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.fod-js-enable-cookies', $enableInEvidence ? 'true' :  'false');
        $flowData->process();

        $js = $flowData->javascriptbuilder->javascript;
        $matches = substr_count($js, 'document.cookie');
        if ($expectCookie) {
            $this->assertSame(2, $matches);
        }
        else {
            $this->assertSame(1, $matches);
        }
    }
}
