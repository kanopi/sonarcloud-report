<?php

namespace Kanopi\Tests\SonarQube\Unit;

use Kanopi\SonarQube\Util;
use Kanopi\Tests\SonarQube\TestCaseBase;

/**
 * @coversDefaultClass \Kanopi\SonarQube\Util
 */
class UtilTest extends TestCaseBase
{

    /**
     * @covers ::findMetric
     */
    public function testFindMetric(): void
    {
        $data = [
            'component' => [
                'measures' => [
                    [
                        'metric' => 'test1',
                        'value' => 1,
                    ],
                    [
                        'metric' => 'test2',
                        'value' => 2,
                    ],
                    [
                        'metric' => 'test3',
                        'value' => 3,
                    ],
                    [
                        'metric' => 'test4',
                        'value' => 4,
                    ],
                ]
            ]
        ];

        $actual = Util::findMetric($data, 'test3');
        $this->assertEquals(3, $actual);

        $actual = Util::findMetric($data, 'notfound');
        $this->assertNull($actual);
    }

    /**
     * @covers ::findSeverity
     */
    public function testFindSeverity(): void
    {
        $data = [
          'values' => [
              [
                  'val' => 'test1',
                  'count' => 1,
              ],
              [
                  'val' => 'test2',
                  'count' => 2,
              ],
              [
                  'val' => 'test3',
                  'count' => 3,
              ],
              [
                  'val' => 'test4',
                  'count' => 4,
              ],
          ]
        ];

        $actual = Util::findSeverity($data, 'test3');
        $this->assertEquals(3, $actual);

        $actual = Util::findSeverity($data, 'notfound');
        $this->assertNull($actual);
    }

    /**
     * @covers ::findComponent
     */
    public function testFindComponent(): void
    {
        $data = [
            [
                'key' => 'test1',
                'sample' => 1,
            ],
            [
                'key' => 'test2',
                'sample' => 2,
            ],
            [
                'key' => 'test3',
                'sample' => 3,
            ],
            [
                'key' => 'test4',
                'sample' => 4,
            ],
        ];

        $actual = Util::findComponent($data, 'test3');
        $this->assertIsArray($actual);
        $this->assertEquals('3', $actual['sample']);

        $actual = Util::findComponent($data, 'notfound');
        $this->assertIsArray($actual);
        $this->assertEmpty($actual);
    }

    /**
     * @covers ::getSeverityLevel
     */
    public function testGetSeverityLevel(): void
    {
        $actual = Util::getSeverityLevel('BLOCKER');
        $this->assertIsInt($actual);
        $this->assertEquals(-5, $actual);

        $actual = Util::getSeverityLevel('NOTFOUND');
        $this->assertIsInt($actual);
        $this->assertEquals(0, $actual);
    }

    /**
     * @covers ::getVulnerabilityLevel
     */
    public function testGetVulnerabilityLevel(): void
    {
        $actual = Util::getVulnerabilityLevel('LOW');
        $this->assertIsInt($actual);
        $this->assertEquals(-1, $actual);

        $actual = Util::getVulnerabilityLevel('NOTFOUND');
        $this->assertIsInt($actual);
        $this->assertEquals(0, $actual);
    }
}