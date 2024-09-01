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
  * A FlowElement is placed inside a Pipeline
  * It receives Evidence via a FlowData object
  * It uses this to optionally create ElementData on the FlowData
  * It has a unique dataKey which is used to extract data from the FlowData
  * Any errors in processing are caught in the FlowData's errors object
**/
class FlowElement
{
    public $dataKey;
    public $properties = [];
    // List of Pipelines the FlowElement has been added to
    public $pipelines = [];

    /**
     * General wrapper function that calls a FlowElement's processInternal method
     * @param FlowData
    */
    public function process($flowData)
    {
        return $this->processInternal($flowData);
    }

    /**
     * Function for getting the FlowElement's EvidenceKeyFilter
     * Used by the filterEvidence method
     * @return EvidenceKeyFilter
    */
    public function getEvidenceKeyFilter()
    {
        return new EvidenceKeyFilter();
    }

    /**
     * Filter FlowData evidence using the FlowElement's EvidenceKeyFilter
     * @param FlowData
     * @return mixed
    */
    public function filterEvidence($flowData)
    {
        $filter = $this->getEvidenceKeyFilter();

        return $filter->filterEvidence($flowData->evidence->getAll());
    }

    /**
     * Filter FlowData evidence using the FlowElement's EvidenceKeyFilter
     * @param FlowData
     * @return mixed
    */
    public function filterEvidenceKey($key)
    {
        $filter = $this->getEvidenceKeyFilter();

        return $filter->filterEvidenceKey($key);
    }

    /**
     * Callback called when an engine is added to a pipeline
     * @param Pipeline
     * @return void
    */
    public function onRegistration($pipeline)
    {
        return $pipeline;
    }

    /**
     * Process FlowData - this is process function
     * is usually overriden by specific FlowElements to do their core work
     * @param FlowData
    */
    public function processInternal($flowData)
    {
        return true;
    }

    /**
     * Get properties
     * is usually overriden by specific FlowElements
     * @return array key value array of properties
    */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * Update a FlowElement's property list
     * This is used by elements that are only aware of their properites
     * at a later stage, such as cloud request based FlowElements or
     * FlowElements that change their properties later based on new datafiles
    */
    public function updatePropertyList()
    {
        foreach ($this->pipelines as $pipeline) {
            $pipeline->updatePropertyDatabaseForFlowElement($this);
        }
    }
}
