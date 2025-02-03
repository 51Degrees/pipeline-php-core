<?php
/* *********************************************************************
 * This Original Work is copyright of 51 Degrees Mobile Experts Limited.
 * Copyright 2025 51 Degrees Mobile Experts Limited, Davidson House,
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

class TestEngine extends FlowElement
{
    public string $dataKey = 'test';

    public array $properties = [
        'javascript' => [
            'type' => 'javascript'
        ],
        'apvGood' => [
            'type' => 'string'
        ],
        'apvBad' => [
            'type' => 'string'
        ],
        'normal' => [
            'type' => 'boolean'
        ]
    ];

    public function processInternal($flowData): void
    {
        $contents = [];

        $contents['javascript'] = "console.log('hello world')";
        $contents['normal'] = true;

        $contents['apvGood'] = new AspectPropertyValue(null, 'Value');
        $contents['apvBad'] = new AspectPropertyValue('No value');

        $data = new ElementDataDictionary($this, $contents);

        $flowData->setElementData($data);
    }
}

class TestPipeline
{
    public $pipeline;

    public function __construct($minify = null)
    {
        if (is_null($minify)) {
            $pipelineSettings = [];
        } else {
            $jsSettings = ['minify' => $minify];
            $pipelineSettings = ['javascriptBuilderSettings' => $jsSettings];
        }
        $this->pipeline = (new PipelineBuilder($pipelineSettings))
            ->add(new TestEngine())
            ->build();
    }
}

class DelayedExecutionEngine1 extends FlowElement
{
    public string $dataKey = 'delayedexecutiontest1';

    public array $properties = [
        'one' => [
            'delayexecution' => false,
            'type' => 'javascript'
        ],
        'two' => [
            'evidenceproperties' => ['jsontestengine']
        ]
    ];

    public function processInternal($flowData): void
    {
        $contents = [
            'one' => 1,
            'two' => 2
        ];

        $data = new ElementDataDictionary($this, $contents);

        $flowData->setElementData($data);
    }
}

class DelayedExecutionEngine2 extends FlowElement
{
    public string $dataKey = 'delayedexecutiontest2';

    public array $properties = [
        'one' => [
            'delayexecution' => true,
            'type' => 'javascript'
        ],
        'two' => [
            'evidenceproperties' => ['one']
        ]
    ];

    public function processInternal($flowData): void
    {
        $contents = [
            'one' => 1,
            'two' => 2
        ];

        $data = new ElementDataDictionary($this, $contents);

        $flowData->setElementData($data);
    }
}

class DelayedExecutionEngine3 extends FlowElement
{
    public string $dataKey = 'delayedexecutiontest3';

    public array $properties = [
        'one' => [
            'evidenceproperties' => ['two', 'three']
        ],
        'two' => [
            'delayexecution' => true
        ],
        'three' => [
            'delayexecution' => false
        ]
    ];

    public function processInternal($flowData): void
    {
        $contents = [
            'one' => 1,
            'two' => 2,
            'three' => 3
        ];

        $data = new ElementDataDictionary($this, $contents);

        $flowData->setElementData($data);
    }
}

class JavaScriptBundlerTests extends TestCase
{
    public function testJSONBundler()
    {
        $flowData = (new TestPipeline(false))->pipeline->createFlowData();
        $flowData->process();

        $expected = [
            'javascriptProperties' => [
                0 => 'test.javascript',
            ],
            'test' => [
                'javascript' => 'console.log(\'hello world\')',
                'apvgood' => 'Value',
                'apvbad' => null,
                'apvbadnullreason' => 'No value',
                'normal' => true,
            ]
        ];

        $this->assertSame($expected, $flowData->jsonbundler->json);
    }

    public function testJavaScriptBuilderMinify()
    {
        // Generate minified javascript
        $flowData = (new TestPipeline(true))->pipeline->createFlowData();
        $flowData->process();
        $minified = $flowData->javascriptbuilder->javascript;

        // Generate non-minified javascript
        $flowData = (new TestPipeline(false))->pipeline->createFlowData();
        $flowData->process();
        $nonminified = $flowData->javascriptbuilder->javascript;

        // Generate javascript with default settings
        $flowData = (new TestPipeline())->pipeline->createFlowData();
        $flowData->process();
        $default = $flowData->javascriptbuilder->javascript;

        // We don't want to get too specific here. Just check that
        // the minified version is smaller to confirm that it's
        // done something.
        $this->assertGreaterThan(strlen($minified), strlen($nonminified));
        // Check that default is to minify the output
        $this->assertSame(strlen($default), strlen($minified));
    }

    public function testSequence()
    {
        $flowData = (new TestPipeline(false))->pipeline->createFlowData();
        $flowData->evidence->set('query.session-id', 'test');
        $flowData->evidence->set('query.sequence', 10);

        $flowData->process();
        
        $this->assertEquals(11, $flowData->evidence->get('query.sequence'));
        $this->assertCount(0, $flowData->jsonbundler->json['javascriptProperties']);
    }

    public function testJsonbundlerWhenDelayedExecutionFalse()
    {
        $pipeline = (new PipelineBuilder())
            ->add(new DelayedExecutionEngine1())
            ->build();

        $flowData = $pipeline->createFlowData();
        $flowData->process();

        $expected = json_encode(['one' => 1, 'two' => 2]);
        $actual = json_encode($flowData->jsonbundler->json['delayedexecutiontest1']);

        $this->assertJsonStringEqualsJsonString($expected, $actual);
    }

    public function testJsonbundlerWhenDelayedExecutionTrue()
    {
        $pipeline = (new PipelineBuilder())
            ->add(new DelayedExecutionEngine2())
            ->build();

        $flowData = $pipeline->createFlowData();

        $flowData->process();

        $expected = json_encode([
            'onedelayexecution' => true,
            'one' => 1,
            'twoevidenceproperties' => ['delayedexecutiontest2.one'],
            'two' => 2
        ]);
        $actual = json_encode($flowData->jsonbundler->json['delayedexecutiontest2']);
        
        $this->assertJsonStringEqualsJsonString($expected, $actual);
    }

    public function testJsonbundlerWhenDelayedExecutionMultiple()
    {
        $pipeline = (new PipelineBuilder())
            ->add(new DelayedExecutionEngine3())
            ->build();
        
        $flowData = $pipeline->createFlowData();
        $flowData->process();

        $expected = json_encode([
            'oneevidenceproperties' => ['delayedexecutiontest3.two'],
            'one' => 1,
            'twodelayexecution' => true,
            'two' => 2,
            'three' => 3
        ]);
        $actual = json_encode($flowData->jsonbundler->json['delayedexecutiontest3']);
        
        $this->assertJsonStringEqualsJsonString($expected, $actual);
    }
}
