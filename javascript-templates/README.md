## Purpose

Store shared (mustache) templates to be used by the implementation of `JavaScriptBuilderElement` and other components across the languages. 

## Background

The [language-independent specification](https://github.com/51Degrees/specifications) describes how JavaScriptBuilderElement is used [here](https://github.com/51Degrees/specifications/blob/36ff732360acb49221dc81237281264dac4eb897/pipeline-specification/pipeline-elements/javascript-builder.md). The mechanics is: javascript file is requested from the server (on-premise web integration) and is created from this mustache template by the JavaScriptBuilderElement. It then collects more evidence, sends it to the server and upon response calls a callback function providing the client with more precise device data.

