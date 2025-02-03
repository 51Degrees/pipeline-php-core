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
 * An AspectPropertyValue is a wrapper for a value
 * It lets you check this wrapper has a value inside it
 * If not value is set, a specific no value message is returned.
 *
 * @property mixed $value
 */
class AspectPropertyValue
{
    public ?string $noValueMessage = null;
    public bool $hasValue = false;

    /**
     * @var mixed
     */
    private $_value;

    /**
     * Constructor for AspectPropertyValue.
     *
     * @param null|string $noValueMessage Reason why the value is missing
     * @param mixed $value
     */
    public function __construct(?string $noValueMessage = null, $value = 'noValue')
    {
        if ($value !== 'noValue') {
            $this->value = $value;
            $this->noValueMessage = null;
            $this->hasValue = true;
        }

        if (!empty($noValueMessage)) {
            $this->hasValue = false;
            $this->noValueMessage = $noValueMessage;
        }
    }

    /**
     * Magic getter to access the value or throw an error with the no value message.
     *
     * @return mixed
     * @throws \Exception
     */
    public function __get(string $key)
    {
        if ($key === 'value') {
            if ($this->hasValue) {
                return $this->_value;
            }
            if (!empty($this->noValueMessage)) {
                throw new \Exception($this->noValueMessage);
            }
        }

        return null;
    }

    /**
     * @param mixed $value
     * @return void
     */
    public function __set(string $key, $value)
    {
        if ($key === 'value') {
            $this->_value = $value;
            $this->hasValue = true;
        }
    }
}
