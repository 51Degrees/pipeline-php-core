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

class Utils
{
    /**
     * Set response headers in the response object (e.g. Accept-CH).
     *
     * @param \fiftyone\pipeline\core\FlowData $flowData A processed FlowData instance to get the response header values from
     */
    public static function setResponseHeader(FlowData $flowData): void
    {
        $setHeaderElementKey = Constants::SETHEADER_ELEMENT_KEY;
        $setHeaderDataKey = Constants::SETHEADER_DATA_KEY;

        // Get response headers dictionary containing key values to be set in response
        $responseHeaderDict = $flowData->{$setHeaderElementKey}->{$setHeaderDataKey};

        foreach ($responseHeaderDict as $responseKey => $responseValue) {
            $responseValue = str_replace(',', ', ', $responseValue);

            if (strlen($responseValue) > 0) {
                header("{$responseKey}: {$responseValue}");
            }
        }
    }
}
