<?php

namespace Kanopi\Tests\SonarQube\Unit;

use Kanopi\SonarQube\Project;
use Kanopi\Tests\SonarQube\TestCaseBase;

/**
 * @coversDefaultClass \Kanopi\SonarQube\Project
 */
class ProjectTest extends TestCaseBase
{

    protected Project $project;

    protected \ReflectionClass $modProject;

    /**
     * Set Up
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->project = new Project($this->sonarQube, $this->projectKey);
        $this->modProject = new \ReflectionClass($this->project);
    }

    /**
     * @covers ::__construct
     * @covers ::getName
     */
    public function testGetName(): void
    {
        $project = new Project($this->sonarQube, $this->projectKey);
        $actual = $project->getName();
        $this->assertIsString($actual);
        $this->assertEquals('sample_project', $actual);
    }

    /**
     * @covers ::getSummary
     */
    public function testGetSummary(): void
    {
        $actual = $this->project->getSummary();
        $this->assertIsArray($actual);

        $this->assertArrayHasKey('name', $actual);
        $this->assertIsString($actual['name']);

        $this->assertArrayHasKey('lastrun', $actual);
        $this->assertIsString($actual['lastrun']);

        $this->assertArrayHasKey('info', $actual);
        $this->assertIsString($actual['info']);

        $this->assertArrayHasKey('minor', $actual);
        $this->assertIsString($actual['minor']);

        $this->assertArrayHasKey('major', $actual);
        $this->assertIsString($actual['major']);

        $this->assertArrayHasKey('critical', $actual);
        $this->assertIsString($actual['critical']);

        $this->assertArrayHasKey('blocker', $actual);
        $this->assertIsString($actual['blocker']);

        $this->assertArrayHasKey('code_smell', $actual);
        $this->assertIsString($actual['code_smell']);

        $this->assertArrayHasKey('bugs', $actual);
        $this->assertIsString($actual['bugs']);

        $this->assertArrayHasKey('vulnerability', $actual);
        $this->assertIsString($actual['vulnerability']);

        $this->assertArrayHasKey('hotspot', $actual);
        $this->assertIsString($actual['hotspot']);

        $this->assertArrayHasKey('lines', $actual);
        $this->assertIsString($actual['lines']);
    }

    /**
     * @covers ::getItems
     */
    public function testGetItems(): void
    {
        $actual = $this->project->getItems();

        $this->assertIsArray($actual);
        $this->assertArrayHasKey('issues', $actual);
        $this->assertArrayHasKey('hotspots', $actual);
        $this->assertArrayHasKey('vulnerabilities', $actual);
        $this->assertArrayHasKey('duplications', $actual);
    }

    /**
     * @covers ::getTotalMeasures
     */
    public function testGetTotalMeasures(): void
    {
        $method = $this->modProject->getMethod('getTotalMeasures');
        $method->setAccessible(true);

        $actual = $method->invoke($this->project, 'code_smells');
        $this->assertEquals('825', $actual);
    }

    /**
     * @covers ::getTotalSeverity
     */
    public function testGetTotalSeverity(): void
    {
        $method = $this->modProject->getMethod('getTotalSeverity');
        $method->setAccessible(true);

        $actual = $method->invoke($this->project, 'BLOCKER');
        $this->assertEquals('66', $actual);
    }

    /**
     * @covers ::getLastRun
     */
    public function testGetLastRun(): void
    {
        $method = $this->modProject->getMethod('getLastRun');
        $method->setAccessible(true);

        $actual = $method->invoke($this->project);
        $this->assertEquals('2023-02-19T08:49:40+0000', $actual);
    }

    /**
     * @covers ::getMeasuresComponents
     */
    public function testGetMeasuresComponents(): void
    {
        $method = $this->modProject->getMethod('getMeasuresComponents');
        $method->setAccessible(true);

        $actual = $method->invoke($this->project);
        $this->assertIsArray($actual);

        $this->assertArrayHasKey('component', $actual);
        $this->assertArrayHasKey('key', $actual['component']);
        $this->assertArrayHasKey('name', $actual['component']);
        $this->assertArrayHasKey('qualifier', $actual['component']);
        $this->assertArrayHasKey('measures', $actual['component']);

        $this->assertArrayHasKey('metric', $actual['component']['measures'][0]);
        $this->assertArrayHasKey('value', $actual['component']['measures'][0]);
        $this->assertArrayHasKey('bestValue', $actual['component']['measures'][0]);
    }

    /**
     * @covers ::getSeveritySummary
     */
    public function testGetSeveritySummary(): void
    {
        $method = $this->modProject->getMethod('getSeveritySummary');
        $method->setAccessible(true);

        $actual = $method->invoke($this->project);
        $this->assertIsArray($actual);
        $this->assertArrayHasKey('values', $actual);
        $this->assertArrayHasKey('val', $actual['values'][0]);
        $this->assertArrayHasKey('count', $actual['values'][0]);
    }

    /**
     * @covers ::getDuplications
     */
    public function testGetDuplications(): void
    {
        $actual = $this->project->getDuplications();

        $this->assertIsArray($actual);

        $this->assertArrayHasKey('items', $actual);
        $this->assertIsArray($actual['items']);
        $this->assertArrayHasKey('summary', $actual);
        $this->assertIsArray($actual['summary']);
        $this->assertArrayHasKey('lines', $actual);
        $this->assertIsArray($actual['lines']);
        $this->assertArrayHasKey('files', $actual);
        $this->assertIsArray($actual['files']);
        $this->assertArrayHasKey('blocks', $actual);
        $this->assertIsArray($actual['blocks']);
    }

    /**
     * @covers ::getProjectVulnerabilities
     */
    public function testGetProjectVulnerabilities(): void
    {
        $method = $this->modProject->getMethod('getProjectVulnerabilities');
        $method->setAccessible(true);

        $actual = $method->invoke($this->project);
        $this->assertIsArray($actual);
    }

    /**
     * @covers ::sortElements
     */
    public function testSortElements(): void
    {
        $method = $this->modProject->getMethod('sortElements');
        $method->setAccessible(true);

        $issues = [
            'z' => [
                'items' => [
                    [
                        'line' => 455,
                        'sample_level' => 10,
                    ],
                    [
                        'line' => 5,
                        'sample_level' => 10,
                    ],
                    [
                        'line' => 555,
                        'sample_level' => 10,
                    ],
                    [
                        'line' => 50,
                        'sample_level' => 3,
                    ],
                    [
                        'line' => 45,
                        'sample_level' => 31,
                    ]
                ]
            ],
            'y' => [
                'items' => [
                    [
                        'line' => 955,
                        'sample_level' => 10,
                    ],
                    [
                        'line' => 5,
                        'sample_level' => 10,
                    ],
                    [
                        'line' => 555,
                        'sample_level' => 10,
                    ],
                    [
                        'line' => 50,
                        'sample_level' => 3,
                    ],
                    [
                        'line' => 45,
                        'sample_level' => 31,
                    ]
                ],
            ],
            'x' => [
                'items' => [
                    [
                        'line' => 355,
                        'sample_level' => 10,
                    ],
                    [
                        'line' => 5,
                        'sample_level' => 10,
                    ],
                    [
                        'line' => 50,
                        'sample_level' => 3,
                    ],
                    [
                        'line' => 45,
                        'sample_level' => 31,
                    ]
                ],
            ],
        ];
        $sortKey = 'sample_level';

        $actual = $method->invoke($this->project, $issues, $sortKey);
        $this->assertIsArray($actual);
        $this->assertNotEquals($issues, $actual);

        $keys = array_keys($actual);
        $this->assertEquals(['x','y','z'], $keys);

        $this->assertEquals(50, $actual['x']['items'][0]['line']);
        $this->assertEquals(3, $actual['x']['items'][0]['sample_level']);
    }

    /**
     * @covers ::sortElements
     */
    public function testSortElementsTestException(): void
    {
        $method = $this->modProject->getMethod('sortElements');
        $method->setAccessible(true);

        $this->expectException(\Exception::class);

        // Should throw exception if the key is not found in the sub arrays.
        $issues = [
            'z' => [
                'items' => [
                    [
                        'line' => 455,
                    ],
                    [
                        'line' => 5,
                        'sample_level' => 10,
                    ],
                    [
                        'line' => 555,
                        'sample_level' => 10,
                    ],
                    [
                        'line' => 50,
                        'sample_level' => 3,
                    ],
                    [
                        'line' => 45,
                        'sample_level' => 31,
                    ]
                ]
            ],
        ];
        $sortKey = 'sample_level';

        $actual = $method->invoke($this->project, $issues, $sortKey);
    }

    /**
     * @covers ::queryElements
     */
    public function testQueryElements(): void
    {
        $method = $this->modProject->getMethod('queryElements');
        $method->setAccessible(true);

        $actual = $method->invoke($this->project, 'getIssuesSearch', 'issues', function(){});
        $this->assertIsArray($actual);

        $firstItem = array_shift($actual);
        $this->assertIsArray($firstItem);
        $this->assertArrayHasKey('items', $firstItem);
        $this->assertIsArray($firstItem['items']);
    }

    /**
     * @covers ::getProjectIssues
     */
    public function testGetProjectIssues(): void
    {
        $method = $this->modProject->getMethod('getProjectIssues');
        $method->setAccessible(true);

        $actual = $method->invoke($this->project);
        $this->assertIsArray($actual);

        $item = array_shift($actual);
        $this->assertIsArray($item);
        $this->assertArrayHasKey('longName', $item);
        $this->assertArrayHasKey('items', $item);

        $element = array_shift($item['items']);
        $this->assertIsArray($element);
        $this->assertArrayHasKey('severity', $element);
        $this->assertArrayHasKey('type', $element);
        $this->assertArrayHasKey('message', $element);
        $this->assertArrayHasKey('line', $element);
    }

    /**
     * @covers ::getProjectHotSpots
     */
    public function testGetProjectHotSpots(): void
    {
        $method = $this->modProject->getMethod('getProjectHotSpots');
        $method->setAccessible(true);

        $actual = $method->invoke($this->project);
        $this->assertIsArray($actual);

        $item = array_shift($actual);
        $this->assertIsArray($item);
        $this->assertArrayHasKey('path', $item);
        $this->assertArrayHasKey('items', $item);

        $element = array_shift($item['items']);
        $this->assertIsArray($element);
        $this->assertArrayHasKey('vulnerabilityProbability', $element);
        $this->assertArrayHasKey('line', $element);
        $this->assertArrayHasKey('message', $element);
        $this->assertArrayHasKey('source', $element);
        $this->assertArrayHasKey('info', $element);
        $this->assertArrayHasKey('rule', $element['info']);
        $this->assertArrayHasKey('riskDescription', $element['info']['rule']);
        $this->assertArrayHasKey('vulnerabilityDescription', $element['info']['rule']);
        $this->assertArrayHasKey('fixRecommendations', $element['info']['rule']);
    }

    /**
     * @covers ::getProjectDuplications
     */
    public function testGetProjectDuplications(): void
    {
        $method = $this->modProject->getMethod('getProjectDuplications');
        $method->setAccessible(true);

        $actual = $method->invoke($this->project);
        $this->assertIsArray($actual);

        $item = array_shift($actual);
        $this->assertIsArray($item);
        $this->assertArrayHasKey('path', $item);
        $this->assertArrayHasKey('measures', $item);
        $this->assertArrayHasKey('duplicated_lines', $item['measures']);
        $this->assertArrayHasKey('duplicated_lines_density', $item['measures']);
        $this->assertArrayHasKey('duplicated_files', $item['measures']);
        $this->assertArrayHasKey('duplicated_blocks', $item['measures']);
    }

    /**
     * @covers ::getProjectDuplicationsSummary
     */
    public function testGetProjectDuplicationsSummary(): void
    {
        $method = $this->modProject->getMethod('getProjectDuplicationsSummary');
        $method->setAccessible(true);

        $actual = $method->invoke($this->project);
        $this->assertIsArray($actual);

        $this->assertArrayHasKey('duplicated_lines', $actual);
        $this->assertIsString($actual['duplicated_lines']);

        $this->assertArrayHasKey('duplicated_lines_density', $actual);
        $this->assertIsString($actual['duplicated_lines_density']);

        $this->assertArrayHasKey('duplicated_files', $actual);
        $this->assertIsString($actual['duplicated_files']);

        $this->assertArrayHasKey('duplicated_blocks', $actual);
        $this->assertIsString($actual['duplicated_blocks']);
    }
}
