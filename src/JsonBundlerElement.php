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

use Throwable;

/**
 * The JSONBundler aggregates all properties from FlowElements into a JSON object
 * It is used for retrieving via an endpoint from the client
 * side via the JavaScriptBuilder and also used inside the
 * JavaScriptBuilder itself to pass properties to the client side.
 * Both this and the JavaScriptBuilder element are automatically
 * added to a Pipeline unless specifically ommited in the PipelineBuilder
 */
class JsonBundlerElement extends FlowElement
{
    /**
     * @var string
     */
    public $dataKey = "jsonbundler";
    /**
     * @var array
     */
    private $propertyCache = [];

    /**
     * The JSONBundler extracts all properties from a FlowData and serializes them into JSON
     * @param FlowData $flowData
     */
    public function processInternal($flowData)
    {
        // Get every property on every FlowElement
        // Storing JavaScript properties in an extra section

        $output = [
            "javascriptProperties" => []
        ];

        if (is_array($this->propertyCache) && count($this->propertyCache)) {
            $propertyCacheSet = true;
        } else {
            $propertyCacheSet = false;
            $this->propertyCache = [];
        }

        foreach ($flowData->pipeline->flowElements as $flowElement) {
            $dataKey = $flowElement->dataKey;
            if ($dataKey === "jsonbundler"
                || $dataKey === "sequence"
                || $dataKey === "javascriptbuilder"
                || $dataKey === "set-headers"
            ) {
                continue;
            }

            $properties = $flowElement->getProperties();

            if (!$propertyCacheSet) {
                $delayExecutionList = [];
                $delayedEvidenceProperties = [];

                // Loop over all properties and see if any have delay execution set to true

                foreach ($properties as $propertyKey => $propertyMeta) {
                    if ($propertyMeta["delayexecution"] ?? false) {
                        $delayExecutionList[] = $propertyKey;
                    }
                }

                // Loop over all properties again and see if any have evidenceproperties which
                // have delayedExecution set to true

                foreach ($properties as $propertyKey => $propertyMeta) {
                    if (isset($propertyMeta["evidenceproperties"])) {
                        $delayedEvidencePropertiesList = array_filter(
                            $propertyMeta["evidenceproperties"],
                            function ($evidenceProperty) use ($delayExecutionList) {
                                return in_array($evidenceProperty, $delayExecutionList);
                            }
                        );

                        if (count($delayedEvidencePropertiesList)) {
                            $delayedEvidenceProperties[$propertyKey] = array_map(
                                function ($property) use ($dataKey) {
                                    return $dataKey . '.' . $property;
                                },
                                $delayedEvidencePropertiesList
                            );
                        }
                    }
                }

                $this->propertyCache[$dataKey] = [
                    "delayExecutionList" => $delayExecutionList,
                    "evidenceProperties" => $delayedEvidenceProperties
                ];
            }

            $propertyCache = $this->propertyCache[$dataKey];

            // Create empty area for FlowElement properties to go
            $output[$dataKey] = [];

            foreach ($properties as $propertyKey => $property) {
                $value = null;
                $nullReason = "Unknown";

                // Check if property has delayed execution and set in JSON if yes

                if (in_array($propertyKey, $propertyCache["delayExecutionList"])) {
                    $output[$dataKey][strtolower($propertyKey) . "delayexecution"] = true;
                }

                // // Check if property has any delayed execution evidence properties and set in JSON if yes

                if (isset($propertyCache["evidenceProperties"][$propertyKey])) {
                    $output[$dataKey][strtolower($propertyKey) . 'evidenceproperties'] =
                        $propertyCache["evidenceProperties"][$propertyKey];
                }

                try {
                    $valueContainer = $flowData->get($dataKey)->get($propertyKey);

                    // Check if value is of the aspect property value type

                    if (is_object($valueContainer) && property_exists($valueContainer, "hasValue")) {
                        // Check if it has a value
                        if ($valueContainer->hasValue) {
                            $value = $valueContainer->value;
                        } else {
                            $nullReason = $valueContainer->noValueMessage;
                        }
                    } else {
                        // Standard value
                        $value = $valueContainer;
                    }
                } catch (Throwable $e) {
                    // Catching missing property exceptions and other errors
                    continue;
                }

                $output[strtolower($dataKey)][strtolower($propertyKey)] = $value;
                if ($value == null) {
                    $output[strtolower($dataKey)][strtolower($propertyKey) . "nullreason"] = $nullReason;
                }

                $sequence = $flowData->evidence->get("query.sequence");

                if (!$sequence || $sequence < 10) {
                    // Cloud properties come back as capitalized
                    // TODO change this, but for now

                    if (isset($property["Type"])) {
                        $property["type"] = $property["Type"];
                    }

                    if (isset($property["type"]) && strtolower($property["type"]) === "javascript") {
                        $output["javascriptProperties"][] = strtolower($dataKey) . "." . strtolower($propertyKey);
                    }
                }
            }
        }

        $data = new ElementDataDictionary($this, ["json" => $output]);

        $flowData->setElementData($data);
    }
}
