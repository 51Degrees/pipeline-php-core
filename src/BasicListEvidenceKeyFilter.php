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
 * An instance of EvidenceKeyFilter that uses a simple array of keys
 * Evidence not using these keys is filtered out
 */
class BasicListEvidenceKeyFilter extends EvidenceKeyFilter
{
    /**
     * @var mixed[]
     */
    private $list;

    /**
     * @param mixed[] $list an array of keys to keep
     */
    public function __construct(array $list)
    {
        $this->list = $list;
    }

    /**
     * @param string $key key to check in the filter
     * @return boolean is this key in the filter's keys list?
     */
    public function filterEvidenceKey(string $key): bool
    {
        $key = strtolower($key);
        foreach ($this->list as $evidenceKey) {
            if ($key === strtolower($evidenceKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the internal list of evidence keys in this filter.
     * @return mixed[] evidence keys
     */
    public function getList(): array
    {
        return $this->list;
    }
}
