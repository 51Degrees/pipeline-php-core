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

namespace fiftyone\pipeline\core;

/**
 * The JavaScriptBuilder aggregates JavaScript properties
 * from FlowElements in the Pipeline. This JavaScript also (when needed)
 * generates a fetch request to retrieve additional properties
 * populated with data from the client side
 * It depends on the JSON Bundler element (both are automatically
 * added to a Pipeline unless specifically removed) for its list of properties.
 * The results of the JSON Bundler should also be used in a user-specified
 * endpoint which retrieves the JSON from the client side.
 * The JavaScriptBuilder is constructed with a url for this endpoint.
 */
class JavascriptBuilderElement extends FlowElement
{
    public $settings;
    public $minify;

    public function __construct($settings = array())
    {
        $this->settings = [

            "_objName" => isset($settings["objName"]) ? $settings["objName"] : "fod",
            "_protocol" => isset($settings["protocol"]) ? $settings["protocol"] : null,
            "_host" => isset($settings["host"]) ? $settings["host"] : null,
            "_endpoint" => isset($settings["endpoint"]) ? $settings["endpoint"] : "",
            "_enableCookies" => isset($settings["enableCookies"]) ? $settings["enableCookies"] : true

        ];
        $this->minify = isset($settings["minify"]) ? $settings["minify"] : true;
    }

    public $dataKey = "javascriptbuilder";

    /**
     * The JavaScriptBuilder captures query string evidence and
     * headers for detecting whether the request is http or https
    */
    public function getEvidenceKeyFilter()
    {
        $filter = new EvidenceKeyFilter();

        $filter->filterEvidenceKey = function ($key) {
            if (strpos($key, "query.") !== false) {
                return true;
            }
    
            if ($key == "header.host" || $key == "header.protocol") {
                return true;
            }
    
            return false;
        };
        
        return $filter;
    }

    /**
     * The JavaScriptBundler collects client side javascript to serve.
     * @param FlowData FlowData
    */
    public function processInternal($flowData)
    {
        $m = new \Mustache_Engine();

        $vars = array();

        foreach ($this->settings as $key => $value) {
            $vars[$key] = $value;
        }

        $vars["_jsonObject"] = json_encode($flowData->jsonbundler->json);

        // Generate URL and autoUpdate params

        $protocol = $this->settings["_protocol"];
        $host = $this->settings["_host"];

        if (!isset($protocol) || trim($protocol) === '') {
            // Check if protocol is provided in evidence

            if ($flowData->evidence->get("header.protocol")) {
                $protocol = $flowData->evidence->get("header.protocol");
            }
        }
        if (!isset($protocol) || trim($protocol) === '') {
            $protocol = "https";
        }


        if (!isset($host) || trim($host) === '') {
            // Check if host is provided in evidence

            if ($flowData->evidence->get("header.host")) {
                $host = $flowData->evidence->get("header.host");
            }
        }

        $vars["_host"] = $host;
        $vars["_protocol"] = $protocol;

        if ($vars["_host"] && $vars["_protocol"] && $vars["_endpoint"]) {
            $vars["_url"] = $vars["_protocol"] . "://" . $vars["_host"] . $vars["_endpoint"];
            

            // Add query parameters to the URL

            $queryParams = $this->getEvidenceKeyFilter()->filterEvidence($flowData->evidence->getAll());
  
            $query = [];
 
            foreach ($queryParams as $param => $paramValue) {
                $paramKey = explode(".", $param)[1];

                $query[$paramKey] = $paramValue;
            }
  
            $urlQuery = http_build_query($query);
  
            // Does the URL already have a query string in it?
    
            if (strpos($vars["_url"], "?") === false) {
                $vars["_url"] .= "?";
            } else {
                $vars["_url"] .= "&";
            }
        
            $vars["_url"] .= $urlQuery;

            $vars["_updateEnabled"] = true;
        } else {
            $vars["_updateEnabled"] = false;
        }

        // Use results from device detection if available to determine
        // if the browser supports promises.
        
        if (property_exists($flowData, "device") && property_exists($flowData->device, "promise")) {
            $vars["_supportsPromises"] = $flowData->device->promise->value == true;
        } else {
            $vars["_supportsPromises"] = false;
        }

        // Check if any delayedproperties exist in the json

        $vars["_hasDelayedProperties"] = strpos($vars["_jsonObject"], "delayexecution") !== false;
         
        $output = $m->render(file_get_contents(__DIR__ . "/JavaScriptResource.mustache"), $vars);

		if($this->minify) {
            // Minify the output
            $output = \JShrink\Minifier::minify($output);
        }
        
        $data = new ElementDataDictionary($this, ["javascript" => $output]);

        $flowData->setElementData($data);

        return;
    }
}
