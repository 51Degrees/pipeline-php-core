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

use fiftyone\pipeline\core\ElementData;

/**
* An extension of ElementData with dictionary object storage / lookup
**/
class ElementDataDictionary extends ElementData
{
    public $contents;

    /**
    * Constructor for element data dictionary
    * @param FlowElement
    * @param mixed[] dicitonary contents
    */
    public function __construct($flowElement, $contents)
    {
        $this->contents = $contents;

        parent::__construct($flowElement);
    }
    
    /**
    * Get the values contained in the ElementData instance as a dictionary
    * of keys and values.
    * @return mixed[]
    */
    public function asDictionary()
    {
        return $this->contents;
    }

    /**
    * Internal getter for contents
    * @param string key
    * @return mixed
    */
    public function getInternal($key)
    {
        if (isset($this->contents[$key])) {
            return $this->contents[$key];
        } else {
            return null;
        }
    }
}
