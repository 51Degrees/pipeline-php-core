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

/**
  * A PipelineBuilder generates a Pipeline object
  * Before construction of the Pipeline, FlowElements are added to it
  * There are also options for how JavaScript is output from the Pipeline
*/
class PipelineBuilder
{
    public $pipelines;
    public $addJavaScriptBuilder;
    public $javascriptBuilderSettings;
    public $useSetHeaderProperties;

    public function __construct($settings = array())
    {

        // List of Pipelines the FlowElement has been added to
        $this->pipelines = [];

        if (isset($settings["addJavaScriptBuilder"])) {
            $this->addJavaScriptBuilder = $settings["addJavaScriptBuilder"];
        } else {
            $this->addJavaScriptBuilder = true;
        }
      
        if (isset($settings["javascriptBuilderSettings"])) {
            $this->javascriptBuilderSettings = $settings["javascriptBuilderSettings"];
        }

        if (isset($settings["useSetHeaderProperties"])) {
            $this->useSetHeaderProperties = $settings["useSetHeaderProperties"];
        } else {
            $this->useSetHeaderProperties = true;
        }
    }

    private function getJavaScriptElements()
    {
        $flowElements = [];
    
        if ($this->addJavaScriptBuilder) {
            // Add JavaScript elements
    
            $flowElements[] = new SequenceElement();
            $flowElements[] = new JsonBundlerElement();
    
            if (property_exists($this, "javascriptBuilderSettings")) {
                $flowElements[] = new JavascriptBuilderElement($this->javascriptBuilderSettings);
            } else {
                $flowElements[] = new JavascriptBuilderElement([]);
            }
        }
   
        return $flowElements;
    }

    private function getSetHeaderElements()
    {
        $flowElements = [];
    
        if ($this->useSetHeaderProperties) {
            
            // Add SetHeader elements   
            $flowElements[] = new SetHeaderElement();   
        }
   
        return $flowElements;
    }

    /**
     * array of FlowElements
    **/
    protected $flowElements = array();
    protected $settings = array();

    /**
     * Add FlowElement to be used in Pipeline
     * @param FlowElement
    */
    public function add($flowElement)
    {
        $this->flowElements[] = $flowElement;

        return $this;
    }

    /**
     * Build Pipeline once done
     * @return Pipeline
    */
    public function build()
    {
        $this->flowElements = $this->generateElements();

        return new Pipeline($this->flowElements, $this->settings);
    }

    private function generateElements()
    {
        $javascriptElements = $this->getJavaScriptElements();
        $setHeaderElements = $this->getSetHeaderElements();

        $javascriptBuilderElementFilter = function ($element) {
            return !empty($element->settings['_endpoint']);
        };

        return array(
            $this->findElement([], "fiftyone\pipeline\devicedetection\DeviceDetectionOnPremise"),
            $this->findElement($javascriptElements, SequenceElement::class),
            $this->findElement($javascriptElements, JsonBundlerElement::class),
            $this->findElement($javascriptElements, JavascriptBuilderElement::class, $javascriptBuilderElementFilter),
            $this->findElement($setHeaderElements, SetHeaderElement::class)
        );
    }

    private function findElement($elements, $class, $additionalFilter = null)
    {
        $typeFilter = function ($element) use ($class) {
            return $element instanceof $class;
        };

        $result = array_filter(array_merge($elements, $this->flowElements), $typeFilter);

        if ($additionalFilter !== null) {
            $result = array_filter($result, $additionalFilter);
        }

        return reset($result);
    }

    /**
     * Add an instance of the logger class to the Pipeline
     * @param Logger
     * @return PipelineBuilder
    */
    public function addLogger($logger)
    {
        $this->settings["logger"] = $logger;

        return $this;
    }

    /**
    * Build from a JSON configuration file
    * This JSON file should look like the following
    * `{
    * "PipelineOptions": {
    * "Elements": [
    *  {
    *    "BuilderName": // Name of element as in use statement,
    *    "BuildParameters": {
    *      // An object of parameters passed to the constructor
    *   }
    *  }]
    * }`
    * @param string file name of the file to load config, or alternatively
    * pass a config object already read from file
    */
    public function buildFromConfig($fileOrConfig)
    {
        if (is_string($fileOrConfig))
        {
            $config = json_decode(file_get_contents($fileOrConfig), true);
        }
        else
        {
            $config = $fileOrConfig;
        }

        foreach ($config["PipelineOptions"]["Elements"] as $element) {
            $builder = $element["BuilderName"];

            if (isset($element["BuildParameters"])) {
                $flowElement = new $builder($element["BuildParameters"]);
            } else {
                $flowElement = new $builder();
            }
            
            $this->flowElements[] = $flowElement;
        }

        return $this;
    }
}
