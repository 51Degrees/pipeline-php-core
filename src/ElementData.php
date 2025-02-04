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
 * Stores information created by a FlowElement based on FlowData.
 * Stored in FlowData.
 */
class ElementData
{
    public FlowElement $flowElement;

    public function __construct(FlowElement $flowElement)
    {
        $this->flowElement = $flowElement;
    }

    /**
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->get($key);
    }

    /**
     * Get a value from the ElementData contents
     * This calls the ElementData class' (often overridden) getInternal method.
     *
     * @param string $key Property name
     * @return mixed
     */
    public function get(string $key)
    {
        return $this->getInternal($key);
    }

    /**
     * Get the values contained in the ElementData instance as a dictionary
     * of keys and values.
     *
     * @return array<string, mixed>
     */
    public function asDictionary(): array
    {
        return [];
    }

    /**
     * Helper method to get property as a string.
     */
    public function getAsString(string $key): string
    {
        return strval($this->get($key));
    }

    /**
     * Helper method to get property as a float.
     */
    public function getAsFloat(string $key): float
    {
        return floatval($this->get($key));
    }

    /**
     * Helper method to get property as an int.
     */
    public function getAsInteger(string $key): int
    {
        return intval($this->get($key));
    }

    /**
     * Called by the get() method
     * Returns the requested property from the data.
     *
     * @param string $key Property name
     * @return mixed
     */
    protected function getInternal(string $key)
    {
        return;
    }
}
