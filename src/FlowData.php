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

declare(strict_types=1);

namespace fiftyone\pipeline\core;

/**
 * FlowData is created by a specific Pipeline
 * It collects evidence set by the user
 * It passes evidence to FlowElements in the Pipeline
 * These elements can return ElementData or populate an errors object.
 *
 * @property \fiftyone\pipeline\core\JsonBundlerElement $jsonbundler
 */
class FlowData
{
    public Pipeline $pipeline;
    public bool $stopped = false;
    public Evidence $evidence;

    /**
     * @var array<string, \fiftyone\pipeline\core\ElementData>
     */
    public array $data = [];
    public bool $processed = false;

    /**
     * @var array<string, \Throwable>
     */
    public array $errors = [];

    /**
     * Constructor for FlowData.
     *
     * @param \fiftyone\pipeline\core\Pipeline $pipeline Parent Pipeline
     */
    public function __construct(Pipeline $pipeline)
    {
        $this->pipeline = $pipeline;
        $this->evidence = new Evidence($this);
    }

    /**
     * Magic getter to allow $FlowData->FlowElementKey getting.
     */
    public function __get(string $flowElementKey): ElementData
    {
        return $this->get($flowElementKey);
    }

    /**
     * process function runs the process function on every attached FlowElement
     * allowing data to be changed based on evidence
     * This can only be run once per FlowData instance.
     *
     * @return static
     */
    public function process(): FlowData
    {
        if (!$this->processed) {
            foreach ($this->pipeline->flowElements as $flowElement) {
                if (!$this->stopped) {
                    // All errors are caught and stored in an errors array keyed by the
                    // FlowElement that set the error

                    try {
                        $flowElement->process($this);
                    } catch (\Throwable $e) {
                        $this->setError($flowElement->dataKey, $e);
                    }
                }
            }

            // Set processed flag to true. FlowData can only be processed once
            $this->processed = true;
        } else {
            $this->setError('global', new \Exception(Messages::FLOW_DATA_PROCESSED));
        }

        if (count($this->errors) != 0 && $this->pipeline->suppressProcessExceptions === false) {
            $exception = reset($this->errors);

            throw $exception;
        }

        return $this;
    }

    /**
     * Retrieve data by FlowElement object.
     */
    public function getFromElement(FlowElement $flowElement): ElementData
    {
        return $this->get($flowElement->dataKey);
    }

    /**
     * Retrieve data by FlowElement key.
     *
     * @throws \Exception
     */
    public function get(string $flowElementKey): ElementData
    {
        if (!isset($this->data[$flowElementKey])) {
            throw new \Exception(sprintf(Messages::NO_ELEMENT_DATA, $flowElementKey, join(',', array_keys($this->data))));
        }

        return $this->data[$flowElementKey];
    }

    /**
     * Set data (used by FlowElement).
     */
    public function setElementData(ElementData $data): void
    {
        $this->data[$data->flowElement->dataKey] = $data;
    }

    /**
     * Set error (should be keyed by FlowElement dataKey).
     */
    public function setError(string $key, \Throwable $error): void
    {
        $this->errors[$key] = $error;

        $logMessage = 'Error occurred during processing';

        if (!empty($key)) {
            $logMessage = $logMessage . ' of ' . $key . ". \n" . $error->getMessage();
        }

        $this->pipeline->log('error', $logMessage);
    }

    /**
     * Get an array evidence stored in the FlowData, filtered by
     * its FlowElements' EvidenceKeyFilters.
     *
     * @return array<string, int|string>
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
     * Stop processing any subsequent FlowElements.
     */
    public function stop(): void
    {
        $this->stopped = true;
    }

    /**
     * Get data from FlowElement based on property metadata.
     *
     * @param mixed $metaValue
     * @return array<string, mixed>
     */
    public function getWhere(string $metaKey, $metaValue): array
    {
        $metaKey = strtolower($metaKey);
        $metaValue = strtolower($metaValue);

        $keys = [];

        if (isset($this->pipeline->propertyDatabase[$metaKey][$metaValue])) {
            foreach ($this->pipeline->propertyDatabase[$metaKey][$metaValue] as $key => $value) {
                $keys[$key] = $value['flowElement'];
            }
        }

        $output = [];

        foreach ($keys as $key => $flowElement) {
            try {
                $output[$key] = $this->get($flowElement)->get($key);
            } catch (\Throwable $e) {
                continue;
            }
        }

        return $output;
    }
}
