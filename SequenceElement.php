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
  * The SequenceElement stores session data regarding
  * requests for client side JavaScript from the JavaScript
  * created by a Pipeline's JavaScriptBuilder
  * If a Pipeline is constructed with the JavaScript elements
  * enabled this is added automatically along with the JavaScriptBuilder
  * and JSONBundler.
**/
class SequenceElement extends FlowElement
{
    public $dataKey = "sequence";

    /**
     * The SequenceElement uses query.sequence and query.session-id evidence
    */
    public function getEvidenceKeyFilter()
    {
        $filter = new EvidenceKeyFilter();

        $filter->filterEvidenceKey = function ($key) {
            if ($key == "query.sequence" || $key == "query.session-id") {
                return true;
            }

            return false;
        };

        return $filter;
    }

    /**
     * The SequenceElement stores session data for requests for JavaScript
     * @param {FlowData} FlowData
    */
    public function processInternal($flowData)
    {
        if ($flowData->evidence->get("query.session-id")) {
            // Get current sequence number
      
            $sequence = $flowData->evidence->get("query.sequence");
      
            if ($sequence) {
                $sequence = intval($sequence);
            } else {
                $sequence = 1;
            }
      
            $flowData->evidence->set("query.sequence", $sequence + 1);
        } else {
            $flowData->evidence->set(
                "query.session-id",
                uniqid()
            );
      
            $flowData->evidence->set("query.sequence", 1);
        }
    }
}
