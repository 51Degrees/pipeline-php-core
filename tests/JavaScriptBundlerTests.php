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

require(__DIR__ . "/../vendor/autoload.php");

use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\AspectPropertyValue;

use PHPUnit\Framework\TestCase;

class TestEngine extends FlowElement
{
    public $dataKey = "test";

    public $properties = array(
        "javascript" => array(
            "type" => "javascript"
        ),
        "apvGood" => array(
            "type" => "string"
        ),
        "apvBad" => array(
            "type" => "string"
        ),
        "normal" => array(
            "type" => "boolean"
        )
    );

    public function processInternal($FlowData)
    {
        $contents = [];

        $contents["javascript"] = "console.log('hello world')";
        $contents["normal"] = true;

        $contents["apvGood"] = new AspectPropertyValue(null, "Value");
        $contents["apvBad"] = new AspectPropertyValue("No value");

        $data = new ElementDataDictionary($this, $contents);

        $FlowData->setElementData($data);
    }
}

class TestPipeline
{
    public $Pipeline;

    public function __construct($minify = NULL)
    {
        if (is_null($minify)) {
            $pipelineSettings = array();
        } else {
            $jsSettings = array('minify' => $minify);
            $pipelineSettings = array('javascriptBuilderSettings' => $jsSettings);
        }
        $this->Pipeline = (new PipelineBuilder($pipelineSettings))
            ->add(new TestEngine())
            ->build();
    }
}

class DelayedExecutionEngine1 extends FlowElement
{

    public $dataKey = "delayedexecutiontest1";

    public $properties = [
        "one" => [
            "delayexecution" => false,
            "type" => 'javascript'
        ],
        "two" => [
            "evidenceproperties" => ['jsontestengine']
        ]
    ];

    public function processInternal($flowData)
    {

        $contents = [
            "one" => 1,
            "two" => 2
        ];

        $data = new ElementDataDictionary($this, $contents);

        $flowData->setElementData($data);
    }
}

class DelayedExecutionEngine2 extends FlowElement
{

    public $dataKey = "delayedexecutiontest2";

    public $properties = [
        "one" => [
            "delayexecution" => true,
            "type" => 'javascript'
        ],
        "two" => [
            "evidenceproperties" => ['one']
        ]
    ];

    public function processInternal($flowData)
    {

        $contents = [
            "one" => 1,
            "two" => 2
        ];

        $data = new ElementDataDictionary($this, $contents);

        $flowData->setElementData($data);
    }
}

class DelayedExecutionEngine3 extends FlowElement
{

    public $dataKey = "delayedexecutiontest3";

    public $properties = [
        "one" => [
            "evidenceproperties" => ['two', 'three']
        ],
        "two" => [
            "delayexecution" => true
        ],
        "three" => [
            "delayexecution" => false
        ]
    ];

    public function processInternal($flowData)
    {

        $contents = [
            "one" => 1,
            "two" => 2,
            "three" => 3
        ];

        $data = new ElementDataDictionary($this, $contents);

        $flowData->setElementData($data);
    }
}

class JavaScriptBundlerTests extends TestCase
{
    public function testJSONBundler()
    {
        $Pipeline = (new TestPipeline(false))->Pipeline;

        $FlowData = $Pipeline->createFlowData();

        $FlowData->process();

        $expected = array(
            'javascriptProperties' =>
            array(
                0 => 'test.javascript',
            ),
            'test' =>
            array(
                'javascript' => 'console.log(\'hello world\')',
                'apvgood' => 'Value',
                'apvbad' => null,
                'apvbadnullreason' => 'No value',
                'normal' => true,
            )
        );

        $this->assertEquals($FlowData->jsonbundler->json, $expected);
    }

    public function testJavaScriptBuilder_Minify()
    {
        // Generate minified javascript
        $Pipeline = (new TestPipeline(true))->Pipeline;
        $FlowData = $Pipeline->createFlowData();
        $FlowData->process();
        $minified = $FlowData->javascriptbuilder->javascript;

        // Generate non-minified javascript
        $Pipeline = (new TestPipeline(false))->Pipeline;
        $FlowData = $Pipeline->createFlowData();
        $FlowData->process();
        $nonminified = $FlowData->javascriptbuilder->javascript;

        // Generate javascript with default settings
        $Pipeline = (new TestPipeline())->Pipeline;
        $FlowData = $Pipeline->createFlowData();
        $FlowData->process();
        $default = $FlowData->javascriptbuilder->javascript;

        // We don't want to get too specific here. Just check that 
        // the minified version is smaller to confirm that it's
        // done something.
        $this->assertGreaterThan(strlen($minified), strlen($nonminified));
        // Check that default is to minify the output
        $this->assertEquals(strlen($default), strlen($minified));
    }

    public function testSequence()
    {
        $Pipeline = (new TestPipeline(false))->Pipeline;

        $FlowData = $Pipeline->createFlowData();

        $FlowData->evidence->set("query.session-id", "test");
        $FlowData->evidence->set("query.sequence", 10);

        $FlowData->process();

        $this->assertEquals($FlowData->evidence->get("query.sequence"), 11);

        $this->assertEquals(count($FlowData->jsonbundler->json["javascriptProperties"]), 0);
    }

    public function test_jsonbundler_when_delayed_execution_false()
    {

        $pipeline = new PipelineBuilder();

        $pipeline->add(new DelayedExecutionEngine1());
        $pipeline = $pipeline->build();

        $flowData = $pipeline->createFlowData();

        $flowData->process();

        $expected = json_encode(["one" => 1, "two" => 2]);
        $actual = json_encode($flowData->jsonbundler->json["delayedexecutiontest1"]);
        $this->assertEquals($actual, $expected);

    }

    public function test_jsonbundler_when_delayed_execution_true()
    {

        $pipeline = new PipelineBuilder();

        $pipeline->add(new DelayedExecutionEngine2());
        $pipeline = $pipeline->build();

        $flowData = $pipeline->createFlowData();

        $flowData->process();

        $expected = json_encode([
            "onedelayexecution" => true,
            "one" => 1,
            "twoevidenceproperties" => ['delayedexecutiontest2.one'],
            "two" => 2
        ]);

        $actual = json_encode($flowData->jsonbundler->json["delayedexecutiontest2"]);
        $this->assertEquals($actual, $expected);
    }


    public function test_jsonbundler_when_delayed_execution_multiple()
    {

        $pipeline = new PipelineBuilder();

        $pipeline->add(new DelayedExecutionEngine3());
        $pipeline = $pipeline->build();

        $flowData = $pipeline->createFlowData();

        $flowData->process();

        $expected = json_encode([
            "oneevidenceproperties" => ['delayedexecutiontest3.two'],
            "one" => 1,
            "twodelayexecution" => true,
            "two" => 2,
            "three" => 3
        ]);

        $actual = json_encode($flowData->jsonbundler->json["delayedexecutiontest3"]);
        $this->assertEquals($actual, $expected);
    }
}
