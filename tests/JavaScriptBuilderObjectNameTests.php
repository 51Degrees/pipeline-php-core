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

use fiftyone\pipeline\core\Constants;
use fiftyone\pipeline\core\JavascriptBuilderElement;
use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\core\tests\classes\ExampleFlowElement1;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Keeps every log entry so the tests can read the warnings.
 */
class ObjectNameTestLogger extends Logger
{
    public array $entries = [];

    public function logInternal(array $log): void
    {
        $this->entries[] = $log;
    }
}

/**
 * The client side object's name can be set in the builder's settings
 * (objName) or per request (query.fod-js-object-name). The name is written
 * into the script as a variable name, a session storage key and a property
 * name, so these tests check that a different name works, and that a name
 * which is not a JavaScript identifier is never written into the script.
 *
 * The script is checked with "node --check" and run in Node with a minimal
 * stand in for a browser (object_name_harness.js). Both need Node on the
 * path, which every GitHub hosted runner has.
 */
class JavaScriptBuilderObjectNameTests extends TestCase
{
    private static function render(
        array $settings,
        array $evidence,
        ?Logger $logger = null
    ): string {
        $builder = new PipelineBuilder(['javascriptBuilderSettings' => $settings]);
        if ($logger !== null) {
            $builder->addLogger($logger);
        }
        $pipeline = $builder->add(new ExampleFlowElement1())->build();
        $flowData = $pipeline->createFlowData();
        foreach ($evidence as $key => $value) {
            $flowData->evidence->set($key, $value);
        }
        $flowData->process();

        return $flowData->javascriptbuilder->javascript;
    }

    /**
     * Runs Node with the arguments and returns the exit code and output.
     * The command is passed as an array so no shell is involved.
     */
    private static function node(array $args): array
    {
        $process = @proc_open(
            array_merge(['node'], $args),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($process)) {
            return [-1, '', 'node could not be started'];
        }
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $out, $err];
    }

    private static function write(string $script): string
    {
        // Node wants a .js name, so the empty file tempnam creates is
        // replaced with one that has the extension.
        $base = tempnam(sys_get_temp_dir(), 'objname');
        unlink($base);
        $path = $base . '.js';
        file_put_contents($path, $script);

        return $path;
    }

    private function requireNode(): void
    {
        [$code] = self::node(['--version']);
        if ($code !== 0) {
            $this->markTestSkipped('Node is needed to parse and run the script');
        }
    }

    private function assertParses(string $script): void
    {
        $this->requireNode();
        $path = self::write($script);
        try {
            [$code, , $err] = self::node(['--check', $path]);
        } finally {
            unlink($path);
        }
        $this->assertSame(0, $code, 'the rendered script does not parse: ' . $err);
    }

    private function runScript(string $script, string $name): array
    {
        $this->requireNode();
        $path = self::write($script);
        try {
            [$code, $out, $err] = self::node([
                __DIR__ . '/object_name_harness.js', $path, $name, 'example1.integer'
            ]);
        } finally {
            unlink($path);
        }
        $this->assertSame(0, $code, $err);

        return json_decode($out, true);
    }

    private static function declaredNames(string $script): array
    {
        preg_match_all(
            '/\bvar\s+([^\s=]*)\s*=\s*new\s+fiftyoneDegreesManager\b/',
            $script,
            $matches
        );

        return $matches[1];
    }

    private function assertUsesName(string $script, string $name): void
    {
        $this->assertSame([$name], self::declaredNames($script));
        if ($name !== 'fod') {
            $this->assertDoesNotMatchRegularExpression(
                '/\bvar\s+fod\b/',
                $script,
                'the default name must not be declared as well'
            );
        }
        $this->assertStringContainsString('var sessionKey = "' . $name . '";', $script);
        $this->assertStringContainsString('window["' . $name . 'Evidence"]', $script);
        $this->assertStringContainsString('typeof window["' . $name . '"]', $script);
    }

    public static function provider_validName(): array
    {
        return [
            'settings' => [['objName' => 'myFod', 'minify' => false], []],
            'evidence' => [
                ['minify' => false],
                [Constants::EVIDENCE_OBJECT_NAME => 'myFod']
            ]
        ];
    }

    /**
     * A valid name from the settings or from the page request is declared,
     * used as the storage key and in the evidence lookup, and the script
     * parses and creates that object.
     * @dataProvider provider_validName
     */
    #[DataProvider('provider_validName')]
    public function testValidNameIsUsed(array $settings, array $evidence): void
    {
        $script = self::render($settings, $evidence);

        $this->assertUsesName($script, 'myFod');
        $this->assertParses($script);

        $result = $this->runScript($script, 'myFod');
        $this->assertNull($result['error']);
        $this->assertTrue($result['exists'], 'window.myFod was not created');
        $this->assertSame('function', $result['complete']);
        $this->assertSame('function', $result['onChange']);
        $this->assertSame('function', $result['refresh']);
        $this->assertSame('5', $result['value']);
        $this->assertSame(['myFod'], $result['globals']);
    }

    /**
     * The minified script declares the requested name and parses.
     */
    public function testValidNameFromEvidenceMinified(): void
    {
        $script = self::render(
            ['minify' => true],
            [Constants::EVIDENCE_OBJECT_NAME => 'myFod']
        );

        $this->assertSame(['myFod'], self::declaredNames($script));
        $this->assertParses($script);
    }

    public static function provider_invalidName(): array
    {
        return [
            'statement' => ['a;b', true],
            'leading digit' => ['9bad', true],
            'quote' => ['x"y', true],
            'empty' => ['', false],
            // A reserved word is also an ordinary word in the script's
            // comments, so only the places the name is written are checked.
            'reserved word' => ['class', false],
            // These are also ordinary words in the script, as values or as
            // the constructor's own name.
            'Infinity' => ['Infinity', false],
            'NaN' => ['NaN', false],
            'undefined' => ['undefined', false],
            'constructor name' => ['fiftyoneDegreesManager', false]
        ];
    }

    /**
     * A requested name that is not a JavaScript identifier is not written
     * into the script. The configured name is used, the script parses and
     * runs, and a warning is logged.
     * @dataProvider provider_invalidName
     */
    #[DataProvider('provider_invalidName')]
    public function testInvalidNameFromEvidenceIsIgnored(string $name, bool $checkText): void
    {
        $logger = new ObjectNameTestLogger('warning');
        $script = self::render(
            ['minify' => false],
            [Constants::EVIDENCE_OBJECT_NAME => $name],
            $logger
        );

        $this->assertUsesName($script, 'fod');
        $this->assertParses($script);

        if ($checkText) {
            // The requested value is still one of the request's query
            // values, which the script carries as data inside a JSON string.
            // Every other line must be free of it.
            foreach (explode("\n", $script) as $line) {
                if (strpos($line, 'var renderedParameters') !== false) {
                    continue;
                }
                $this->assertStringNotContainsString(
                    $name,
                    $line,
                    'the requested name was written into the script'
                );
            }
        }

        $result = $this->runScript($script, 'fod');
        $this->assertNull($result['error']);
        $this->assertTrue($result['exists']);
        $this->assertSame(['fod'], $result['globals']);

        $warnings = array_values(array_filter(
            $logger->entries,
            function ($entry) {
                return $entry['level'] === 'warning' &&
                    strpos($entry['message'], Constants::EVIDENCE_OBJECT_NAME) !== false;
            }
        ));
        $this->assertCount(1, $warnings, json_encode($logger->entries));
        if ($checkText) {
            $this->assertStringNotContainsString($name, $warnings[0]['message']);
        }
    }

    /**
     * The fallback is the configured name, not always 'fod'.
     */
    public function testInvalidNameFromEvidenceUsesConfiguredName(): void
    {
        $script = self::render(
            ['objName' => 'myFod', 'minify' => false],
            [Constants::EVIDENCE_OBJECT_NAME => '9bad']
        );

        $this->assertUsesName($script, 'myFod');
        $this->assertParses($script);
    }

    public static function provider_invalidSetting(): array
    {
        return [
            'statement' => ['a;b'],
            'leading digit' => ['9bad'],
            'quote' => ['x"y'],
            'empty' => [''],
            'reserved word' => ['var'],
            'Infinity' => ['Infinity'],
            'NaN' => ['NaN'],
            'undefined' => ['undefined'],
            'constructor name' => ['fiftyoneDegreesManager'],
            'trailing new line' => ["fod\n"],
            'not ASCII' => ["caf\u{e9}"],
            'not a string' => [5]
        ];
    }

    /**
     * An invalid name in the settings is refused when the builder is
     * created.
     * @dataProvider provider_invalidSetting
     */
    #[DataProvider('provider_invalidSetting')]
    public function testInvalidConfiguredNameIsRefused($name): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new JavascriptBuilderElement(['objName' => $name]);
    }

    public static function provider_validSetting(): array
    {
        return [
            'plain' => ['myFod'],
            'underscore' => ['_fod'],
            'dollar' => ['$fod9'],
            'contains a reserved word' => ['classy']
        ];
    }

    /**
     * @dataProvider provider_validSetting
     */
    #[DataProvider('provider_validSetting')]
    public function testValidConfiguredNameIsAccepted(string $name): void
    {
        $element = new JavascriptBuilderElement(['objName' => $name]);
        $this->assertSame($name, $element->settings['_objName']);
    }

    public static function provider_notConfigured(): array
    {
        return [
            'absent' => [[]],
            'null' => [['objName' => null]]
        ];
    }

    /**
     * A name that is not configured at all, absent or null, means fod. Only
     * a configured name, the empty string included, is checked.
     * @dataProvider provider_notConfigured
     */
    #[DataProvider('provider_notConfigured')]
    public function testNameNotConfiguredIsFod(array $settings): void
    {
        $element = new JavascriptBuilderElement($settings);
        $this->assertSame('fod', $element->settings['_objName']);
    }
}
