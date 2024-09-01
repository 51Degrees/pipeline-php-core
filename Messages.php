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
 * Constants used for messages returned to the user. These can be error
 * messages, or otherwise. Messages which require formatting will contain
 * format characters e.g. %s.
 */
class Messages {
    /**
     * Error message thrown when there is no matching element in the FlowData.
     */
    const NO_ELEMENT_DATA = "There is no element data for '%s' against "
        . "this flow data. Available element data keys are: '%s'";
    const NO_ELEMENT_DATA_NULL = "There is no element data for '%s' against "
        . "this flow data.'";
	const PASS_KEY_VALUE = "Must pass key and value";
	const FLOW_DATA_PROCESSED = "FlowData already processed";

    /**
     * Property does not start with SetHeader. This takes the name of a property
     * as a format argument.
     */
    const PROPERTY_NOT_SET_HEADER = "Property Name '%s' does not start with 'SetHeader'.";
    
    /**
     * Property Name is not in the valid format. This takes the property name 
     * as format argument.
     */
    const WRONG_PROPERTY_FORMAT = 
        "Property Name '%s' is not in the expected format i.e. SetHeader[Component][HeaderName]. ";

    /**
     * Element not found in flowData. This takes the element datakey 
     * as format argument.
     */
    const ELEMENT_NOT_FOUND = 
        "Element '%s' is not present in the FlowData. ";

    /**
     * Property not found in flowData. This takes the element datakey
     * and property names as format arguments.
     */ 
    const PROPERTY_NOT_FOUND = 
        "Property '%s' is not present in the FlowData against '%s' ElementData. ";
}
