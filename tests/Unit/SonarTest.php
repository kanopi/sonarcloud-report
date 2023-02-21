<?php

namespace Kanopi\Tests\SonarQube\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Kanopi\SonarQube\SonarQube;
use Kanopi\Tests\SonarQube\TestCaseBase;
use Psr\Http\Message\ResponseInterface;

/**
 * @coversDefaultClass \Kanopi\SonarQube\SonarQube
 */
class SonarTest extends TestCaseBase
{

    protected \ReflectionClass $modSonarQube;

    /**
     * Set Up.
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->modSonarQube = new \ReflectionClass($this->sonarQube);
    }

    /**
     * Create a mock client to use and help with testing.
     */
    public function createMockClient(array $data, string $path, array $query): SonarQube
    {
        $response = new Response(200, [], json_encode($data));

        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->any())
            ->method('get')
            ->with($path, ['query' => $query])
            ->willReturn($response);

        $notFoundResponse = new Response(404);

        $mockClient->expects($this->any())
            ->method('get')
            ->withAnyParameters()
            ->willReturn($notFoundResponse);

        return new SonarQube($mockClient);
    }

    /**
     * @covers ::__construct
     * @covers ::create
     */
    public function testCreate(): void
    {
        $actual = SonarQube::create('http://127.0.0.1:9000', 'admin', 'passwrod');
        $this->assertInstanceOf(SonarQube::class, $actual);
    }

    /**
     * @covers ::query
     */
    public function testQuery(): void
    {
        $data = [
            'a' => 1,
            'b' => 2,
            'c' => 3,
        ];

        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->any())->method('getBody')->willReturn(json_encode($data));

        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->any())->method('get')->willReturn($mockResponse);

        $sonarQube = new SonarQube($mockClient);
        $modSonarQube = new \ReflectionClass($sonarQube);

        $method = $modSonarQube->getMethod('query');
        $method->setAccessible(true);

        $actual = $method->invoke($sonarQube, '/test');
        $this->assertIsArray($actual);
        $this->assertEquals($data, $actual);
    }

    /**
     * @covers ::query
     */
    public function testQueryGuzzleException(): void
    {
        $request = new Request('GET', '/test');

        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->any())->method('get')
            ->willThrowException(
                new ConnectException('Could not connect.', $request)
            );

        $sonarQube = new SonarQube($mockClient);
        $modSonarQube = new \ReflectionClass($sonarQube);

        $method = $modSonarQube->getMethod('query');
        $method->setAccessible(true);

        $this->expectException(\Exception::class);
        $method->invoke($this->sonarQube, '/test');
    }

    /**
     * @covers ::query
     */
    public function testQueryJsonException(): void
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse->expects($this->any())->method('getBody')->willReturn('{');

        $mockClient = $this->createMock(Client::class);
        $mockClient->expects($this->any())->method('get')->willReturn($mockResponse);

        $sonarQube = new SonarQube($mockClient);
        $modSonarQube = new \ReflectionClass($sonarQube);

        $method = $modSonarQube->getMethod('query');
        $method->setAccessible(true);

        $this->expectException(\Exception::class);
        $method->invoke($this->sonarQube, '/test');
    }

    /**
     * @covers ::getMeasuresComponents
     */
    public function testGetMeasuresComponents(): void
    {
        $data = [];
        $sonarQube = $this->createMockClient(
            $data,
            '/api/measures/component',
            [
                'component' => 'sample_project',
                'metricKeys' => 'code_smells,coverage,bugs,vulnerabilities,security_hotspots,duplicated_lines,lines,ncloc',
            ]
        );

        // Test with no provided args for metrics and use the defaults.
        $actual = $sonarQube->getMeasuresComponents('sample_project');
        $this->assertIsArray($actual);
        $this->assertEquals($data, $actual);
    }

    /**
     * @covers ::getMeasuresComponents
     */
    public function testGetMeasuresComponentsTestWithMetrics(): void
    {
        $data = [];
        $sonarQube = $this->createMockClient(
            $data,
            '/api/measures/component',
            [
                'component' => 'sample_project',
                'metricKeys' => 'code_smells',
            ]
        );

        // Test with metrics
        $actual = $sonarQube->getMeasuresComponents('sample_project', ['code_smells']);
        $this->assertIsArray($actual);
        $this->assertEquals($data, $actual);
    }

    /**
     * @covers ::getMeasuresComponentsTree
     */
    public function testGetMeasuresComponentsTree(): void
    {
        $data = [];
        $sonarQube = $this->createMockClient(
            $data,
            '/api/measures/component_tree',
            [
                'component' => 'sample_project',
                'metricKeys' => 'sample_metric',
                'ps' => 500,
                'p' => 1,
            ]
        );

        $actual = $sonarQube->getMeasuresComponentsTree('sample_project', ['sample_metric']);
        $this->assertIsArray($actual);
        $this->assertEquals($data, $actual);
    }

    /**
     * @covers ::getMetrics
     */
    public function testGetMetrics(): void
    {
        $data = [];

        $sonarQube = $this->createMockClient(
            $data,
            '/api/metrics/search',
            [
                'ps' => 500,
                'p' => 1,
            ]
        );

        $actual = $sonarQube->getMetrics();
        $this->assertIsArray($actual);
        $this->assertEquals($data, $actual);
    }

    /**
     * @covers ::getIssuesSearch
     */
    public function testGetIssuesSearch(): void
    {
        $data = [];
        $sonarQube = $this->createMockClient(
            $data,
            '/api/issues/search',
            [
                'componentKeys' => 'sample_project',
                'ps' => 500,
                'p' => 1,
                'facets' => '',
                'types' => ''
            ]
        );

        $actual = $sonarQube->getIssuesSearch('sample_project');
        $this->assertIsArray($actual);
        $this->assertEquals($data, $actual);
    }

    /**
     * @covers ::getHotSpotsSearch
     */
    public function testGetHotSpotsSearch(): void
    {
        $data = [];
        $sonarQube = $this->createMockClient(
            $data,
            '/api/hotspots/search',
            [
                'projectKey' => 'sample_project',
                'ps' => 500,
                'p' => 1,
            ]
        );

        $actual = $sonarQube->getHotSpotsSearch('sample_project');
        $this->assertIsArray($actual);
        $this->assertEquals($data, $actual);
    }

    /**
     * @covers ::getSourceLines
     */
    public function testGetSourceLines(): void
    {
        $data = [];
        $sonarQube = $this->createMockClient(
            $data,
            '/api/sources/lines',
            [
                'key' => 'test',
                'from' => 1,
                'to' => 10,
            ]
        );

        $actual = $sonarQube->getSourceLines('test', 1, 10);
        $this->assertIsArray($actual);
        $this->assertEquals($data, $actual);
    }

    /**
     * @covers ::getSourceSnippet
     */
    public function testGetSourceSnippet(): void
    {
        $data = [];
        $sonarQube = $this->createMockClient(
            $data,
            '/api/sources/issue_snippets',
            [
                'issueKey' => 'test',
            ]
        );

        $actual = $sonarQube->getSourceSnippet('test');
        $this->assertIsArray($actual);
        $this->assertEquals($data, $actual);
    }

    /**
     * @covers ::getHotSpot
     */
    public function testGetHotSpot(): void
    {
        $data = [];
        $sonarQube = $this->createMockClient(
            $data,
            '/api/hotspots/show',
            [
                'hotspot' => 'test',
            ]
        );

        $actual = $sonarQube->getHotSpot('test');
        $this->assertIsArray($actual);
        $this->assertEquals($data, $actual);
    }

    /**
     * @covers ::getProjectAnalyses
     */
    public function testGetProjectAnalyses(): void
    {
        $data = [];
        $sonarQube = $this->createMockClient(
            $data,
            '/api/project_analyses/search',
            [
                'project' => 'test',
            ]
        );

        $actual = $sonarQube->getProjectAnalyses('test');
        $this->assertIsArray($actual);
        $this->assertEquals($data, $actual);
    }

    /**
     * @covers ::getRule
     */
    public function testGetRule(): void
    {
        $data = [];
        $sonarQube = $this->createMockClient(
            $data,
            '/api/rules/show',
            [
                'key' => 'test',
            ]
        );

        $actual = $sonarQube->getRule('test');
        $this->assertIsArray($actual);
        $this->assertEquals($data, $actual);
    }

    /**
     * @covers ::searchRules
     */
    public function testSearchRules(): void
    {
        $data = [];
        $sonarQube = $this->createMockClient(
            $data,
            '/api/rules/search',
            [
                'ps' => 500,
                'p' => 1
            ]
        );

        $actual = $sonarQube->searchRules();
        $this->assertIsArray($actual);
        $this->assertEquals($data, $actual);
    }

    /**
     * @covers ::continueToNextPage
     */
    public function testContinueToNextPage(): void
    {
        $mockClient = $this->createMock(Client::class);
        $sonarQube = new SonarQube($mockClient);

        $page = 1;
        $actual = $sonarQube->continueToNextPage($page, 10, 100);
        $this->assertTrue($actual);
        $this->assertEquals(2, $page);
    }

    /**
     * @covers ::continueToNextPage
     */
    public function testNoContinueToNextPage(): void
    {
        $mockClient = $this->createMock(Client::class);
        $sonarQube = new SonarQube($mockClient);

        $page = 1;
        $actual = $sonarQube->continueToNextPage($page, 10, 5);
        $this->assertFalse($actual);
    }

    /**
     * @covers ::continueToNextPage
     */
    public function testNoContinueToNextPagePastMax(): void
    {
        $mockClient = $this->createMock(Client::class);
        $sonarQube = new SonarQube($mockClient);

        $page = 1;
        $actual = $sonarQube->continueToNextPage($page, 10, 20, 10);
        $this->assertFalse($actual);
    }

    /**
     * @covers ::getDuplications
     */
    public function testGetDuplications(): void
    {
        $data = [];
        $sonarQube = $this->createMockClient(
            $data,
            '/api/duplications/show',
            [
                'key' => 'test'
            ]
        );

        $actual = $sonarQube->getDuplications('test');
        $this->assertIsArray($actual);
        $this->assertEquals($data, $actual);
    }

    /**
     * @covers ::getDuplicationsTree
     */
    public function testGetDuplicationsTree(): void
    {
        $data = [];
        $sonarQube = $this->createMockClient(
            $data,
            '/api/measures/component_tree',
            [
                'component' => 'sample_project',
                'metricKeys' => 'duplicated_lines,duplicated_blocks,duplicated_lines_density,duplicated_files',
                'ps' => 500,
                'p' => 1,
            ]
        );

        $actual = $sonarQube->getDuplicationsTree('sample_project');
        $this->assertIsArray($actual);
        $this->assertEquals($data, $actual);
    }
}