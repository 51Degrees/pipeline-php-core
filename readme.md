# 51Degrees PHP Pipeline Core

![51Degrees](https://51degrees.com/DesktopModules/FiftyOne/Distributor/Logo.ashx?utm_source=github&utm_medium=repository&utm_content=readme_main&utm_campaign=php-open-source "Data rewards the curious") **PHP Pipeline API**

[Developer Documentation](https://51degrees.com/documentation/index.html?utm_source=github&utm_medium=repository&utm_content=documentation&utm_campaign=php-open-source"developer documentation")

## Introduction
This project contains the core source code for the PHP implementation of the 51Degrees Pipeline API.

The Pipeline is a generic micro-services aggregation solution with the ability to add a range of 
51Degrees and/or custom plug ins (Engines) 

## Dependencies

The [tested versions](https://51degrees.com/documentation/_info__tested_versions.html) page shows 
the PHP versions that we currently test against. The software may run fine against other versions, 
but additional caution should be applied.

## Examples

To run the examples, you first need to install dependencies. Navigate to the repository root and execute:

```
composer install
```

This will create the vendor directory containing autoload.php. Now navigate to the examples 
directory and start a PHP server with the relevant file. For example:

```
PHP -S localhost:3000 CustomFlowElement.php
```

This will start a local web server listening on port 3000. Open your web browser and browse to http://localhost:3000/ to see the example in action.

There are several examples available that demonstrate how to make use of the Pipeline API in 
isolation. These are described in the table below.
If you want examples that demonstrate how to use 51Degrees products such as device detection, 
then these are available in the corresponding [repository](https://github.com/51Degrees/device-detection-php) 
and on our [website](http://51degrees.com/documentation/_examples__device_detection__index.html).

| Example                                | Description |
|----------------------------------------|-------------|
| Pipeline                               | Demonstrates adding some sample flow elements to a pipeline and processing some data with them. |
| CustomFlowElement                      | Demonstrates how to write your own flow element, which can then be added to a pipeline to perform processing. |

## Tests
To run the tests in this repository, make sure PHPUnit is installed then, in the root of this repo, call:
```
phpunit --fail-on-warning --display-warnings --log-junit test-results.xml
```

## Mustache template
`javascript-templates` is not a full-fledged PHP package installable via `composer`, however, it is shipped as a regularly updated, static dependency of this package.
