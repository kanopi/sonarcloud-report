<?php

namespace Kanopi\Tests\SonarQube;

use Kanopi\SonarQube\SonarQube;
use PHPUnit\Framework\TestCase;

/**
 * Abstract Class for Test Cases.
 */
abstract class TestCaseBase extends TestCase
{
    protected SonarQube $sonarQube;

    protected string $projectKey = 'sample_project';

    /**
     * Set Up.
     */
    public function setUp(): void
    {
        parent::setUp();
        // After turning on the VCR will intercept all requests
        \VCR\VCR::turnOn();
        // Record requests and responses in cassette file 'example'
        \VCR\VCR::insertCassette('sonarqube.yml');
        // SonarQube Instance.
        $this->sonarQube = SonarQube::create('http://127.0.0.1:9000', 'admin', 'password');
    }

    /**
     * Tear Down.
     */
    public function tearDown(): void
    {
        parent::tearDown();
        // To stop recording requests, eject the cassette
        \VCR\VCR::eject();
        // Turn off VCR to stop intercepting requests
        \VCR\VCR::turnOff();
    }
}
