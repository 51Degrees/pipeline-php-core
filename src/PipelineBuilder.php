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
 * A PipelineBuilder generates a Pipeline object
 * Before construction of the Pipeline, FlowElements are added to it
 * There are also options for how JavaScript is output from the Pipeline.
 */
class PipelineBuilder
{
    /**
     * @var array<\fiftyone\pipeline\core\Pipeline>
     */
    public array $pipelines;
    public bool $addJavaScriptBuilder;

    /**
     * @var array<string, mixed>
     */
    public array $javascriptBuilderSettings = [];
    public bool $useSetHeaderProperties;

    /**
     * @var array<\fiftyone\pipeline\core\FlowElement>
     */
    protected array $flowElements = [];

    /**
     * @var array<string, mixed>
     */
    protected array $settings = [];

    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(array $settings = [])
    {
        // List of Pipelines the FlowElement has been added to
        $this->pipelines = [];

        $this->addJavaScriptBuilder = (bool) ($settings['addJavaScriptBuilder'] ?? true);
        $this->javascriptBuilderSettings = $settings['javascriptBuilderSettings'] ?? [];
        $this->useSetHeaderProperties = (bool) ($settings['useSetHeaderProperties'] ?? true);
    }

    /**
     * Add FlowElement to be used in Pipeline.
     *
     * @return static
     */
    public function add(FlowElement $flowElement): PipelineBuilder
    {
        $this->flowElements[] = $flowElement;

        return $this;
    }

    /**
     * Build Pipeline once done.
     */
    public function build(): Pipeline
    {
        $this->flowElements = array_merge(
            $this->flowElements,
            $this->getJavaScriptElements(),
            $this->getSetHeaderElements()
        );

        return new Pipeline($this->flowElements, $this->settings);
    }

    /**
     * Add an instance of the logger class to the Pipeline.
     *
     * @return static
     */
    public function addLogger(Logger $logger): PipelineBuilder
    {
        $this->settings['logger'] = $logger;

        return $this;
    }

    /**
     * Build from a JSON configuration file
     * This JSON file should have the following structure:
     * `{
     * "PipelineOptions": {
     * "Elements": [
     *  {
     *    "BuilderName": // Name of element as in use statement,
     *    "BuildParameters": {
     *      // An object of parameters passed to the constructor
     *   }
     *  }]
     * }`.
     *
     * @param array<string, mixed>|string $fileOrConfig Filename of the config file to load config or associative array of config values
     */
    public function buildFromConfig($fileOrConfig): Pipeline
    {
        if (is_string($fileOrConfig)) {
            $config = json_decode(file_get_contents($fileOrConfig), true);
        } else {
            $config = $fileOrConfig;
        }

        foreach ($config['PipelineOptions']['Elements'] as $element) {
            /** @phpstan-var \fiftyone\pipeline\core\FlowElement $builder */
            $builder = $element['BuilderName'];

            if (isset($element['BuildParameters'])) {
                $flowElement = new $builder($element['BuildParameters']);
            } else {
                $flowElement = new $builder();
            }

            $this->flowElements[] = $flowElement;
        }

        return new Pipeline($this->flowElements, $this->settings);
    }

    /**
     * @return array<\fiftyone\pipeline\core\FlowElement>
     */
    private function getJavaScriptElements(): array
    {
        $flowElements = [];

        if ($this->addJavaScriptBuilder) {
            // Add JavaScript elements
            $flowElements[] = new SequenceElement();
            $flowElements[] = new JsonBundlerElement();
            $flowElements[] = new JavascriptBuilderElement($this->javascriptBuilderSettings);
        }

        return $flowElements;
    }

    /**
     * @return array<\fiftyone\pipeline\core\FlowElement>
     */
    private function getSetHeaderElements(): array
    {
        $flowElements = [];

        if ($this->useSetHeaderProperties) {
            // Add SetHeader elements
            $flowElements[] = new SetHeaderElement();
        }

        return $flowElements;
    }
}
