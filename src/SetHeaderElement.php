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

namespace fiftyone\pipeline\core;

use Exception;

/**
 *  Set response headers element class. This is used to get response
 *  headers based on what the browser supports. For example, newer
 *  Chrome browsers support the Accept-CH header
 */
class SetHeaderElement extends FlowElement
{
    /**
     * @var string
     */
    public $dataKey = "set-headers";
    /**
     * @var array
     */
    private $setHeaderProperties = [];

    /**
     * Add the response header dictionary to the FlowData.
     * @param FlowData $flowData
     */
    public function processInternal($flowData)
    {
        if (empty($this->setHeaderProperties)) {
            $this->setHeaderProperties = $this->getSetHeaderPropertiesPipeline($flowData->pipeline);
        }

        $responseHeaders = $this->getResponseHeaderValue($flowData, $this->setHeaderProperties);

        $data = new ElementDataDictionary($this, ["responseheaderdictionary" => $responseHeaders]);

        $flowData->setElementData($data);
    }

    /**
     *  Get All the properties starting with SetHeader string from pipeline
     * @param Pipeline $pipeline
     * @return array A dictionary object containing SetHeader properties list against flowElement
     */
    public function getSetHeaderPropertiesPipeline(Pipeline $pipeline): array
    {
        $setHeaderPropertiesDict = [];

        // Loop over each flowElement in pipeline to check SetHeader properties
        foreach ($pipeline->flowElements as $flowElement) {
            # Get the properties against the flowElement
            $properties = $flowElement->getProperties();

            $setHeaderElementList = [];

            // Loop over each flowElement property
            foreach ($properties as $propertyKey => $propertyMeta) {
                // Check if the property starts ith SetHeader
                if (strpos($propertyKey, "setheader") !== false) {
                    $setHeaderElementList[] = $propertyMeta["name"];
                }
            }

            // Add SetHeader element list in dict against flowElement.datakey as key
            if (count($setHeaderElementList)) {
                $setHeaderPropertiesDict[$flowElement->dataKey] = $setHeaderElementList;
            }
        }

        return $setHeaderPropertiesDict;
    }

    /**
     * Get response header value using set header properties from FlowData
     * @param FlowData $flowData
     * @param array $setHeaderPropertiesDict A processed FlowData object containing setheader properties
     * @return array A dictionary object containing SetHeader properties list against flowElement
     * @throws Exception
     */
    public function getResponseHeaderValue(FlowData $flowData, array $setHeaderPropertiesDict): array
    {
        $responseHeadersDict = [];

        // Loop over all the flowElements to process Set Header properties for User Agent Client Hints
        foreach ($setHeaderPropertiesDict as $elementDataKey => $setHeaderElementList) {
            // Loop over each setHeader property of the element
            foreach ($setHeaderElementList as $setHeaderProperty) {
                // Get response header key to be set in response
                $responseHeader = $this->getResponseHeaderName($setHeaderProperty);

                // Get SetHeader property value from elementData
                $setHeaderValue = $this->getPropertyValue($flowData, $elementDataKey, $setHeaderProperty);

                if (isset($responseHeadersDict[$responseHeader])) {
                    $responseHeaderValue = $responseHeadersDict[$responseHeader];
                    if ($responseHeaderValue == "") {
                        $responseHeaderValue = $setHeaderValue;
                    } else {
                        if ($setHeaderValue != "") {
                            $responseHeaderValue = $responseHeaderValue . "," . $setHeaderValue;
                        }
                    }
                    $responseHeadersDict[$responseHeader] = $responseHeaderValue;
                } else {
                    $responseHeadersDict[$responseHeader] = $setHeaderValue;
                }
            }
        }
        return $responseHeadersDict;
    }

    /**
     *  Determines which response header the property value will be appended to by
     *  stripping the 'SetHeader' string and the 'Component Name' from the property name.
     * @param string $propertyKey Key for SetHeaderAcceptCH property
     * @return string Response Header name
     */
    public function getResponseHeaderName(string $propertyKey): string
    {
        $actualPropertyName = $propertyKey;

        // Check if property name starts with SetHeader.
        // If Yes, Discard SetHeader from property name.
        if (strcmp(substr($propertyKey, 0, 9), "SetHeader") !== 0) {
            throw new Exception(
                sprintf(
                    Messages::PROPERTY_NOT_SET_HEADER,
                    $actualPropertyName
                )
            );
        } else {
            $propertyKey = str_replace("SetHeader", "", $propertyKey);
        }

        // Check if the first letter of Component name is in Uppercase
        // If Yes, Split the propertyKey based on Uppercase letters
        if (!ctype_upper(substr($propertyKey, 0, 1))) {
            throw new Exception(
                sprintf(
                    Messages::WRONG_PROPERTY_FORMAT,
                    $actualPropertyName
                )
            );
        } else {
            $parts = preg_split('/(?=[A-Z][^A-Z]*)/', $propertyKey, -1, PREG_SPLIT_NO_EMPTY);

            // Get the Component name string to be removed from the key
            $discardLetter = $parts[0];
        }

        // Check if property name contains the header name that starts with upper case
        // If Yes, Remove the previously found Component Name to get the Header Name
        if (count($parts) <= 1) {
            throw new Exception(
                sprintf(
                    Messages::WRONG_PROPERTY_FORMAT,
                    $actualPropertyName
                )
            );
        } else {
            $responseHeader = str_replace($discardLetter, "", $propertyKey);
        }

        return $responseHeader;
    }

    /**
     *  Try to get the value for the given element and property.
     *  If the value cannot be found or is null/unknown, then ""
     *  will be returned.
     * @param FlowData $flowData A processed FlowData instance to get the value from.
     * @param string $elementKey Key for the element data to get the value from
     * @param string $propertyKey Name of the property to get the value for.
     * @return mixed value or empty string
     */
    public function getPropertyValue(FlowData $flowData, string $elementKey, string $propertyKey)
    {
        if ($flowData->$elementKey) {
            // Get the elementData from flowData that contains required property.
            $elementData = $flowData->$elementKey;
        } else {
            //error_log(sprintf(Messages::ELEMENT_NOT_FOUND, $elementKey));
            return "";
        }

        $propertyKey = strtolower($propertyKey);
        if (isset($elementData->$propertyKey)) {
            $property = $elementData->$propertyKey;
        } else {
            //error_log(sprintf(Messages::PROPERTY_NOT_FOUND, $propertyKey, $elementKey));
            return "";
        }

        if ($property
            && $property->hasValue
            && !in_array($property->value, ["Unknown", "noValue"])
        ) {
            return $property->value;
        }

        return "";
    }
}
