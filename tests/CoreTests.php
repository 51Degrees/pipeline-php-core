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

use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\core\tests\classes\ExampleFlowElement1;
use fiftyone\pipeline\core\tests\classes\ExampleFlowElement2;
use fiftyone\pipeline\core\tests\classes\MemoryLogger;
use fiftyone\pipeline\core\tests\classes\StopFlowData;
use fiftyone\pipeline\core\tests\classes\TestPipeline;
use PHPUnit\Framework\TestCase;

class CoreTests extends TestCase
{
    // Test logging works
    public function testLogger()
    {
        $testPipeline = new TestPipeline();
        $loggerMessage = $testPipeline->logger->log[0]['message'];
        $this->assertSame('test', $loggerMessage);
    }

    // Test getting evidence
    public function testEvidence()
    {
        $testPipeline = new TestPipeline();
        $userAgent = $testPipeline->flowData->evidence->get('header.user-agent');
        $this->assertSame('test', $userAgent);
    }

    // Test filtering evidence
    public function testEvidenceKeyFilter()
    {
        $testPipeline = new TestPipeline();
        $nullEvidence = $testPipeline->flowData->evidence->get('header.other-evidence');
        $this->assertNull($nullEvidence);
    }

    // Test Getter methods
    public function testGet()
    {
        $testPipeline = new TestPipeline();
        $getValue = $testPipeline->flowData->get('example1')->get('integer');
        $this->assertSame(5, $getValue);
    }

    public function testGetWhere()
    {
        $testPipeline = new TestPipeline();
        $this->assertCount(1, $testPipeline->flowData->getWhere('type', 'int'));
    }

    public function testGetFromElement()
    {
        $testPipeline = new TestPipeline();
        $getValue = $testPipeline->flowData->getFromElement($testPipeline->flowElement1)->get('integer');
        $this->assertSame(5, $getValue);
    }

    // Test check stop FlowData works
    public function testStopFlowData()
    {
        $getValue = null;
        $testPipeline = new TestPipeline();

        try {
            $getValue = $testPipeline->flowData->get('example2');
            $this->fail();
        } catch (\Exception $e) {
            // An exception should be thrown.
        }

        $this->assertNull($getValue);
    }

    // Test exception is thrown when not suppressed.
    public function testErrorsDontSuppressException()
    {
        try {
            $testPipeline = new TestPipeline(false);
            $this->fail('Exception is expected.');
        } catch (\Exception $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    // Test errors are returned
    public function testErrors()
    {
        $testPipeline = new TestPipeline();
        $getValue = $testPipeline->flowData->errors['error'];
        $this->assertNotNull($getValue);
    }

    // Test Already Processed FlowData.
    public function testErrorsAlreadyProcessed()
    {
        $logger = new MemoryLogger('info');
        $flowElement1 = new ExampleFlowElement1();
        $pipeline = (new PipelineBuilder())
            ->add($flowElement1)
            ->addLogger($logger)
            ->build();
        $flowData = $pipeline->createFlowData();
        $flowData->process();

        try {
            $flowData->process();
            $this->fail('Exception is expected.');
        } catch (\Exception $e) {
            $this->assertSame('FlowData already processed', $e->getMessage());
        }
    }

    // Test if adding properties at a later stage works (for cloud FlowElements for example)
    public function testUpdateProperties()
    {
        $flowElement1 = new ExampleFlowElement1();
        $logger = new MemoryLogger('info');
        $pipeline = (new PipelineBuilder())->add($flowElement1)
            ->add(new StopFlowData())
            ->add(new ExampleFlowElement2())
            ->addLogger($logger)
            ->build();
        $flowElement1->properties['integer']['testing'] = 'true';
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('header.user-agent', 'test');
        $flowData->evidence->set('some.other-evidence', 'test');
        $flowData->process();

        $this->assertCount(0, $flowData->getWhere('testing', 'true'));

        $flowElement1->updatePropertyList();
        $this->assertCount(1, $flowData->getWhere('testing', 'true'));
    }
}
