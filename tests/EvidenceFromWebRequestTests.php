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

namespace fiftyone\pipeline\core\tests;

use fiftyone\pipeline\core\Evidence;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\core\tests\classes\ExampleFlowElement2;
use PHPUnit\Framework\TestCase;

/**
 * Evidence read from a web request keeps the parameter names the browser
 * sent.
 *
 * PHP replaces a dot and a space in a parameter name with an underscore
 * when it fills $_GET and $_POST. The cloud service reads several names
 * that have a dot of their own, among them 'id.usage', which decides what
 * a 51Did may be used for, and without it the service creates no 51Did at
 * all. Read against the live service on 17 September 2026, a request
 * carrying 'id.usage=standard' answers with a 51Did and the same request
 * carrying 'id_usage=standard' answers with no fodid section.
 */
class EvidenceFromWebRequestTests extends TestCase
{
    /**
     * @var array<string, string>
     */
    private array $savedGet;

    /**
     * @var array<string, string>
     */
    private array $savedPost;

    protected function setUp(): void
    {
        $this->savedGet = $_GET;
        $this->savedPost = $_POST;
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_GET = $this->savedGet;
        $_POST = $this->savedPost;
    }

    public function testQueryStringDottedNameKeepsItsDot(): void
    {
        // What PHP itself puts in $_GET for '?id.usage=standard'.
        $_GET = ['id_usage' => 'standard'];

        $evidence = $this->evidenceFor(['QUERY_STRING' => 'id.usage=standard']);

        $this->assertSame('standard', $evidence->get('query.id.usage'));
        $this->assertNull($evidence->get('query.id_usage'));
    }

    public function testFormBodyDottedNameKeepsItsDot(): void
    {
        // What PHP itself puts in $_POST for a body of
        // 'id.usage=standard'.
        $_POST = ['id_usage' => 'standard'];

        $evidence = $this->evidenceFor(
            [
                'REQUEST_METHOD' => 'POST',
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ],
            'id.usage=standard'
        );

        $this->assertSame('standard', $evidence->get('query.id.usage'));
        $this->assertNull($evidence->get('query.id_usage'));
    }

    public function testQueryStringWinsOverTheBody(): void
    {
        $_POST = ['id_usage' => 'non-marketing'];
        $_GET = ['id_usage' => 'standard'];

        $evidence = $this->evidenceFor(
            [
                'REQUEST_METHOD' => 'POST',
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
                'QUERY_STRING' => 'id.usage=standard',
            ],
            'id.usage=non-marketing'
        );

        $this->assertSame('standard', $evidence->get('query.id.usage'));
    }

    public function testNameSentWithAnUnderscoreIsLeftAlone(): void
    {
        $_GET = ['session_id' => '12345'];

        $evidence = $this->evidenceFor(['QUERY_STRING' => 'session_id=12345']);

        $this->assertSame('12345', $evidence->get('query.session_id'));
    }

    public function testValuesAreDecodedAsPhpDecodesThem(): void
    {
        $_GET = ['id_email' => 'someone@example.com'];

        $evidence = $this->evidenceFor(
            ['QUERY_STRING' => 'id.email=someone%40example.com']
        );

        $this->assertSame(
            'someone@example.com',
            $evidence->get('query.id.email')
        );
    }

    public function testNameSentWithASpaceIsLeftToPhp(): void
    {
        // What PHP itself puts in $_GET for '?user+agent=x'. No name the
        // cloud service reads holds a space, so PHP's name stands and the
        // evidence does not gain a key with a space in it.
        $_GET = ['user_agent' => 'x'];

        $evidence = $this->evidenceFor(['QUERY_STRING' => 'user+agent=x']);

        $this->assertSame('x', $evidence->get('query.user_agent'));
        $this->assertNull($evidence->get('query.user agent'));
    }

    public function testNameSentWithADotAndASpaceIsLeftToPhp(): void
    {
        // Putting the dot back on its own would leave PHP's 'a_b_c' in
        // place beside 'a.b c', because the two are no longer the same
        // name, and the evidence would carry the parameter twice.
        $_GET = ['a_b_c' => '1'];

        $evidence = $this->evidenceFor(['QUERY_STRING' => 'a.b+c=1']);

        $this->assertSame('1', $evidence->get('query.a_b_c'));
        $this->assertNull($evidence->get('query.a.b c'));
    }

    public function testNameSentWithALeadingSpaceIsLeftToPhp(): void
    {
        // PHP strips a leading space rather than replacing it, so the name
        // it gave cannot be found to drop and the parameter would arrive
        // under both names at once.
        $_GET = ['lead' => '1'];

        $evidence = $this->evidenceFor(['QUERY_STRING' => '+lead=1']);

        $this->assertSame('1', $evidence->get('query.lead'));
        $this->assertNull($evidence->get('query. lead'));
    }

    public function testFormBodyIsReadWhenTheContentTypeArrivesAsAHeader(): void
    {
        // Some SAPI and proxy configurations pass the content type on only
        // as HTTP_CONTENT_TYPE. Reading CONTENT_TYPE alone left the body
        // unread on those and the dot was lost with nothing to say so.
        $_POST = ['id_usage' => 'standard'];

        $evidence = $this->evidenceFor(
            [
                'REQUEST_METHOD' => 'POST',
                'HTTP_CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            ],
            'id.usage=standard'
        );

        $this->assertSame('standard', $evidence->get('query.id.usage'));
        $this->assertNull($evidence->get('query.id_usage'));
    }

    public function testReadingTheRequestStopsWherePhpStops(): void
    {
        // PHP gives up at max_input_vars, so reading the request again
        // gives up in the same place. Without the limit a request could
        // carry any number of names past the point PHP would have stopped
        // at, and each one is offered to every flow element in turn.
        $limit = (int) ini_get('max_input_vars');
        $this->assertGreaterThan(0, $limit, 'max_input_vars is readable');

        $sent = [];
        for ($index = 0; $index < $limit + 10; $index++) {
            $sent[] = 'id.usage' . $index . '=standard';
        }

        $evidence = $this->evidenceFor(
            ['QUERY_STRING' => implode('&', $sent)]
        );

        $this->assertSame(
            'standard',
            $evidence->get('query.id.usage' . ($limit - 1))
        );
        $this->assertNull($evidence->get('query.id.usage' . $limit));
    }

    public function testArrayFormIsLeftToPhp(): void
    {
        // PHP builds an array for 'a[b]=1', which this does not replace.
        $_GET = ['a' => ['b' => '1'], 'id_usage' => 'standard'];

        $evidence = $this->evidenceFor(
            ['QUERY_STRING' => 'a[b]=1&id.usage=standard']
        );

        $this->assertSame(['b' => '1'], $evidence->get('query.a'));
        $this->assertSame('standard', $evidence->get('query.id.usage'));
    }

    public function testAMultipartBodyIsNotReadAgain(): void
    {
        // A multipart body cannot be read as name and value pairs, so the
        // name PHP gave is all there is.
        $_POST = ['id_usage' => 'standard'];

        $evidence = $this->evidenceFor(
            [
                'REQUEST_METHOD' => 'POST',
                'CONTENT_TYPE' => 'multipart/form-data; boundary=x',
            ],
            'ignored'
        );

        $this->assertSame('standard', $evidence->get('query.id_usage'));
    }

    /**
     * Evidence read from a request described by $server, with $body as the
     * text of the request body.
     *
     * @param array<string, string> $server
     */
    private function evidenceFor(array $server, string $body = ''): Evidence
    {
        $pipeline = (new PipelineBuilder())
            ->add(new ExampleFlowElement2())
            ->build();
        $flowData = $pipeline->createFlowData();
        $evidence = new EvidenceWithBody($flowData, $body);
        $evidence->setFromWebRequest($server, []);

        return $evidence;
    }
}

/**
 * Evidence whose request body is given to it, because php://input cannot be
 * written to in a test.
 */
class EvidenceWithBody extends Evidence
{
    private static string $body = '';

    public function __construct(FlowData $flowData, string $body = '')
    {
        parent::__construct($flowData);
        self::$body = $body;
    }

    protected static function rawBody(): string
    {
        return self::$body;
    }
}
