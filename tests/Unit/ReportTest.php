<?php

namespace Kanopi\Tests\SonarQube\Unit;

use Kanopi\SonarQube\RunReport;
use Kanopi\Tests\SonarQube\TestCaseBase;
use mikehaertl\wkhtmlto\Pdf;
use Psr\Log\NullLogger;
use Twig\Environment;
use Twig\Error\LoaderError;

/**
 * @coversDefaultClass \Kanopi\SonarQube\RunReport
 */
class ReportTest extends TestCaseBase
{

    /**
     * @covers ::__construct
     * @covers ::createReport
     */
    public function testCreateReport(): void
    {
        $mockTwigEnvironment = $this->createMock(Environment::class);
        $mockTwigEnvironment->expects($this->exactly(5))->method('render')->willReturn('');

        $mockPdf = $this->createMock(Pdf::class);
        $mockPdf->expects($this->exactly(5))->method('addPage');
        $mockPdf->expects($this->once())->method('saveAs')->willReturn(true);

        $logger = new NullLogger();
        $runReport = new RunReport(
            $this->sonarQube,
            $logger,
            $mockTwigEnvironment,
            $mockPdf
        );

        $actual = $runReport->createReport($this->projectKey, 'output.pdf');
        $this->assertTrue($actual);
    }

    /**
     * @covers ::createReport
     */
    public function testCreateReportSaveException(): void
    {
        $mockTwigEnvironment = $this->createMock(Environment::class);
        $mockTwigEnvironment->expects($this->exactly(5))->method('render')->willReturn('');

        $mockPdf = $this->createMock(Pdf::class);
        $mockPdf->expects($this->exactly(5))->method('addPage');
        $mockPdf->expects($this->once())->method('saveAs')->willReturn(false);

        $logger = new NullLogger();
        $runReport = new RunReport(
            $this->sonarQube,
            $logger,
            $mockTwigEnvironment,
            $mockPdf
        );

        $actual = $runReport->createReport($this->projectKey, 'output.pdf');
        $this->assertFalse($actual);
    }

    /**
     * @covers ::createReport
     */
    public function testCreateReportTwigException(): void
    {
        $mockTwigEnvironment = $this->createMock(Environment::class);
        $mockTwigEnvironment->expects($this->exactly(1))->method('render')->willThrowException(new LoaderError('Twig Exception'));

        $mockPdf = $this->createMock(Pdf::class);

        $logger = new NullLogger();
        $runReport = new RunReport(
            $this->sonarQube,
            $logger,
            $mockTwigEnvironment,
            $mockPdf
        );

        $actual = $runReport->createReport($this->projectKey, 'output.pdf');
        $this->assertFalse($actual);
    }
}