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

/*
 * @example Pipeline.php
 *
 * This example demonstrates how various FlowElements can be combined in a Pipeline and processed
 *
 */

use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\core\PipelineBuilder;

// Require some FlowElements to use in this example
require __DIR__ . '/FlowElementsForExamples.php';

// Two simple example FlowElements

$fe1 = new ExampleFlowElementA();
$fe2 = new ExampleFlowElementB();

// A FlowElement that stops processing (and prevents and subsequent elements in the Pipeline from processing)

$feStop = new StopFlowElement();

// Pipelines can log info, errors and other messages if you supply a logger to them, here is a basic logger example that stores the logs in an array

class ArrayLogger extends Logger
{
    public $log = [];

    public function logInternal(array $log): void
    {
        if ($log['message'] === 'test') {
            $this->log[] = $log;
        }
    }
}

// We make a Pipeline with our elements

$pipeline = (new PipelineBuilder())
    ->add($fe1)
    ->add($feStop)
    ->add($fe2)
    ->addLogger(new ArrayLogger('info'))
    ->build();

// We create FlowData which we will add evidence to

$flowData = $pipeline->createFlowData();

$flowData->evidence->set('header.user-agent', 'test');
$flowData->evidence->set('some.other-evidence', 'test');

// Add extra evidence available in a web request
$flowData->evidence->setFromWebRequest();

// Process the FlowData
$flowData->process();

// Get a property from the first FlowElement
$flowData->example1->exampleProperty1;

// Get all properties which match a condition
$flowData->getWhere('type', 'int');

// Get from an element by its object
$flowData->getFromElement($fe1)->exampleProperty1;
