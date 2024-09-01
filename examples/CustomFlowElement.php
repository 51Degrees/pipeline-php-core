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

/**
* @example CustomFlowElement.php
*
* This example demonstrates the creation of a custom flow element. In this case
* the FlowElement takes the results of a client side form collecting
* date of birth, setting this as evidence on a FlowData object to calculate
* a person's starsign. The FlowElement also serves additional JavaScript
* which gets a user's geolocation and saves the latitude as a cookie.
* This latitude is also then passed in to the FlowData to calculate if
* a person is in the northern or southern hemispheres.

*/

include(__DIR__ . "/../vendor/autoload.php");

use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\core\BasicListEvidenceKeyFilter;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\ElementDataDictionary;

// Function to get star sign from month and day
function getStarSign($month, $day)
{
    if (($month == 1 && $day <= 20) || ($month == 12 && $day >= 22)) {
        return "capricorn";
    } elseif (($month == 1 && $day >= 21) || ($month == 2 && $day <= 18)) {
        return "aquarius";
    } elseif (($month == 2 && $day >= 19) || ($month == 3 && $day <= 20)) {
        return "pisces";
    } elseif (($month == 3 && $day >= 21) || ($month == 4 && $day <= 20)) {
        return "aries";
    } elseif (($month == 4 && $day >= 21) || ($month == 5 && $day <= 20)) {
        return "taurus";
    } elseif (($month == 5 && $day >= 21) || ($month == 6 && $day <= 20)) {
        return "gemini";
    } elseif (($month == 6 && $day >= 22) || ($month == 7 && $day <= 22)) {
        return "cancer";
    } elseif (($month == 7 && $day >= 23) || ($month == 8 && $day <= 23)) {
        return "leo";
    } elseif (($month == 8 && $day >= 24) || ($month == 9 && $day <= 23)) {
        return "virgo";
    } elseif (($month == 9 && $day >= 24) || ($month == 10 && $day <= 23)) {
        return "libra";
    } elseif (($month == 10 && $day >= 24) || ($month == 11 && $day <= 22)) {
        return "scorpio";
    } elseif (($month == 11 && $day >= 23) || ($month == 12 && $day <= 21)) {
        return "sagittarius";
    }
};

//! [class]
//! [declaration]
class AstrologyFlowElement extends FlowElement
{
    //! [declaration]

    // datakey used to categorise data coming back from this
    // FlowElement in a Pipeline
    public $dataKey = "astrology";

    // The processInternal function is the core working of a FlowElement.
    // It takes FlowData, reads evidence and returns data.
    public function processInternal($FlowData)
    {
        $result = [];

        
        // Get the date of birth from the query string (submitted through
        // a form on the client side)
        $dateOfBirth = $FlowData->evidence->get("query.dateOfBirth");
        
        if ($dateOfBirth) {
            $dateOfBirth = explode("-", $dateOfBirth);

            $month = $dateOfBirth[1];
            $day = $dateOfBirth[2];


            $result["starSign"] = getStarSign($month, $day);
        }

        // Serve some JavaScript to the user that will be used to save
        // a cookie with the user's latitude in it
        $result["getLatitude"] = "navigator.geolocation.getCurrentPosition(function(position) {
            document.cookie = \"latitude=\" + position.coords.latitude;
            loadHemisphere();
        });";

        // Get the latitude from the above cookie
        $latitude = $FlowData->evidence->get("cookie.latitude");

        // Calculate the hemisphere
        if ($latitude) {
            $result["hemisphere"] = $latitude > 0 ? "Northern" : "Southern";
        }


        $data = new ElementDataDictionary($this, $result);

        $FlowData->setElementData($data);
    }

    public $properties = array(
        "starSign" => array(
            "type" => "string",
            "description" => "the user's starsign"
        ),
        "hemisphere" => array(
            "type" => "string",
            "description" => "the user's hemisphere"
        ),
        "getLatitude" => array(
            "type" => "javascript",
            "description" => "JavaScript used to get a user's latitude"
        )
    );

    public function getEvidenceKeyFilter()
    {

        // A filter (in this case a basic list) stating which evidence
        // the FlowElement is interested in
        return new BasicListEvidenceKeyFilter(["cookie.latitude", "query.dateOfBirth"]);
    }
}

//! [class]
//! [usage]

// Add some callback settings for the page to make a request with extra evidence from the client side, in this case the same url with an extra query string.

$javascriptBuilderSettings = array(
    "host" => "localhost:3000",
    "protocol" => "http",
    "endpoint" => "/?json"
);

// Make the Pipeline and add the element we want to it

$Pipeline = (new PipelineBuilder(["javascriptBuilderSettings"=>$javascriptBuilderSettings]))->add(new AstrologyFlowElement())->build();

$FlowData = $Pipeline->createFlowData();

// Add any information from the request (headers, cookies and additional
// client side provided information)

$FlowData->evidence->setFromWebRequest();

// Process the FlowData

$FlowData->process();

// The client side JavaScript calls back to this page

if (isset($_GET["json"])) {
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode($FlowData->jsonbundler->json);

    return;
}

// Generate the HTML for the form that gets a user's starsign

$output = "";

$output .= "<h1>Starsign</h1>";

$output .= "<form><label for='dateOfBirth'>Date of birth</label><input type='date' name='dateOfBirth' id='dateOfBirth'><input type='submit'></form>";

// Add the results if they're available


if ($FlowData->astrology->starSign) {
    $output .= "<p>Your starsign is " . $FlowData->astrology->starSign . "</p>";
}

$output .= "<div id='hemispheretext'>";

if ($FlowData->astrology->hemisphere) {
    $output .= "<p>Look at the " . $FlowData->astrology->hemisphere . " hemisphere stars tonight!</p>";
}

$output .= "</div>";

$output .= "<script>";

// This function will fire when the JSON data object is updated
// with information from the server.
// The sequence is:
// 1. Response contains JavaScript property 'getLatitude' that gets executed on the client
// 2. This triggers another call to the webserver that passes the location as evidence
// 3. The web server responds with new JSON data that contains the hemisphere based on the location.
// 4. The JavaScript integrates the new JSON data and fires the onChange callback below.

$output .= $FlowData->javascriptbuilder->javascript;

$output .= 'loadHemisphere = function() {
            fod.complete(function (data) {  
                if(data.astrology.hemisphere) {          
                    var para = document.createElement("p");
                    var text = document.createTextNode("Look at the " + 
                        data.astrology.hemisphere + " hemisphere stars tonight");
                    para.appendChild(text);

                    var element = document.getElementById("hemispheretext");
                    var child = element.lastElementChild;  
                    while (child) { 
                        element.removeChild(child); 
                        child = element.lastElementChild; 
                    } 
                    element.appendChild(para);
                }
            })};';

$output .= "</script>";

// Return the full output to the page

echo $output;
//! [usage]
