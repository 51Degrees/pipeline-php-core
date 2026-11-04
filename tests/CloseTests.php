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

use fiftyone\pipeline\core\ElementData;
use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\core\tests\classes\BrokenElement;
use fiftyone\pipeline\core\tests\classes\RecordedElement;
use PHPUnit\Framework\TestCase;

class CloseTests extends TestCase
{
    /**
     * An element whose element data has a close method of its own, as the
     * results of an on-premise engine have. It counts its own closes.
     */
    private function closingElement(): FlowElement
    {
        return new class() extends FlowElement {
            public string $dataKey = 'closing';
            public int $closes = 0;

            public function processInternal($flowData): void
            {
                $flowData->setElementData(new class($this) extends ElementData {
                    public bool $closed = false;

                    public function close(): void
                    {
                        $this->closed = true;
                    }
                });
            }

            public function close(): void
            {
                ++$this->closes;
            }
        };
    }

    /**
     * An element whose element data needs no cleanup.
     */
    private function plainElement(): FlowElement
    {
        return new class() extends FlowElement {
            public string $dataKey = 'plain';

            public function processInternal($flowData): void
            {
                $flowData->setElementData(new ElementDataDictionary($this, ['value' => 1]));
            }
        };
    }

    /**
     * An element that fails to close.
     */
    private function failingElement(): FlowElement
    {
        return new class() extends FlowElement {
            public string $dataKey = 'failing';

            public function close(): void
            {
                throw new \Exception('close failed');
            }
        };
    }

    private function build(array $elements, array $settings = [])
    {
        $settings['addJavaScriptBuilder'] = false;
        $settings['useSetHeaderProperties'] = false;
        $builder = new PipelineBuilder($settings);
        foreach ($elements as $element) {
            $builder->add($element);
        }

        return $builder->build();
    }

    /**
     * Element data with a close method is closed with the FlowData, and
     * element data without one is left alone.
     */
    public function testFlowDataClosesElementData()
    {
        $pipeline = $this->build([$this->closingElement(), $this->plainElement()]);
        $flowData = $pipeline->createFlowData();
        try {
            $flowData->process();
            $closing = $flowData->get('closing');
            $this->assertFalse($closing->closed);
        } finally {
            $flowData->close();
            $pipeline->close();
        }

        $this->assertTrue($closing->closed);
        $this->assertSame(1, $flowData->get('plain')->get('value'));
    }

    /**
     * A FlowData that was never processed holds nothing to close.
     */
    public function testUnprocessedFlowDataCloses()
    {
        $pipeline = $this->build([$this->closingElement()]);
        $flowData = $pipeline->createFlowData();
        try {
            $this->assertSame([], $flowData->data);
        } finally {
            $flowData->close();
            $pipeline->close();
        }
    }

    public function testPipelineClosesElements()
    {
        $element = $this->closingElement();
        $pipeline = $this->build([$element, $this->plainElement()]);
        try {
            $this->assertSame(0, $element->closes);
        } finally {
            $pipeline->close();
        }

        $this->assertSame(1, $element->closes);
    }

    /**
     * Elements shared with another pipeline are left for the caller to
     * close.
     */
    public function testPipelineLeavesElementsOpen()
    {
        $element = $this->closingElement();
        $pipeline = $this->build([$element], ['autoCloseElements' => false]);
        try {
            $this->assertFalse($pipeline->autoCloseElements);
        } finally {
            $pipeline->close();
        }

        $this->assertSame(0, $element->closes);
    }

    /**
     * Every element is closed even when one fails, and the failure is
     * thrown afterwards.
     */
    public function testPipelineClosesEveryElement()
    {
        $element = $this->closingElement();
        $pipeline = $this->build([$this->failingElement(), $element]);

        $message = null;
        try {
            $pipeline->close();
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        $this->assertSame('close failed', $message);
        $this->assertSame(1, $element->closes);
    }

    /**
     * When an element in a configuration fails to build, the elements built
     * before it are closed, and the error that stopped the build is thrown.
     */
    public function testFailedConfigurationClosesBuiltElements()
    {
        RecordedElement::$closed = [];
        $config = ['PipelineOptions' => ['Elements' => [
            ['BuilderName' => RecordedElement::class],
            ['BuilderName' => BrokenElement::class]
        ]]];

        $message = null;
        try {
            (new PipelineBuilder())->buildFromConfig($config);
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        $this->assertSame('element failed to build', $message);
        $this->assertCount(1, RecordedElement::$closed);
    }
}
