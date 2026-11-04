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

namespace fiftyone\pipeline\core\tests;

use fiftyone\pipeline\core\CallbackUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the callback URL against the cases every implementation of the
 * JavaScript builder shares, in the same order, so that each one refuses and
 * writes the same URLs.
 */
class CallbackUrlTests extends TestCase
{
    private const ENDPOINT = '/51dpipeline/json';

    private static function url(string $protocol, string $host): ?CallbackUrl
    {
        return CallbackUrl::of($protocol, $host, self::ENDPOINT);
    }

    private function assertValid(
        string $expected,
        ?CallbackUrl $url,
        string $given
    ): void {
        $this->assertNotNull($url, $given . ' gave no URL');
        $this->assertTrue($url->valid, $given . ' was refused as ' . $url->url);
        $this->assertSame($expected, $url->url, $given);
    }

    private function assertInvalid(?CallbackUrl $url, string $given): void
    {
        $this->assertNotNull($url, $given . ' gave no URL');
        $this->assertFalse(
            $url->valid,
            $given . ' was accepted as ' . $url->url
        );
    }

    public function testAddsASingleSlash(): void
    {
        $this->assertValid(
            'https://example.com/51dpipeline/json',
            CallbackUrl::of('https', 'example.com', '51dpipeline/json'),
            'an endpoint without a slash'
        );
    }

    public function testKeepsASingleSlashOnTheEndpoint(): void
    {
        $this->assertValid(
            'https://example.com/51dpipeline/json',
            CallbackUrl::of('https', 'example.com', '/51dpipeline/json'),
            'an endpoint with a slash'
        );
    }

    public function testCollapsesADoubleSlash(): void
    {
        $this->assertValid(
            'http://example.com/json',
            CallbackUrl::of('http', 'example.com/', '/json'),
            'a slash on both'
        );
    }

    public function testMissingWhenAPartIsEmpty(): void
    {
        $this->assertNull(CallbackUrl::of('https', '', '/json'));
        $this->assertNull(CallbackUrl::of('', 'example.com', '/json'));
        $this->assertNull(CallbackUrl::of('https', 'example.com', ''));
        $this->assertNull(CallbackUrl::of('https', '  ', '/json'));
    }

    public static function provider_acceptedHosts(): array
    {
        return [
            ['EXAMPLE.com', 'https://example.com/51dpipeline/json'],
            ['example.com:8080', 'https://example.com:8080/51dpipeline/json'],
            ['example.com:', 'https://example.com:/51dpipeline/json'],
            ['example.com:65535', 'https://example.com:65535/51dpipeline/json'],
            ['a_b.com', 'https://a_b.com/51dpipeline/json'],
            ['-ab.com', 'https://-ab.com/51dpipeline/json'],
            ['127.0.0.1:80', 'https://127.0.0.1:80/51dpipeline/json'],
            ['[::1]', 'https://[::1]/51dpipeline/json'],
            ['[::1]:8080', 'https://[::1]:8080/51dpipeline/json'],
            ['[fe80::1%25eth0]', 'https://[fe80::1%25eth0]/51dpipeline/json'],
            ['user:pw@example.com', 'https://user:pw@example.com/51dpipeline/json'],
            ['u x@example.com', 'https://u%20x@example.com/51dpipeline/json'],
            ["u\tx@example.com", 'https://u%09x@example.com/51dpipeline/json'],
            ['example.com.', 'https://example.com./51dpipeline/json'],
            ['example.com/path', 'https://example.com/path/51dpipeline/json'],
            ['example.com?x', 'https://example.com?x/51dpipeline/json'],
            ['example.com/a b', 'https://example.com/a%20b/51dpipeline/json'],
            ['example.com/a%zzb', 'https://example.com/a%25zzb/51dpipeline/json'],
            ['example.com/a%41b', 'https://example.com/a%41b/51dpipeline/json'],
            ["example.com/\u{fc}", 'https://example.com/%C3%BC/51dpipeline/json'],
            ["example.com/a\nb", 'https://example.com/a%0Ab/51dpipeline/json'],
            [
                "example.com/a\u{2028}b",
                'https://example.com/a%E2%80%A8b/51dpipeline/json'
            ]
        ];
    }

    /**
     * @dataProvider provider_acceptedHosts
     */
    #[DataProvider('provider_acceptedHosts')]
    public function testAcceptsTheHost(string $host, string $expected): void
    {
        $this->assertValid($expected, self::url('https', $host), json_encode($host));
    }

    public static function provider_refusedHosts(): array
    {
        return [
            ['example.com:65536'],
            ['example.com:99999'],
            ['example.com:abc'],
            ['exa mple.com'],
            ["example.com');alert(1);//"],
            ['a"b.com'],
            ["a'b.com"],
            ['a<b.com'],
            ['::1'],
            ['[::1'],
            ['[v1.x]'],
            ['[1.2.3.4]'],
            ['a@b@example.com'],
            ['a..b.com'],
            ['%41.com'],
            ['a\\b.com'],
            ['a`b.com'],
            ['a{b}.com'],
            ['a|b.com'],
            ['a^b.com'],
            ['a(b).com'],
            ['a;b.com'],
            ['a,b.com'],
            ['a!b.com'],
            ['a*b.com'],
            ['a+b.com'],
            ['a=b.com'],
            ['a$b.com'],
            ['a&b.com'],
            ['a~b.com'],
            ["a\tb.com"],
            ["a\nb.com"],
            [' example.com'],
            ['example.com '],
            ["example.com:8080');alert(1);//"]
        ];
    }

    /**
     * @dataProvider provider_refusedHosts
     */
    #[DataProvider('provider_refusedHosts')]
    public function testRefusesTheHost(string $host): void
    {
        $this->assertInvalid(self::url('https', $host), json_encode($host));
    }

    public function testTheInvalidUrlIsTheOneBuilt(): void
    {
        $url = self::url('https', 'exa mple.com');
        $this->assertInvalid($url, 'exa mple.com');
        $this->assertSame('https://exa mple.com/51dpipeline/json', $url->url);
    }

    public function testChecksTheScheme(): void
    {
        foreach (['https', 'http', 'ftp', 'h+t-p.s'] as $protocol) {
            $url = self::url($protocol, 'example.com');
            $this->assertTrue($url->valid, $protocol);
        }
        $this->assertValid(
            'https://example.com/51dpipeline/json',
            self::url('HTTPS', 'example.com'),
            'HTTPS'
        );
        foreach (['1http', 'h t', "x');alert(1);//", 'a://b'] as $protocol) {
            $url = self::url($protocol, 'example.com');
            $this->assertInvalid($url, $protocol);
        }
    }

    public static function provider_hostsThatCouldEndTheString(): array
    {
        return [
            ["example.com/');alert(1);//"],
            ["example.com?');alert(1);//"],
            ["example.com#');alert(1);//"],
            ["u'x@example.com"],
            ['u\\x@example.com'],
            ['example.com/a\\b'],
            ['example.com/</script>']
        ];
    }

    /**
     * A quote, a backslash or a line break never reaches the script,
     * wherever in the URL it was given.
     *
     * @dataProvider provider_hostsThatCouldEndTheString
     */
    #[DataProvider('provider_hostsThatCouldEndTheString')]
    public function testNothingGivenCanEndTheString(string $host): void
    {
        $url = self::url('https', $host);
        $this->assertTrue($url->valid, $host);
        $ends = ["'", '"', '\\', '<', '>', "\n", "\r", "\u{2028}", "\u{2029}"];
        foreach ($ends as $c) {
            $this->assertStringNotContainsString($c, $url->url, $host);
        }
    }

    public function testTheEndpointIsEncoded(): void
    {
        $this->assertValid(
            'https://example.com/json%27x',
            CallbackUrl::of('https', 'example.com', "/json'x"),
            "the endpoint /json'x"
        );
    }

    public static function provider_hostsOutsideAscii(): array
    {
        return [
            ["b\u{fc}cher.de"],
            ["a\u{a0}b.com"],
            ["a\u{200b}b.com"],
            ["a\u{2028}b.com"]
        ];
    }

    /**
     * A host must be ASCII, so an internationalized name is given as
     * A-labels, and anything else outside ASCII is refused.
     *
     * @dataProvider provider_hostsOutsideAscii
     */
    #[DataProvider('provider_hostsOutsideAscii')]
    public function testRefusesTheHostOutsideAscii(string $host): void
    {
        $this->assertInvalid(self::url('https', $host), json_encode($host));
    }

    /**
     * Not one of the shared cases. A PHP string is bytes, so text that is
     * not UTF-8 is percent-encoded byte by byte.
     */
    public function testEncodesBytesThatAreNotUtf8(): void
    {
        $this->assertValid(
            'https://example.com/%FF/51dpipeline/json',
            self::url('https', "example.com/\xff"),
            'a byte that is not UTF-8'
        );
    }
}
