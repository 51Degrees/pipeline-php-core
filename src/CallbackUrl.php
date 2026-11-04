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
 * The callback URL the client script posts to for updated JSON. The protocol
 * and host usually come from the request, and the script holds the URL in a
 * single-quoted string, so the URL is checked before it is written into the
 * script. A URL that is not valid is refused. In the user information, in
 * anything after the host and port, and in the endpoint, the bytes a URL
 * cannot hold or that could end the string or the script are
 * percent-encoded.
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/pipeline-elements/javascript-builder.md#callback-url
 *
 * @internal
 */
class CallbackUrl
{
    /**
     * The largest port number.
     */
    private const MAX_PORT = 65535;

    /**
     * The most digits a port can have once its leading zeros are dropped.
     */
    private const MAX_PORT_DIGITS = 5;

    /**
     * The printable ASCII characters that are percent-encoded, besides the
     * control characters, the space and every byte outside ASCII.
     */
    private const ENCODED = '"\'<>\\^`{|}';

    private const HEX_DIGITS = '0123456789ABCDEFabcdef';

    /**
     * Text made only of the Unicode White_Space characters, which counts as
     * a part that is not set. The first range is tab, line feed, vertical
     * tab, form feed and carriage return.
     */
    private const BLANK = '/^[\t-\r \x{85}\x{A0}\x{1680}\x{2000}-\x{200A}' .
        '\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]*$/Du';

    /**
     * A scheme as RFC 3986 section 3.1 defines one, being a letter followed
     * by letters, digits, '+', '-' and '.'.
     */
    private const SCHEME = '/^[A-Za-z][A-Za-z0-9+.-]*$/D';

    /**
     * A label of a host name, being ASCII letters, digits, '-' and '_'.
     */
    private const LABEL = '/^[A-Za-z0-9_-]+$/D';

    /**
     * An IPv6 zone identifier, being unreserved characters as RFC 3986
     * section 2.3 defines them.
     */
    private const ZONE = '/^[A-Za-z0-9._~-]+$/D';

    /**
     * A port as it follows the host, being ':' and any number of digits.
     */
    private const PORT = '/^:[0-9]*$/D';

    /**
     * One group of an IPv6 address.
     */
    private const HEX_GROUP = '/^[0-9A-Fa-f]{1,4}$/D';

    /**
     * An IPv4 address as RFC 3986 section 3.2.2 defines one, being four
     * numbers from 0 to 255 with no leading zeros.
     */
    private const IPV4 = '/^(25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])' .
        '(\.(25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])){3}$/D';

    /**
     * Whether the URL can be written into the script.
     */
    public bool $valid;

    /**
     * The URL to write into the script when it is valid, and otherwise the
     * URL as it was built, for the log.
     */
    public string $url;

    private function __construct(bool $valid, string $url)
    {
        $this->valid = $valid;
        $this->url = $url;
    }

    /**
     * Builds the callback URL from its parts and checks it. One slash
     * separates the host and the endpoint, being added when neither has one
     * and removed when both do. The protocol and the host are written in
     * lower case and the rest as given, apart from the percent-encoding.
     *
     * @param mixed $protocol The protocol, such as https
     * @param mixed $host The host, which can start with user information and
     * end with a port, a path, a query or a fragment
     * @param mixed $endpoint The endpoint
     * @return null|self The callback URL, or null when the protocol, host or
     * endpoint is not set
     */
    public static function of($protocol, $host, $endpoint): ?self
    {
        if (self::isBlank($protocol) || self::isBlank($host) ||
            self::isBlank($endpoint)) {
            return null;
        }
        $hostHasSlash = substr($host, -1) === '/';
        $endpointHasSlash = $endpoint[0] === '/';
        if (!$hostHasSlash && !$endpointHasSlash) {
            $endpoint = '/' . $endpoint;
        } elseif ($hostHasSlash && $endpointHasSlash) {
            $endpoint = substr($endpoint, 1);
        }
        $built = $protocol . '://' . $host . $endpoint;
        if (preg_match(self::SCHEME, $protocol) !== 1) {
            return new self(false, $built);
        }
        $end = strcspn($host, '/?#');
        $authority = self::checkedAuthority(substr($host, 0, $end));
        if ($authority === null) {
            return new self(false, $built);
        }

        return new self(
            true,
            self::lower($protocol) . '://' . $authority .
                self::encoded(substr($host, $end)) . self::encoded($endpoint)
        );
    }

    /**
     * Whether a part of the URL is not set, being anything other than a
     * string, or a string that is empty or only whitespace.
     *
     * @param mixed $text
     */
    private static function isBlank($text): bool
    {
        return !is_string($text) || preg_match(self::BLANK, $text) === 1;
    }

    /**
     * The user information, host and port as they are written into the URL,
     * or null when they are not valid. The user information ends at the
     * first '@', so a second '@' is part of the host and refused.
     */
    private static function checkedAuthority(string $authority): ?string
    {
        $at = strpos($authority, '@');
        if ($at === false) {
            return self::checkedHostPort($authority);
        }
        $hostPort = self::checkedHostPort(substr($authority, $at + 1));
        if ($hostPort === null) {
            return null;
        }

        return self::encoded(substr($authority, 0, $at)) . '@' . $hostPort;
    }

    /**
     * The host and optional port in lower case, or null when they are not
     * valid. The host is an IPv6 address in brackets with an optional zone
     * identifier, or a name of labels separated by dots with a dot allowed
     * at the end. The port is ':' followed by digits up to 65535, or by
     * nothing.
     */
    private static function checkedHostPort(string $hostPort): ?string
    {
        if ($hostPort !== '' && $hostPort[0] === '[') {
            $close = strpos($hostPort, ']');
            if ($close === false) {
                return null;
            }
            $address = substr($hostPort, 1, $close - 1);
            $zone = strpos($address, '%25');
            if ($zone !== false) {
                if (preg_match(self::ZONE, substr($address, $zone + 3)) !== 1) {
                    return null;
                }
                $address = substr($address, 0, $zone);
            }
            if (!self::isIpv6($address)) {
                return null;
            }
            $host = substr($hostPort, 0, $close + 1);
            $port = substr($hostPort, $close + 1);
        } else {
            $end = strcspn($hostPort, ':');
            $host = substr($hostPort, 0, $end);
            $port = substr($hostPort, $end);
            $name = substr($host, -1) === '.' ? substr($host, 0, -1) : $host;
            foreach (explode('.', $name) as $label) {
                if (preg_match(self::LABEL, $label) !== 1) {
                    return null;
                }
            }
        }
        if ($port !== '' && !self::isPort($port)) {
            return null;
        }

        return self::lower($host) . $port;
    }

    /**
     * Whether the text is ':' followed by digits whose value is no more than
     * 65535, or by nothing.
     */
    private static function isPort(string $text): bool
    {
        if (preg_match(self::PORT, $text) !== 1) {
            return false;
        }
        $digits = ltrim(substr($text, 1), '0');

        return strlen($digits) <= self::MAX_PORT_DIGITS &&
            ($digits === '' || (int) $digits <= self::MAX_PORT);
    }

    /**
     * Whether the text is an IPv6 address as RFC 3986 section 3.2.2 defines
     * one. That is eight groups of one to four hexadecimal digits separated
     * by ':', where one '::' can stand for one or more groups of zeros and
     * the last two groups can be written as an IPv4 address.
     */
    private static function isIpv6(string $text): bool
    {
        $gap = strpos($text, '::');
        if ($gap === false) {
            return self::groups($text, true) === 8;
        }
        if (strpos($text, '::', $gap + 1) !== false) {
            return false;
        }
        $head = self::groups(substr($text, 0, $gap), false);
        $tail = self::groups(substr($text, $gap + 2), true);

        return $head >= 0 && $tail >= 0 && $head + $tail <= 7;
    }

    /**
     * The number of 16 bit groups in a run of groups separated by ':', or -1
     * when the run is not valid. An empty run has none, and an IPv4 address
     * at the end of the run, where one is allowed, counts as two.
     */
    private static function groups(string $run, bool $ipv4AtEnd): int
    {
        if ($run === '') {
            return 0;
        }
        $parts = explode(':', $run);
        $last = count($parts) - 1;
        $count = 0;
        foreach ($parts as $i => $part) {
            if (preg_match(self::HEX_GROUP, $part) === 1) {
                ++$count;
            } elseif ($ipv4AtEnd && $i === $last &&
                preg_match(self::IPV4, $part) === 1) {
                $count += 2;
            } else {
                return -1;
            }
        }

        return $count;
    }

    /**
     * The text with each byte percent-encoded where the byte is a control
     * character, a space, outside ASCII or one of ENCODED, and with a '%'
     * encoded where two hexadecimal digits do not follow it.
     */
    private static function encoded(string $text): string
    {
        $out = '';
        $length = strlen($text);
        for ($i = 0; $i < $length; ++$i) {
            $c = $text[$i];
            $b = ord($c);
            if ($c === '%') {
                $keep = $i + 2 < $length &&
                    strpos(self::HEX_DIGITS, $text[$i + 1]) !== false &&
                    strpos(self::HEX_DIGITS, $text[$i + 2]) !== false;
            } else {
                $keep = $b > 0x20 && $b < 0x7F &&
                    strpos(self::ENCODED, $c) === false;
            }
            $out .= $keep ? $c : sprintf('%%%02X', $b);
        }

        return $out;
    }

    /**
     * The text with its ASCII letters in lower case, whatever the locale.
     */
    private static function lower(string $text): string
    {
        return strtr(
            $text,
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'abcdefghijklmnopqrstuvwxyz'
        );
    }
}
