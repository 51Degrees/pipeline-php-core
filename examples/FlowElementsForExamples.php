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

use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\BasicListEvidenceKeyFilter;

// Two simple FlowElements

class ExampleFlowElementA extends FlowElement
{
    public $dataKey = "example1";
    public function processInternal($FlowData)
    {
        $data = new ElementDataDictionary($this, array("exampleProperty1" => 5));

        $FlowData->setElementData($data);
    }

    public $properties = array(
        "exampleProperty1" => array(
            "type" => "int"
        )
    );

    public function getEvidenceKeyFilter()
    {
        return new BasicListEvidenceKeyFilter(["header.user-agent"]);
    }
}

class ExampleFlowElementB extends FlowElement
{
    public $dataKey = "example2";

    public function processInternal($FlowData)
    {
        $data = new ElementDataDictionary($this, array("exampleProperty2" => 7));

        $FlowData->setElementData($data);
    }

    public $properties = array(
        "exampleProperty2" => array(
            "type" => "int"
        )
    );

    public function getEvidenceKeyFilter()
    {
        return new BasicListEvidenceKeyFilter(["header.user-agent"]);
    }
}


if (!class_exists("errorFlowElement")) {
// A FlowElement that triggers an error

    class ErrorFlowElement extends FlowElement
    {
        public $dataKey = "error";

        public function processInternal($FlowData)
        {
            throw new Exception("Something went wrong");
        }

        public function getEvidenceKeyFilter()
        {
            return new BasicListEvidenceKeyFilter(["header.user-agent"]);
        }
    }
}


if (!class_exists("stopFlowElement")) {
    // A FlowElement that stops processing

    class StopFlowElement extends FlowElement
    {
        public $dataKey = "stop";

        public function processInternal($FlowData)
        {
            $FlowData->stop();
        }

        public function getEvidenceKeyFilter()
        {
            return new BasicListEvidenceKeyFilter(["header.user-agent"]);
        }
    }
}
