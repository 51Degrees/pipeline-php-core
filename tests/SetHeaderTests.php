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

require_once(__DIR__ . "/../vendor/autoload.php");
require_once(__DIR__ . "/classes/TestPipeline.php");
require_once(__DIR__ . "/classes/Constants.php");

use fiftyone\pipeline\core\SetHeaderElement;
use fiftyone\pipeline\core\Messages;
use fiftyone\pipeline\core\Utils;
use fiftyone\pipeline\core\AspectPropertyValue;
use PHPUnit\Framework\TestCase;

class SetHeaderTests extends TestCase
{  
    // Data Provider for testGetResponseHeaderValue
	public static function provider_testGetResponseHeaderValue()
    {
        return array(
        array(array("device" => (object) array( "setheaderbrowseraccept-ch" => new AspectPropertyValue(null, Constants::UNKNOWN), "setheaderplatformaccept-ch" => new AspectPropertyValue(null, Constants::UNKNOWN), "setheaderhardwareaccept-ch" => new AspectPropertyValue(null, Constants::UNKNOWN))), ""),
		array(array("device" => (object) array( "setheaderbrowseraccept-ch" => new AspectPropertyValue(null, Constants::ACCEPTCH_BROWSER_VALUE))), "SEC-CH-UA,SEC-CH-UA-Full-Version"),
		array(array("device" => (object) array( "setheaderplatformaccept-ch" => new AspectPropertyValue(null, Constants::ACCEPTCH_PLATFORM_VALUE), "setheaderhardwareaccept-ch" => new AspectPropertyValue(null, Constants::ACCEPTCH_HARDWARE_VALUE))), "SEC-CH-UA-Model,SEC-CH-UA-Mobile,SEC-CH-UA-Arch,SEC-CH-UA-Platform,SEC-CH-UA-Platform-Version"),
        array(array("device" => (object) array( "setheaderbrowseraccept-ch" => new AspectPropertyValue(null, Constants::ACCEPTCH_BROWSER_VALUE), "setheaderplatformaccept-ch" => new AspectPropertyValue(null, Constants::ACCEPTCH_PLATFORM_VALUE), "setheaderhardwareaccept-ch" => new AspectPropertyValue(null, Constants::ACCEPTCH_HARDWARE_VALUE))), "SEC-CH-UA,SEC-CH-UA-Full-Version,SEC-CH-UA-Model,SEC-CH-UA-Mobile,SEC-CH-UA-Arch,SEC-CH-UA-Platform,SEC-CH-UA-Platform-Version")
        );
    }

    // Test response header value to be set for UACH
    /**
     * @dataProvider provider_testGetResponseHeaderValue
     */
    public function testGetResponseHeaderValue($device, $expectedValue)
    {
        $setHeaderPropertiesDict = array('device' => array('SetHeaderBrowserAccept-CH', 'SetHeaderHardwareAccept-CH', 'SetHeaderPlatformAccept-CH'));
        $testPipeline = new TestPipeline();
        $setHeaderElement = new SetHeaderElement();
		$testPipeline->flowData->data = $device;
		$flowData = $testPipeline->flowData;
        $actualValue = $setHeaderElement->getResponseHeaderValue($flowData, $setHeaderPropertiesDict);
        $this->assertEquals($expectedValue, $actualValue["Accept-CH"]);

    }
	
    // Test response header not being sent for empty value
    public function testSetResponseHeader_emptyHeader()
    {
        $data = array("set-headers" => (object) array("responseheaderdictionary" => array("Accept-CH"=> "")));
        $setHeaderPropertiesDict = array('device' => array('SetHeaderBrowserAccept-CH', 'SetHeaderHardwareAccept-CH', 'SetHeaderPlatformAccept-CH'));
        $testPipeline = new TestPipeline();
        $testPipeline->flowData->data = $data;
        $flowData = $testPipeline->flowData;
	    $actualValue = Utils::setResponseHeader($flowData);
        $this->assertEquals(False, isset($actualValue["Accept-CH"]));
    }

    // Data Provider for testGetResponseHeaderValue
	public static function provider_testGetResponseHeaderName_Valid()
    {
        return array(
        array("SetHeaderBrowserAccept-CH", "Accept-CH"),
		array("SetHeaderBrowserCritical-CH", "Critical-CH"),
        array("SetHeaderUnknownAccept-CH", "Accept-CH")
        );
    }

    // Test get response header function for valid formats.
    /**
     * @dataProvider provider_testGetResponseHeaderName_Valid
     */
    public function testGetResponseHeaderName_Valid($data, $expectedValue)
    {
    $setHeaderElement = new SetHeaderElement();
    $actualValue = $setHeaderElement->getResponseHeaderName($data);
    $this->assertEquals($expectedValue, $actualValue);
    }

    // Data Provider for testGetResponseHeaderValue
	public static function provider_testGetResponseHeaderName_InValid()
    {
        return array(
        array("TestBrowserAccept-CH", Messages::PROPERTY_NOT_SET_HEADER),
        array("SetHeaderbrowserAccept-ch", Messages::WRONG_PROPERTY_FORMAT),
		array("SetHeaderBrowseraccept-ch", Messages::WRONG_PROPERTY_FORMAT)
        );
    }

    // Test get response header function for valid formats.
    /**
     * @dataProvider provider_testGetResponseHeaderName_InValid
     */
    public function testGetResponseHeaderName_InValid($data, $expectedValue)
    {
        $setHeaderElement = new SetHeaderElement();
    
        try{
            $setHeaderElement->getResponseHeaderName($data);
        } catch(\Exception $e){
            $this->assertEquals(sprintf($expectedValue, $data), $e->getMessage());
        }
    
    }
}
