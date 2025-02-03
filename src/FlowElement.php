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
 * A FlowElement is placed inside a Pipeline
 * It receives Evidence via a FlowData object
 * It uses this to optionally create ElementData on the FlowData
 * It has a unique dataKey which is used to extract data from the FlowData
 * Any errors in processing are caught in the FlowData's errors object.
 */
class FlowElement
{
    public string $dataKey;

    /**
     * @var array<string, array<string, mixed>>
     */
    public array $properties = [];

    /**
     * List of Pipelines the FlowElement has been added to.
     *
     * @var array<\fiftyone\pipeline\core\Pipeline>
     */
    public array $pipelines = [];

    /**
     * A default dummy constructor is needed for there are classes inheriting this
     * class deeper than 1-level of inheritance and calling parent::__construct() in their
     * explicit constructors. Unfortunately intermediates do not define their own __construct()
     * so the call propagates up to the base class FlowElement and fails.
     * Intermediates might define their own __construct() at some point, so we do not want to
     * remove parent::__construct() calls, rather add this one as a catch-all.
     *
     * */
    public function __construct() {}

    /**
     * General wrapper function that calls a FlowElement's processInternal method.
     */
    public function process(FlowData $flowData): void
    {
        $this->processInternal($flowData);
    }

    /**
     * Function for getting the FlowElement's EvidenceKeyFilter
     * Used by the filterEvidence method.
     */
    public function getEvidenceKeyFilter(): EvidenceKeyFilter
    {
        return new EvidenceKeyFilter();
    }

    /**
     * Filter FlowData evidence using the FlowElement's EvidenceKeyFilter.
     *
     * @return array<string, int|string>
     */
    public function filterEvidence(FlowData $flowData): array
    {
        $filter = $this->getEvidenceKeyFilter();

        return $filter->filterEvidence($flowData->evidence->getAll());
    }

    /**
     * Filter FlowData evidence using the FlowElement's EvidenceKeyFilter.
     */
    public function filterEvidenceKey(string $key): bool
    {
        $filter = $this->getEvidenceKeyFilter();

        return $filter->filterEvidenceKey($key);
    }

    /**
     * Callback called when an engine is added to a pipeline.
     */
    public function onRegistration(Pipeline $pipeline): void
    {
        return;
    }

    /**
     * Process FlowData - this is process function
     * is usually overridden by specific FlowElements to do their core work.
     */
    public function processInternal(FlowData $flowData): void
    {
        return;
    }

    /**
     * Get properties
     * Usually overridden by specific FlowElements.
     *
     * @return array<string, array<string, mixed>> Key-value array of properties
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Update a FlowElement's property list
     * This is used by elements that are only aware of their properties
     * at a later stage, such as cloud request based FlowElements or
     * FlowElements that change their properties later based on new datafiles.
     */
    public function updatePropertyList(): void
    {
        foreach ($this->pipelines as $pipeline) {
            $pipeline->updatePropertyDatabaseForFlowElement($this);
        }
    }
}
