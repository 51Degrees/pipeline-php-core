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

/**
 * Storage of evidence on a FlowData object.
 */
class Evidence
{
    protected FlowData $flowData;

    /**
     * @var array<string, int|string>
     */
    protected array $evidence = [];

    /**
     * Evidence container constructor.
     *
     * @param \fiftyone\pipeline\core\FlowData $flowData Parent FlowData
     */
    public function __construct(FlowData $flowData)
    {
        $this->flowData = $flowData;
    }

    /**
     * If a flow element can use the key then add the key value pair to the
     * evidence collection.
     *
     * @param int|string $value
     */
    public function set(string $key, $value): void
    {
        $keep = false;

        foreach ($this->flowData->pipeline->flowElements as $flowElement) {
            if ($flowElement->filterEvidenceKey($key)) {
                $keep = true;
                break;
            }
        }

        if ($keep) {
            $this->evidence[$key] = $value;
        }
    }

    /**
     * Helper function to set multiple pieces of evidence from an array.
     *
     * @param array<string, int|string> $array
     */
    public function setArray($array): void
    {
        if (!is_array($array)) {
            $this->flowData->setError('core', new \Exception(Messages::PASS_KEY_VALUE));

            return;
        }

        foreach ($array as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Extract evidence from a web request
     * No argument version automatically reads from current request using the
     * $_SERVER, $_COOKIE, $_GET and $_POST globals.
     *
     * @param null|array<string, string> $server Key-value pairs for the HTTP headers
     * @param null|array<string, string> $cookies Key-value pairs for the cookies
     * @param null|array<string, string> $query Key-value pairs for the form parameters
     */
    public function setFromWebRequest(?array $server = null, ?array $cookies = null, ?array $query = null): void
    {
        if ($server === null) {
            $server = $_SERVER;
        }

        if ($cookies === null) {
            $cookies = $_COOKIE;
        }

        if ($query === null) {
            $query = static::queryFromRequest($server);
        }

        $evidence = [];

        foreach ($server as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));

                $key = strtolower($key);

                $evidence['header.' . $key] = $value;
            }
        }

        foreach ($cookies as $key => $value) {
            $evidence['cookie.' . $key] = $value;
        }

        foreach ($query as $key => $value) {
            $evidence['query.' . $key] = $value;
        }

        if (isset($server['SERVER_ADDR'])) {
            $evidence['server.host-ip'] = $server['SERVER_ADDR'];
        }

        if (isset($server['REMOTE_ADDR'])) {
            $evidence['server.client-ip'] = $server['REMOTE_ADDR'];
        }

        // Protocol

        if (isset($server['HTTPS']) && ($server['HTTPS'] == 'on' || $server['HTTPS'] == 1) || isset($server['HTTP_X_FORWARDED_PROTO']) && $server['HTTP_X_FORWARDED_PROTO'] == 'https') {
            $protocol = 'https';
        } else {
            $protocol = 'http';
        }

        // Override protocol with referer header if set

        if (isset($server['HTTP_REFERER']) && $server['HTTP_REFERER']) {
            $protocol = parse_url($server['HTTP_REFERER'], PHP_URL_SCHEME);
        }

        $evidence['header.protocol'] = $protocol;

        $this->setArray($evidence);
    }

    /**
     * The form parameters of the current request, with the names as the
     * browser sent them.
     *
     * PHP replaces a dot or a space in a parameter name with an underscore
     * when it fills $_GET and $_POST, so a request carrying 'id.usage'
     * arrives as 'id_usage' and the cloud service, which reads 'id.usage',
     * never sees it. The raw query string and, for a form encoded body, the
     * raw body are read again here so those names survive. A name PHP left
     * alone is taken from $_GET and $_POST as before, and where a name was
     * changed the changed one is dropped in favour of the name that was
     * sent.
     *
     * A name written in the array form, such as 'a[b]', is left to PHP,
     * which builds an array from it. A multipart body cannot be read a
     * second time, so a dotted name sent in one keeps the underscore PHP
     * gave it.
     *
     * @param array<string, string> $server Key-value pairs for the HTTP headers
     * @return array<string, int|string>
     */
    protected static function queryFromRequest(array $server): array
    {
        // Merge the GET and POST parameters favoring the GET keys if there
        // are keys that conflict.
        $query = array_merge($_POST, $_GET);

        $sent = [];
        if (static::isFormEncodedPost($server)) {
            $sent = static::parseFormEncoded(static::rawBody());
        }

        // The query string is applied last for the same reason the GET
        // parameters win above.
        foreach (static::parseFormEncoded((string) ($server['QUERY_STRING'] ?? '')) as $name => $value) {
            $sent[$name] = $value;
        }

        foreach ($sent as $name => $value) {
            $asPhpGivesIt = static::asPhpGivesIt($name);
            if ($asPhpGivesIt === $name) {
                continue;
            }
            unset($query[$asPhpGivesIt]);
            $query[$name] = $value;
        }

        return $query;
    }

    /**
     * Whether the request carries a form encoded body, which is the only
     * body that can be read again as name and value pairs.
     *
     * @param array<string, string> $server Key-value pairs for the HTTP headers
     */
    protected static function isFormEncodedPost(array $server): bool
    {
        if (strtoupper((string) ($server['REQUEST_METHOD'] ?? '')) !== 'POST') {
            return false;
        }

        $type = strtolower((string) ($server['CONTENT_TYPE'] ?? ''));

        return strpos($type, 'application/x-www-form-urlencoded') === 0;
    }

    /**
     * The body of the request exactly as it arrived. Overridden in tests,
     * because php://input cannot be written to.
     */
    protected static function rawBody(): string
    {
        $body = file_get_contents('php://input');

        return $body === false ? '' : $body;
    }

    /**
     * Reads form encoded text into name and value pairs, decoding both and
     * keeping every name exactly as it was sent. A repeated name takes the
     * last value, as PHP does. A name in the array form is skipped, because
     * PHP builds an array for it and this is not trying to replace that.
     *
     * @return array<string, string>
     */
    protected static function parseFormEncoded(string $raw): array
    {
        $values = [];

        foreach (explode('&', $raw) as $pair) {
            if ($pair === '') {
                continue;
            }

            $split = strpos($pair, '=');
            $name = urldecode($split === false ? $pair : substr($pair, 0, $split));

            if ($name === '' || strpos($name, '[') !== false) {
                continue;
            }

            $values[$name] = $split === false
                ? ''
                : urldecode(substr($pair, $split + 1));
        }

        return $values;
    }

    /**
     * The name PHP gives a parameter in $_GET and $_POST, which is the name
     * that was sent with each dot and space replaced by an underscore.
     */
    protected static function asPhpGivesIt(string $name): string
    {
        return str_replace(['.', ' '], '_', $name);
    }

    /**
     * Get a piece of evidence by key.
     *
     * @return null|int|string
     */
    public function get(string $key)
    {
        return $this->evidence[$key] ?? null;
    }

    /**
     * Get all evidence.
     *
     * @return array<string, int|string>
     */
    public function getAll(): array
    {
        return $this->evidence;
    }
}
