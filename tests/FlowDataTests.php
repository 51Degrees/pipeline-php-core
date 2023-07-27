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

use fiftyone\pipeline\core\ElementData;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\Messages;
use PHPUnit\Framework\TestCase;

class FlowDataTests extends TestCase
{
    /**
     * Check that an element data can be returned from a FlowData using its
     * data key.
     */
    public function testGetWithKey()
    {
        $element = $this->createMock(FlowElement::class);
        $element->dataKey = "testKey";
        $data = new ElementData($element);

        $flowData = new FlowData(null);
        $flowData->setElementData($data);

        $returnedData = $flowData->get("testKey");
        $this->assertNotNull($returnedData);
    }

    /**
     * Check that an element data can be returned from a FlowData using its
     * data key directly via a "magic getter".
     */
    public function testMagicGetter()
    {
        $element = $this->createMock(FlowElement::class);
        $element->dataKey = "testKey";
        $data = new ElementData($element);

        $flowData = new FlowData(null);
        $flowData->setElementData($data);

        $returnedData = $flowData->testKey;
        $this->assertNotNull($returnedData);
    }

    /**
     * Check that an element data can be returned from a FlowData using the
     * getFromElement method.
     */
    public function testGetFromElement()
    {
        $element = $this->createMock(FlowElement::class);
        $element->dataKey = "testKey";
        $data = new ElementData($element);

        $flowData = new FlowData(null);
        $flowData->setElementData($data);

        $returnedData = $flowData->getFromElement($element);
        $this->assertNotNull($returnedData);
    }

    /**
     * Check that an exception is thrown when fetching a key which does not
     * exist in the FlowData, and that the correct error message is returned.
     */
    public function testMissingKey()
    {
        $element = $this->createMock(FlowElement::class);
        $element->dataKey = "testKey";
        $data = new ElementData($element);

        $flowData = new FlowData(null);
        $flowData->setElementData($data);

        try {
            $returnedData = $flowData->get("otherKey");
            $this->fail();
        } catch (Exception $e) {
            $this->assertEquals(
                sprintf(
                    Messages::NO_ELEMENT_DATA,
                    "otherKey",
                    "testKey"
                ),
                $e->getMessage()
            );
        }
    }

    /**
     * Check that an exception is thrown when fetching a key through a magic
     * getter which does not exist in the FlowData, and that the correct error
     * message is returned.
     */
    public function testMissingKeyMagicGetter()
    {
        $element = $this->createMock(FlowElement::class);
        $element->dataKey = "testKey";
        $data = new ElementData($element);

        $flowData = new FlowData(null);
        $flowData->setElementData($data);

        try {
            $returnedData = $flowData->otherKey;
            $this->fail();
        } catch (Exception $e) {
            $this->assertEquals(
                sprintf(
                    Messages::NO_ELEMENT_DATA,
                    "otherKey",
                    "testKey"
                ),
                $e->getMessage()
            );
        }
    }

    /**
     * Check that an exception is thrown when fetching a key using the
     * getFromElement method which does not exist in the FlowData, and that the
     * correct error message is returned.
     */
    public function testMissingKeyFromElement()
    {
        $element = $this->createMock(FlowElement::class);
        $element->dataKey = "testKey";
        $data = new ElementData($element);

        $element2 = $this->createMock(FlowElement::class);
        $element2->dataKey = "otherKey";

        $flowData = new FlowData(null);
        $flowData->setElementData($data);

        try {
            $returnedData = $flowData->getFromElement($element2);
            $this->fail();
        } catch (Exception $e) {
            $this->assertEquals(
                sprintf(
                    Messages::NO_ELEMENT_DATA,
                    "otherKey",
                    "testKey"
                ),
                $e->getMessage()
            );
        }
    }
}
