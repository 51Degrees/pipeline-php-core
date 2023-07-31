<?php
/* *********************************************************************
 * This Original Work is copyright of 51 Degrees Mobile Experts Limited.
 * Copyright 2022 51 Degrees Mobile Experts Limited, Davidson House,
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

require(__DIR__ . "/../vendor/autoload.php");

require(__DIR__ . "/classes/TestPipeline.php");

use fiftyone\pipeline\core\JavascriptBuilderElement;
use fiftyone\pipeline\core\PipelineBuilder;

use fiftyone\pipeline\core\SequenceElement;
use fiftyone\pipeline\core\SetHeaderElement;
use fiftyone\pipeline\core\tests\ErrorFlowData;
use fiftyone\pipeline\core\tests\StopFlowData;
use fiftyone\pipeline\core\tests\MemoryLogger;
use fiftyone\pipeline\core\tests\ExampleFlowElement1;
use fiftyone\pipeline\core\tests\ExampleFlowElement2;
use fiftyone\pipeline\core\tests\TestPipelineBuilder;

use PHPUnit\Framework\TestCase;

class CoreTests extends TestCase
{
    // Test logging works
    public function testLogger()
    {
        $testPipeline = new TestPipeline();
        $loggerMessage = $testPipeline->logger->log[0]["message"];
        $this->assertTrue($loggerMessage === "test");
    }
    
    // Test getting evidence
    public function testEvidence()
    {
        $testPipeline = new TestPipeline();
        $userAgent = $testPipeline->flowData->evidence->get("header.user-agent");
        $this->assertTrue($userAgent === "test");
    }

    // Test filtering evidence
    public function testEvidenceKeyFilter()
    {
        $testPipeline = new TestPipeline();
        $nullEvidence = $testPipeline->flowData->evidence->get("header.other-evidence");
        $this->assertTrue($nullEvidence === null);
    }

    // Test Getter methods
    public function testGet()
    {
        $testPipeline = new TestPipeline();
        $getValue = $testPipeline->flowData->get("example1")->get("integer");
        $this->assertTrue($getValue === 5);
    }

    public function testGetWhere()
    {
        $testPipeline = new TestPipeline();
        $getValue = count($testPipeline->flowData->getWhere("type", "int"));
        $this->assertTrue($getValue === 1);
    }

    public function testGetFromElement()
    {
        $testPipeline = new TestPipeline();
        $getValue = $testPipeline->flowData->getFromElement($testPipeline->flowElement1)->get("integer");
        $this->assertTrue($getValue === 5);
    }

    // Test check stop FlowData works
    public function testStopFlowData()
    {
        $getValue = null;
        $testPipeline = new TestPipeline();
        try {
            $getValue = $testPipeline->flowData->get("example2");
            $this->fail();
        }
        catch (\Exception $e) {
            // An exception should be thrown.
        }
        $this->assertTrue($getValue === null);
    }

    // Test exception is thrown when not suppressed.
    public function testErrors_DontSuppressException() {

        try {
            $testPipeline = new TestPipeline(false);
            $this->fail("Exception is expected.");
        }
        catch (\Exception $e) {
            $this->assertTrue(!empty($e->getMessage()));
        }
    }

    // Test errors are returned
    public function testErrors()
    {
        $testPipeline = new TestPipeline();
        $getValue = $testPipeline->flowData->errors["error"];
        $this->assertTrue(isset($getValue));
    }

    // Test Already Processed FlowData.
    public function testErrors_AlreadyProcessed() {

            $logger = new MemoryLogger("info");
            $flowElement1 = new ExampleFlowElement1();
            $pipeline = (new PipelineBuilder())
                ->add($flowElement1)
                ->addLogger($logger)
                ->build();
            $flowData = $pipeline->createFlowData();
            $flowData->process();
        try {
            $flowData->process();
            $this->fail("Exception is expected.");
        }
        catch (\Exception $e) {
            $this->assertEquals($e->getMessage(), "FlowData already processed");
        }
    }
        
    // Test if adding properties at a later stage works (for cloud FlowElements for example)
    public function testUpdateProperties()
    {
        $flowElement1 = new ExampleFlowElement1();
        $logger = new MemoryLogger("info");
        $pipeline = (new PipelineBuilder())->add($flowElement1)
        ->add(new StopFlowData())
        ->add(new ExampleFlowElement2())
        ->addLogger($logger)
        ->build();
        $flowElement1->properties["integer"]["testing"] = "true";
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set("header.user-agent", "test");
        $flowData->evidence->set("some.other-evidence", "test");
        $flowData->process();

        $getValue = count($flowData->getWhere("testing", "true"));
        $this->assertTrue($getValue === 0);
        $flowElement1->updatePropertyList();
        $getValue = count($flowData->getWhere("testing", "true"));
        $this->assertTrue($getValue === 1);
    }

    public function testSequenceElementIsAlwaysFirstInPipeline()
    {
        $configFile = __DIR__ . '/testWebConfig.json';

        $pipeline = (new PipelineBuilder())->buildFromConfig($configFile)->build();
        $this->assertSame(get_class(reset($pipeline->flowElements)), SequenceElement::class);

        $pipeline = (new PipelineBuilder())->build();
        $this->assertSame(get_class(reset($pipeline->flowElements)), SequenceElement::class);

        $config = json_decode(file_get_contents($configFile), true);
        $pipeline = (new PipelineBuilder($config))->build();
        $this->assertSame(get_class(reset($pipeline->flowElements)), SequenceElement::class);
    }

    public function testSetHeaderElementIsAlwaysLastInPipeline()
    {
        $configFile = __DIR__ . '/testWebConfig.json';

        $pipeline = (new PipelineBuilder())->buildFromConfig($configFile)->build();
        $this->assertSame(get_class(end($pipeline->flowElements)), SetHeaderElement::class);

        $pipeline = (new PipelineBuilder())->build();
        $this->assertSame(get_class(end($pipeline->flowElements)), SetHeaderElement::class);

        $config = json_decode(file_get_contents($configFile), true);
        $pipeline = (new PipelineBuilder($config))->build();
        $this->assertSame(get_class(end($pipeline->flowElements)), SetHeaderElement::class);
    }

    public function testPipelineDoesntHaveDuplicateSequenceElements()
    {
        $configFile = __DIR__ . '/testWebConfig.json';
        $filter = function ($element) {
            return $element instanceof SequenceElement;
        };

        $pipeline = (new PipelineBuilder())->buildFromConfig($configFile)->build();
        $this->assertCount(1, array_filter($pipeline->flowElements, $filter));

        $pipeline = (new PipelineBuilder())->build();
        $this->assertCount(1, array_filter($pipeline->flowElements, $filter));

        $config = json_decode(file_get_contents($configFile), true);
        $pipeline = (new PipelineBuilder($config))->build();
        $this->assertCount(1, array_filter($pipeline->flowElements, $filter));
    }

    public function testPipelineDoesntHaveDuplicateJavascriptBuilderElements()
    {
        $configFile = __DIR__ . '/testWebConfig.json';
        $filter = function ($element) {
            return $element instanceof JavascriptBuilderElement;
        };

        $pipeline = (new PipelineBuilder())->buildFromConfig($configFile)->build();
        $this->assertCount(1, array_filter($pipeline->flowElements, $filter));

        $pipeline = (new PipelineBuilder())->build();
        $this->assertCount(1, array_filter($pipeline->flowElements, $filter));

        $config = json_decode(file_get_contents($configFile), true);
        $pipeline = (new PipelineBuilder($config))->build();
        $this->assertCount(1, array_filter($pipeline->flowElements, $filter));
    }

    public function testPipelineDoesntHaveDuplicateSetHeaderElements()
    {
        $configFile = __DIR__ . '/testWebConfig.json';
        $filter = function ($element) {
            return $element instanceof SetHeaderElement;
        };

        $pipeline = (new PipelineBuilder())->buildFromConfig($configFile)->build();
        $this->assertCount(1, array_filter($pipeline->flowElements, $filter));

        $pipeline = (new PipelineBuilder())->build();
        $this->assertCount(1, array_filter($pipeline->flowElements, $filter));

        $config = json_decode(file_get_contents($configFile), true);
        $pipeline = (new PipelineBuilder($config))->build();
        $this->assertCount(1, array_filter($pipeline->flowElements, $filter));
    }
}
