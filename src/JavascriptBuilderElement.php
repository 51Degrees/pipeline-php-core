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
     * The sequence the script is rendered with when the evidence holds no
     * usable value.
     */
    private const DEFAULT_SEQUENCE = 1;

    /**
     * The largest sequence, which is the largest 32 bit signed integer.
     */
    public const MAX_SEQUENCE = 2147483647;

    /**
     * @var array<string, mixed>
     */
    public array $settings;
    public bool $minify;
    public string $dataKey = 'javascriptbuilder';

    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(array $settings = [])
    {
        $this->settings = [
            '_objName' => $settings['objName'] ?? 'fod',
            '_protocol' => $settings['protocol'] ?? null,
            '_host' => $settings['host'] ?? null,
            '_endpoint' => $settings['endpoint'] ?? '',
            '_enableCookies' => $settings['enableCookies'] ?? true
        ];

        $this->minify = $settings['minify'] ?? true;

        parent::__construct();
    }

    /**
     * A sequence as a positive 32 bit integer, or null when the value cannot
     * be used as one.
     *
     * @param mixed $value The query.sequence evidence, or null
     */
    public static function parseSequence($value): ?int
    {
        if (is_int($value)) {
            return $value >= 1 && $value <= self::MAX_SEQUENCE ? $value : null;
        }

        if (!is_string($value) || preg_match('/^\s*\+?[0-9]+\s*$/D', $value) !== 1) {
            return null;
        }

        // The range is checked on the digits, because casting a number that
        // is too large gives the largest integer rather than failing. Every
        // leading zero is dropped first, so no digits left means zero, which
        // is not a sequence.
        $digits = ltrim(ltrim(trim($value, " \t\n\r\v\f"), '+'), '0');
        if ($digits === '' || strlen($digits) > 10 ||
            (strlen($digits) === 10 && strcmp($digits, '2147483647') > 0)) {
            return null;
        }

        return (int) $digits;
    }

    /**
     * The sequence to render into the script.
     *
     * The template writes the sequence as bare code (var sequence = ...;), so
     * anything other than a whole number would stop the script parsing, or
     * change what it does. A pipeline without a SequenceElement passes the
     * query string's value, or nothing at all, straight through, and the
     * pipeline specification requires 1 in that case. This builder goes a
     * little further than the specification currently says and renders 1
     * for any value that is not a positive 32 bit integer, because the
     * script counts its own requests up from this number and cannot use
     * zero or a negative one.
     *
     * @param mixed $value The query.sequence evidence, or null
     */
    public static function getSequence($value): int
    {
        return self::parseSequence($value) ?? self::DEFAULT_SEQUENCE;
    }

    /**
     * The session id to render into the script.
     *
     * The template writes the session id inside quotes without any escaping,
     * so a session id that is not 1 to 64 ASCII letters, digits and hyphens
     * is rendered as an empty string, whether it came from the
     * SequenceElement or from the query string. This is the rule proposed
     * for the pipeline specification in 51Degrees/specifications pull
     * request 31, which is still open.
     *
     * @param mixed $value The query.session-id evidence, or null
     */
    public static function getSessionId($value): string
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9-]{1,64}$/D', $value) === 1
            ? $value : '';
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

        $endpoint = $vars['_endpoint'];

        if ($vars['_host'] && $vars['_protocol'] && $endpoint) {
            // One slash between the host and the endpoint, as the .NET
            // builder does.
            $endpointHasSlash = $endpoint[0] === '/';
            $hostHasSlash = substr($vars['_host'], -1) === '/';
            if (!$endpointHasSlash && !$hostHasSlash) {
                $endpoint = '/' . $endpoint;
            } elseif ($endpointHasSlash && $hostHasSlash) {
                $endpoint = substr($endpoint, 1);
            }

            // The URL carries no query string. The parameters, the session
            // id and the sequence all go in the request body, and a web
            // request's query string wins over its body when the evidence is
            // read back, so values rendered here would override the newer
            // values the script puts in the body.
            $vars['_url'] = $vars['_protocol'] . '://' . $vars['_host'] . $endpoint;

            $vars['_updateEnabled'] = true;
        } else {
            $vars['_updateEnabled'] = false;
        }

        // Use results from device detection if available to determine
        // if the browser fully supports promises and supports fetch, as the
        // .NET builder does. Without them the script creates no promise and
        // uses XMLHttpRequest.
        $vars['_supportsPromises'] = $this->deviceValueIs($flowData, 'promise', 'Full');
        $vars['_supportsFetch'] = $this->deviceValueIs($flowData, 'fetch', true);

        // Check if any delayedproperties exist in the json
        $vars['_hasDelayedProperties'] = strpos($vars['_jsonObject'], 'delayexecution') !== false;
        // Both are written into the script, so each always has a safe value.
        // The session id is written inside quotes and is empty when absent or
        // not safe, and the sequence is written as bare code, so it is always
        // a positive number.
        $vars['_sessionId'] = self::getSessionId($flowData->evidence->get('query.session-id'));
        $vars['_sequence'] = self::getSequence($flowData->evidence->get('query.sequence'));

        $enableCookies = $flowData->evidence->get('query.fod-js-enable-cookies');
        if ($enableCookies !== null) {
            $vars['_enableCookies'] = strtolower($enableCookies) === 'true';
        }

        $vars['_parameters'] = json_encode(
            (object) $this->getParameters($flowData->evidence->getAll())
        );

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

    /**
     * The parameters the script is configured with, which it puts into the
     * request body as they are. Only query evidence is taken, and the key is
     * everything after the 'query.' prefix, so 'query.id.usage' becomes
     * 'id.usage'. The session id and the sequence are left out, because the
     * record of a request's inputs is taken from these parameters and a
     * session id that differs on every page view would stop it ever
     * matching, so the cached response would be thrown away and the
     * snippets would run again on every page. Keys and values are encoded
     * as the .NET builder encodes them.
     *
     * @param array<string, mixed> $evidence All the evidence
     * @return array<string, string>
     */
    private function getParameters(array $evidence): array
    {
        $parameters = [];
        foreach ($evidence as $key => $value) {
            if (
                stripos($key, 'query.') !== 0 ||
                in_array($key, self::EXCLUDED_PARAMETERS, true) ||
                !is_scalar($value)
            ) {
                continue;
            }
            $name = substr($key, strlen('query.'));
            $parameters[self::encode($name)] = self::encode((string) $value);
        }

        return $parameters;
    }

    /**
     * Encode a value as .NET's WebUtility.UrlEncode does, which is PHP's
     * urlencode with '!', '*', '(' and ')' left alone.
     */
    private static function encode(string $value): string
    {
        return str_replace(
            ['%21', '%2A', '%28', '%29'],
            ['!', '*', '(', ')'],
            urlencode($value)
        );
    }

    /**
     * True where the device property has exactly the value given. False
     * where there is no device data, no such property or no value.
     *
     * @param mixed $expected
     */
    private function deviceValueIs(FlowData $flowData, string $property, $expected): bool
    {
        try {
            $value = $flowData->get('device')->get($property);

            return $value instanceof AspectPropertyValue &&
                $value->hasValue &&
                $value->value === $expected;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
