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

use Exception;

/**
 * An AspectPropertyValue is a wrapper for a value
 * It lets you check this wrapper has a value inside it
 * If not value is set, a specific no value message is returned
 */
class AspectPropertyValue
{
    /**
     * @var string|null the message to return if there is no value
     */
    public $noValueMessage = null;
    /**
     * @var bool whether this wrapper has a value
     */
    public $hasValue = false;
    /**
     * @var mixed
     */
    private $_value;

    /**
     * @param string|null $noValueMessage if there is no value, the reason for there not being one
     * @param mixed $value the value
     */
    public function __construct(?string $noValueMessage = null, $value = "noValue")
    {
        $this->_value = $value;
        $this->noValueMessage = $noValueMessage;
        $this->hasValue = !($noValueMessage || $value === "noValue");
    }

    public function __get($name)
    {
        if ($name === "value") {
            if ($this->hasValue) {
                return $this->_value;
            }

            throw new Exception($this->noValueMessage ?? "");
        }
    }

    public function __set($key, $value)
    {
        if ($key === "value") {
            $this->_value = $value;
            $this->hasValue = true;
        }
    }
}
