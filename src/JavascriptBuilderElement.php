<?php
/* *********************************************************************
 * This Original Work is copyright of 51 Degrees Mobile Experts Limited.
 * Copyright 2026 51 Degrees Mobile Experts Limited, Davidson House,
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

use JShrink\Minifier;

/**
 * The JavaScriptBuilder aggregates JavaScript properties
 * from FlowElements in the Pipeline. This JavaScript also (when needed)
 * generates a fetch request to retrieve additional properties
 * populated with data from the client side
 * It depends on the JSON Bundler element (both are automatically
 * added to a Pipeline unless specifically removed) for its list of properties.
 * The results of the JSON Bundler should also be used in a user-specified
 * endpoint which retrieves the JSON from the client side.
 * The JavaScriptBuilder is constructed with a url for this endpoint.
 */
class JavascriptBuilderElement extends FlowElement
{
    /**
     * Evidence keys the rendered script is not configured with. The script
     * appends both to its own request itself, so naming them here as well
     * would put the session id into the record the script keeps of a
     * request's inputs. That record decides whether a later page view in the
     * same tab can be served from the cached response, and a session id
     * changes on every page view, so a record holding one could never match.
     * The .NET builder, the reference for this behaviour, excludes the same
     * two.
     */
    private const EXCLUDED_PARAMETERS = ['query.session-id', 'query.sequence'];

    /**
     * Names the object cannot have. These are the reserved words of the
     * language, including those reserved only in strict mode and the literals
     * null, true and false, plus the three global values a top level var
     * cannot replace, so the object would silently never be created, and the
     * name of the constructor the script itself defines.
     */
    private const RESERVED_WORDS = [
        'await', 'break', 'case', 'catch', 'class', 'const', 'continue',
        'debugger', 'default', 'delete', 'do', 'else', 'enum', 'export',
        'extends', 'false', 'finally', 'for', 'function', 'if', 'implements',
        'import', 'in', 'instanceof', 'interface', 'let', 'new', 'null',
        'package', 'private', 'protected', 'public', 'return', 'static',
        'super', 'switch', 'this', 'throw', 'true', 'try', 'typeof', 'var',
        'void', 'while', 'with', 'yield',
        'Infinity', 'NaN', 'undefined', 'fiftyoneDegreesManager'
    ];

    /**
     * @var array<string, mixed>
     */
    public array $settings;
    public bool $minify;
    public string $dataKey = 'javascriptbuilder';

    /**
     * @param array<string, mixed> $settings 'objName' is the name of the
     * client side object ('fod' by default). The page request can ask for a
     * different name with the 'query.fod-js-object-name' evidence. A name
     * must be a JavaScript identifier (ASCII letters, digits, '_' and '$',
     * not starting with a digit) and not a reserved word. An invalid
     * 'objName' throws an \InvalidArgumentException, and an invalid
     * requested name is ignored with a warning logged.
     */
    public function __construct(array $settings = [])
    {
        $objName = $settings['objName'] ?? 'fod';
        if (!self::isValidObjectName($objName)) {
            throw new \InvalidArgumentException(
                "The JavaScript builder's objName setting must be a " .
                "JavaScript identifier (ASCII letters, digits, '_' and '$', " .
                'not starting with a digit) and not a reserved word.'
            );
        }

        $this->settings = [
            '_objName' => $objName,
            '_protocol' => $settings['protocol'] ?? null,
            '_host' => $settings['host'] ?? null,
            '_endpoint' => $settings['endpoint'] ?? '',
            '_enableCookies' => $settings['enableCookies'] ?? true
        ];

        $this->minify = $settings['minify'] ?? true;

        parent::__construct();
    }

    /**
     * Whether a name can be used as the client side object's name. The name
     * is written into the script as a variable name, a session storage key
     * and a property name, all without any escaping, so a name that is not
     * a plain JavaScript identifier would break the script or change what it
     * does.
     *
     * @param mixed $name
     */
    public static function isValidObjectName($name): bool
    {
        return is_string($name) &&
            preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/D', $name) === 1 &&
            !in_array($name, self::RESERVED_WORDS, true);
    }

    /**
     * The JavaScriptBuilder captures query string evidence and
     * headers for detecting whether the request is http or https.
     */
    public function getEvidenceKeyFilter(): EvidenceKeyFilter
    {
        $filter = new EvidenceKeyFilter();

        $filter->filterEvidenceKey = function ($key) {
            if (strpos($key, 'query.') !== false) {
                return true;
            }

            if ($key == 'header.host' || $key == 'header.protocol') {
                return true;
            }

            return false;
        };

        return $filter;
    }

    /**
     * The JavaScriptBundler collects client side javascript to serve.
     */
    public function processInternal(FlowData $flowData): void
    {
        $vars = [];

        foreach ($this->settings as $key => $value) {
            $vars[$key] = $value;
        }

        $vars['_jsonObject'] = json_encode($flowData->jsonbundler->json);

        // Generate URL and autoUpdate params
        $protocol = $this->settings['_protocol'];
        $host = $this->settings['_host'];

        if ($protocol === null || trim($protocol) === '') {
            // Check if protocol is provided in evidence
            if ($flowData->evidence->get('header.protocol')) {
                $protocol = $flowData->evidence->get('header.protocol');
            }
        }

        if ($protocol === null || trim($protocol) === '') {
            $protocol = 'https';
        }

        if ($host === null || trim($host) === '') {
            // Check if host is provided in evidence
            if ($flowData->evidence->get('header.host')) {
                $host = $flowData->evidence->get('header.host');
            }
        }

        $vars['_host'] = $host;
        $vars['_protocol'] = $protocol;

        $params = $this->getEvidenceKeyFilter()->filterEvidence($flowData->evidence->getAll());

        if ($vars['_host'] && $vars['_protocol'] && $vars['_endpoint']) {
            $vars['_url'] = $vars['_protocol'] . '://' . $vars['_host'] . $vars['_endpoint'];

            // Add query parameters to the URL
            $query = [];

            foreach ($params as $param => $paramValue) {
                $paramKey = explode('.', $param)[1];
                $query[$paramKey] = $paramValue;
            }

            $urlQuery = http_build_query($query);

            // Does the URL already have a query string in it?
            if (strpos($vars['_url'], '?') === false) {
                $vars['_url'] .= '?';
            } else {
                $vars['_url'] .= '&';
            }

            $vars['_url'] .= $urlQuery;

            $vars['_updateEnabled'] = true;
        } else {
            $vars['_updateEnabled'] = false;
        }

        // Use results from device detection if available to determine
        // if the browser supports promises.
        if (property_exists($flowData, 'device') && property_exists($flowData->device, 'promise')) {
            $vars['_supportsPromises'] = $flowData->device->promise->value == true;
        } else {
            $vars['_supportsPromises'] = false;
        }

        // Check if any delayedproperties exist in the json
        $vars['_hasDelayedProperties'] = strpos($vars['_jsonObject'], 'delayexecution') !== false;
        $vars['_sessionId'] = $flowData->evidence->get('query.session-id');
        $vars['_sequence'] = $flowData->evidence->get('query.sequence');

        $enableCookies = $flowData->evidence->get('query.fod-js-enable-cookies');
        if ($enableCookies !== null) {
            $vars['_enableCookies'] = strtolower($enableCookies) === 'true';
        }

        // The page request can ask for a different object name. A name that
        // is not a valid identifier is ignored, and the configured name is
        // used, because it would be written into the script as given. The
        // requested text is left out of the warning so that it cannot reach
        // the log either.
        $requestedName = $flowData->evidence->get(Constants::EVIDENCE_OBJECT_NAME);
        if ($requestedName !== null) {
            if (self::isValidObjectName($requestedName)) {
                $vars['_objName'] = $requestedName;
            } else {
                $flowData->pipeline->log(
                    'warning',
                    'The requested JavaScript object name (' .
                    Constants::EVIDENCE_OBJECT_NAME . ') is not a valid ' .
                    "JavaScript identifier, so the configured name '" .
                    $this->settings['_objName'] . "' is used."
                );
            }
        }

        // Left out after the URL above is built, because the record of a
        // request's inputs is taken from these parameters and a session id
        // that differs on every page view would stop it ever matching, so the
        // cached response would be thrown away and the snippets would run
        // again on every page.
        $jsParams = [];
        foreach ($params as $param => $paramValue) {
            if (in_array($param, self::EXCLUDED_PARAMETERS, true)) {
                continue;
            }
            $paramKey = explode('.', $param)[1];
            $jsParams[$paramKey] = $paramValue;
        }

        $vars['_parameters'] = json_encode($jsParams);

        $output = (new \Mustache_Engine())->render(
            file_get_contents(__DIR__ . '/../javascript-templates/JavaScriptResource.mustache'),
            $vars
        );

        if ($this->minify) {
            // Minify the output
            $output = Minifier::minify($output);
        }

        $data = new ElementDataDictionary($this, ['javascript' => $output]);

        $flowData->setElementData($data);
    }
}
