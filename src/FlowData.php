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
use Throwable;

/**
 * FlowData is created by a specific Pipeline
 * It collects evidence set by the user
 * It passes evidence to FlowElements in the Pipeline
 * These elements can return ElementData or populate an errors object
 */
class FlowData
{
    /**
     * @var Pipeline parent Pipeline
     */
    public $pipeline;
    /**
     * @var bool
     */
    public $stopped = false;
    /**
     * @var Evidence
     */
    public $evidence;
    /**
     * @var ElementData[]
     */
    public $data;
    /**
     * @var bool
     */
    public $processed;
    /**
     * @var array
     */
    public $errors = [];

    /**
     * @param Pipeline|null $pipeline parent Pipeline
     */
    public function __construct(?Pipeline $pipeline)
    {
        $this->pipeline = $pipeline;

        $this->evidence = new Evidence($this);
    }

    /**
     * process function runs the process function on every attached FlowElement
     * allowing data to be changed based on evidence
     * This can only be run once per FlowData instance
     * @return self
     */
    public function process(): FlowData
    {
        if (!$this->processed) {
            foreach ($this->pipeline->flowElements as $flowElement) {
                if ($this->stopped) {
                    break;
                }

                // All errors are caught and stored in an errors array keyed by the
                // FlowElement that set the error

                try {
                    $flowElement->process($this);
                } catch (Throwable $e) {
                    $this->setError($flowElement->dataKey, $e);
                }
            }

            // Set processed flag to true. FlowData can only be processed once
            $this->processed = true;
        } else {
            $this->setError("global", new Exception(Messages::FLOW_DATA_PROCESSED));
        }

        if (count($this->errors) != 0 && $this->pipeline->suppressProcessExceptions === false) {
            throw reset($this->errors);
        }

        return $this;
    }

    /**
     * Set error (should be keyed by FlowElement dataKey)
     * @param string $key
     * @param Throwable|string $error
     */
    public function setError(string $key, $error): void
    {
        $this->errors[$key] = $error;

        $logMessage = "Error occurred during processing";
        if (!empty($key)) {
            $logMessage = $logMessage . " of " . $key . ". \n" . $error;
        }

        $this->pipeline->log("error", $logMessage);
    }

    /**
     * Retrieve data by FlowElement object
     * @param FlowElement $flowElement
     * @return \stdClass|null
     */
    public function getFromElement(FlowElement $flowElement)
    {
        return $this->get($flowElement->dataKey);
    }

    /**
     * Retrieve data by FlowElement key
     * @param string $flowElementKey
     * @return \stdClass|null
     */
    public function get(string $flowElementKey)
    {
        if (isset($this->data[$flowElementKey])) {
            return $this->data[$flowElementKey];
        }

        if (is_null($this->data)) {
            throw new Exception(
                sprintf(Messages::NO_ELEMENT_DATA_NULL, $flowElementKey)
            );
        }

        throw new Exception(
            sprintf(
                Messages::NO_ELEMENT_DATA,
                $flowElementKey,
                join(",", array_keys($this->data))
            )
        );
    }

    /**
     * Magic getter to allow $FlowData->FlowElementKey getting
     * @param string $flowElementKey
     * @return \stdClass|null
     */
    public function __get(string $flowElementKey)
    {
        return $this->get($flowElementKey);
    }

    /**
     * Set data (used by FlowElement)
     * @param ElementData $data
     */
    public function setElementData(ElementData $data): void
    {
        $this->data[$data->flowElement->dataKey] = $data;
    }

    /**
     * Get an array evidence stored in the FlowData, filtered by
     * its FlowElements' EvidenceKeyFilters
     * @return array
     */
    public function getEvidenceDataKey(): array
    {
        $requestedEvidence = [];

        foreach ($this->pipeline->flowElements as $flowElement) {
            $requestedEvidence = array_merge($requestedEvidence, $flowElement->filterEvidence($this));
        }

        return $requestedEvidence;
    }

    /**
     * Stop processing any subsequent FlowElements
     * @return void
     */
    public function stop(): void
    {
        $this->stopped = true;
    }

    /**
     * Get data from FlowElement based on property metadata
     * @param string $metaKey
     * @param mixed $metaValue
     * @return array
     */
    public function getWhere(string $metaKey, $metaValue): array
    {
        $metaKey = strtolower($metaKey);
        $metaValue = strtolower($metaValue);

        $keys = [];

        if (isset($this->pipeline->propertyDatabase[$metaKey][$metaValue])) {
            foreach ($this->pipeline->propertyDatabase[$metaKey][$metaValue] as $key => $value) {
                $keys[$key] = $value["flowElement"];
            }
        }

        $output = [];

        if (isset($keys)) {
            foreach ($keys as $key => $flowElement) {
                // First check if FlowElement has any data set

                if (isset($this->data[$flowElement])) {
                    $data = $this->get($flowElement);

                    if ($data) {
                        try {
                            $output[$key] = $data->get($key);
                        } catch (Throwable $e) {
                            continue;
                        }
                    }
                }
            }
        }

        return $output;
    }
}
