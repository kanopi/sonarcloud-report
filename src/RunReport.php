<?php

namespace Kanopi\SonarQube;

use Exception;
use JsonException;
use mikehaertl\wkhtmlto\Pdf;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Class RunReport.
 *
 * Used as a mechanism and the entry point for running the reports for
 * SonarQube. This will result in creating a PDF via WKHTMLPDF.
 */
final class RunReport
{
    /**
     * @var array<string, string>
     */
    private const REPORTS = [
        'issues.html.twig' => 'issues',
        'vulnerabilities.html.twig' => 'vulnerabilities',
        'hotspots.html.twig' => 'hotspots',
        'duplications.html.twig' => 'duplications',
    ];

    /**
     * Constructor.
     */
    public function __construct(
        private readonly SonarQube $sonarQube,
        private readonly LoggerInterface $logger,
        private readonly Environment $twigEnvironment,
        private readonly Pdf $pdf
    ) {
    }

    /**
     * Check the project queue to ensure it's empty.
     *
     * @param int $sleep_time
     *   Default sleep time amount.
     * @param int $max_tries
     *   Max number of tries to check.
     *
     * @return bool
     *   Is queue empty.
     */
    private function checkProjectAnalysisQueue(int $sleep_time = 10, int $max_tries = 6): bool
    {
        $count = 0;
        while (!$this->sonarQube->isQueueEmpty()) {
            if ($count >= $max_tries) {
                return false;
            }

            sleep($sleep_time);
            ++$count;
        }

        return true;
    }

    /**
     * Create the report for the given list of projects.
     *
     * @param string|string[] $projects
     *   List of projects to query.
     * @param string $fileName
     *   Filename to save as.
     *
     * @return bool
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function createReport(string|array $projects, string $fileName): bool
    {
        $this->logger->info('Starting to Create Report');
        if (is_string($projects)) {
            $projects = [$projects];
        }

        $data = [];

        // Loop through all the requested projects and create new classes.
        foreach ($projects as $project) {
            $data[$project] = new Project($this->sonarQube, $project);
        }

        $this->logger->info('Checking Project Analysis Queue');
        $response = $this->checkProjectAnalysisQueue();
        if (!$response) {
            $this->logger->error('Error: Project queue is not empty.');
            return false;
        }

        $this->logger->info('Creating Summary for Project', $projects);

        try {
            $summaryOutput = $this->twigEnvironment->render('summary.html.twig', [
                'summary' => array_map(static fn(Project $project): array => $project->getSummary(), $data),
            ]);

            $this->logger->info('Add Summary Page to end of the report');
            $this->pdf->addPage($summaryOutput);

            $this->logger->info('Looping through the projects');
            foreach ($data as $project) {
                $this->logger->info('Starting on the report - ' . $project->getName());
                $options = [
                    'header-left' => $project->getName(),
                ];

                $this->logger->info('Starting on reports');
                $summary = $project->getSummary();
                $items = $project->getItems();
                foreach (self::REPORTS as $index) {
                    $this->logger->info('Building report ' . $index);
                    $report = $this->twigEnvironment->render($index . '.html.twig', [
                        'title' => '',
                        'items' => $items[$index],
                        'summary' => $summary,
                    ]);
                    $this->logger->info('Adding page to end of the report');
                    $this->pdf->addPage($report, $options);
                }
            }
        } catch (Exception $exception) {
            $this->logger->error(sprintf('Error: %s', $exception->getMessage()));
            return false;
        }

        $this->logger->info('Attempting to save the file');
        if (!$this->pdf->saveAs($fileName)) {
            $this->logger->error(sprintf('ERROR: %s', $this->pdf->getError()));
            return false;
        }

        return true;
    }
}
