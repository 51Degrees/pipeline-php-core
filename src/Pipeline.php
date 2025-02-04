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
 * Pipeline holding a list of FlowElements for processing,
 * can create FlowData that will be passed through these,
 * collecting ElementData
 * Should be constructed through the PipelineBuilder class.
 */
class Pipeline
{
    /**
     * @var array<\fiftyone\pipeline\core\FlowElement>
     */
    public array $flowElements;

    /**
     * @var array<string, \fiftyone\pipeline\core\FlowElement>
     */
    public array $flowElementsList = [];
    public Logger $logger;

    /** @phpstan-ignore-next-line */
    public $metaDataStore;
    public bool $suppressProcessExceptions;

    /**
     * @var array<string, array<string, array<string, array<string, string>>>>
     */
    public array $propertyDatabase;

    /**
     * Pipeline constructor.
     *
     * @param array<\fiftyone\pipeline\core\FlowElement> $flowElements List of FlowElements
     * @param array<string, mixed> $settings
     */
    public function __construct(array $flowElements, array $settings)
    {
        $this->logger = $settings['logger'] ?? new Logger(null);

        /*
         * If true then pipeline will suppress exceptions added to FlowData errors,
         * otherwise will throw the exception occurred during the processing of the
         * first element.
         */
        $this->suppressProcessExceptions = (bool) ($settings['suppressProcessExceptions'] ?? false);

        $this->flowElements = $flowElements;

        $this->propertyDatabase = [];

        foreach ($flowElements as $flowElement) {
            $this->flowElementsList[$flowElement->dataKey] = $flowElement;

            $flowElement->pipelines[] = $this;

            $flowElement->onRegistration($this);

            $this->updatePropertyDatabaseForFlowElement($flowElement);
        }
    }

    /**
     * Create a FlowData based on what's in the Pipeline.
     */
    public function createFlowData(): FlowData
    {
        return new FlowData($this);
    }

    public function log(string $level, string $message): void
    {
        $this->logger->log($level, $message);
    }

    /**
     * Get a FlowElement by its name.
     */
    public function getElement(string $key): FlowElement
    {
        return $this->flowElementsList[$key];
    }

    /**
     * Update metadata store for a FlowElement based on its list of properties.
     */
    public function updatePropertyDatabaseForFlowElement(FlowElement $flowElement): void
    {
        $dataKey = $flowElement->dataKey;

        // First unset any properties stored by the FlowElement
        foreach ($this->propertyDatabase as $propertyValues) {
            foreach ($propertyValues as $propertyList) {
                foreach ($propertyList as $key => $info) {
                    if ($info['flowElement'] === $dataKey) {
                        unset($propertyList[$key]);
                    }
                }
            }
        }

        $properties = $flowElement->getProperties();

        foreach ($properties as $key => $property) {
            foreach ($property as $metaKey => $metaValue) {
                $metaKey = strtolower($metaKey);

                if (!isset($this->propertyDatabase[$metaKey])) {
                    $this->propertyDatabase[$metaKey] = [];
                }

                if (!is_string($metaValue)) {
                    continue;
                }

                $metaValue = strtolower($metaValue);

                if (!isset($this->propertyDatabase[$metaKey][$metaValue])) {
                    $this->propertyDatabase[$metaKey][$metaValue] = [];
                }

                $property['flowElement'] = $dataKey;

                $this->propertyDatabase[$metaKey][$metaValue][$key] = $property;
            }
        }
    }
}
