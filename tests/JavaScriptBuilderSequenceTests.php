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

use fiftyone\pipeline\core\JavascriptBuilderElement;
use fiftyone\pipeline\core\JsonBundlerElement;
use fiftyone\pipeline\core\PipelineBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The script must parse whatever the pipeline holds. The sequence is written
 * into the script as bare code and the session id inside quotes, and a
 * pipeline built without a SequenceElement, for example from a configuration
 * file that does not list one, passes the query string's values, or nothing
 * at all, straight to the builder.
 *
 * The script is checked with "node --check", which needs Node on the path.
 * Every GitHub hosted runner has it.
 */
class JavaScriptBuilderSequenceTests extends TestCase
{
    /**
     * Render the script. Without the sequence element the pipeline has only
     * the JSON bundler and the JavaScript builder.
     */
    private static function render(
        array $evidence = [],
        bool $minify = false,
        bool $sequenceElement = false
    ): string {
        $settings = ['javascriptBuilderSettings' => ['minify' => $minify]];
        if (!$sequenceElement) {
            $settings['addJavaScriptBuilder'] = false;
        }
        $builder = new PipelineBuilder($settings);
        if (!$sequenceElement) {
            $builder->add(new JsonBundlerElement());
            $builder->add(new JavascriptBuilderElement(['minify' => $minify]));
        }
        $flowData = $builder->build()->createFlowData();
        foreach ($evidence as $key => $value) {
            $flowData->evidence->set($key, $value);
        }
        $flowData->process();

        return $flowData->javascriptbuilder->javascript;
    }

    private static function lines(string $script, string $name): array
    {
        $found = [];
        foreach (preg_split('/\r?\n/', $script) as $line) {
            if (preg_match('/^\s*var ' . $name . '\s*=/', $line) === 1) {
                $found[] = trim($line);
            }
        }

        return $found;
    }

    private function assertParses(string $script): void
    {
        $base = tempnam(sys_get_temp_dir(), 'sequence');
        unlink($base);
        $path = $base . '.js';
        file_put_contents($path, $script);
        try {
            $process = @proc_open(
                ['node', '--check', $path],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes
            );
            if (!is_resource($process)) {
                $this->markTestSkipped('Node is needed to parse the script');
            }
            stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $code = proc_close($process);
        } finally {
            unlink($path);
        }
        $this->assertSame(0, $code, 'the rendered script does not parse: ' . $err);
    }

    public static function provider_minify(): array
    {
        return ['unminified' => [false], 'minified' => [true]];
    }

    /**
     * With no SequenceElement and no query evidence the script is rendered
     * with sequence 1 and an empty session id, and parses.
     * @dataProvider provider_minify
     */
    #[DataProvider('provider_minify')]
    public function testScriptParsesWithoutSequenceElement(bool $minify): void
    {
        $script = self::render([], $minify);

        $this->assertParses($script);
        if (!$minify) {
            $this->assertSame(['var sequence = 1;'], self::lines($script, 'sequence'));
            $this->assertSame(['var sessionId = "";'], self::lines($script, 'sessionId'));
        }
    }

    public static function provider_unusableSequence(): array
    {
        return [
            'text' => ['abc'],
            'statement' => ['1;b'],
            'empty' => [''],
            'decimal' => ['1.5'],
            'hexadecimal' => ['0x10'],
            'too large' => ['2147483648'],
            'too small' => ['-2147483649'],
            'far too large' => ['99999999999999999999']
        ];
    }

    /**
     * A query.sequence the builder cannot use as a whole number is rendered
     * as 1, which is what the .NET builder does, so the script parses and
     * nothing else is written in its place.
     * @dataProvider provider_unusableSequence
     */
    #[DataProvider('provider_unusableSequence')]
    public function testUnusableSequenceFromQueryBecomesOne(string $value): void
    {
        $script = self::render([
            'query.session-id' => 'abc',
            'query.sequence' => $value
        ]);

        $this->assertSame(['var sequence = 1;'], self::lines($script, 'sequence'));
        $this->assertParses($script);
    }

    public static function provider_usableSequence(): array
    {
        return [
            'text' => ['7', 7],
            'spaces' => [' 7 ', 7],
            'negative' => ['-2', -2],
            'plus' => ['+3', 3],
            'leading zeros' => ['007', 7],
            'largest' => ['2147483647', 2147483647],
            'smallest' => ['-2147483648', -2147483648],
            'number' => [12, 12]
        ];
    }

    /**
     * @dataProvider provider_usableSequence
     */
    #[DataProvider('provider_usableSequence')]
    public function testUsableSequenceFromQueryIsRendered($value, int $expected): void
    {
        $script = self::render([
            'query.session-id' => 'abc',
            'query.sequence' => $value
        ]);

        $this->assertSame(
            ['var sequence = ' . $expected . ';'],
            self::lines($script, 'sequence')
        );
        $this->assertParses($script);
    }

    public function testSessionIdFromQueryIsRendered(): void
    {
        $script = self::render(['query.session-id' => 'abc-123']);

        $this->assertSame(['var sessionId = "abc-123";'], self::lines($script, 'sessionId'));
        $this->assertParses($script);
    }

    /**
     * With the SequenceElement the first request is sequence 1 and the next
     * one carries the number the element worked out.
     */
    public function testSequenceElementStillSetsTheSequence(): void
    {
        $first = self::render([], false, true);
        $this->assertSame(['var sequence = 1;'], self::lines($first, 'sequence'));
        $this->assertParses($first);

        $second = self::render(
            ['query.session-id' => 'abc', 'query.sequence' => '1'],
            false,
            true
        );
        $this->assertSame(['var sequence = 2;'], self::lines($second, 'sequence'));
        $this->assertParses($second);
    }

    public static function provider_getSequence(): array
    {
        return [
            'null' => [null, 1],
            'true' => [true, 1],
            'float' => [2.0, 1],
            'int too large' => [2147483648, 1],
            'int too small' => [-2147483649, 1],
            'zero' => ['0', 0],
            'minus zero' => ['-0', 0]
        ];
    }

    /**
     * @dataProvider provider_getSequence
     */
    #[DataProvider('provider_getSequence')]
    public function testGetSequence($value, int $expected): void
    {
        $this->assertSame($expected, JavascriptBuilderElement::getSequence($value));
    }
}
