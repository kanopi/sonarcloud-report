<?php

namespace Kanopi\SonarQube;

use mikehaertl\wkhtmlto\Pdf;
use Monolog\Formatter\LineFormatter;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;

/**
 * Class RunReport.
 *
 * Used as a mechanism and the entry point for running the reports for
 * SonarQube. This will result in creating a PDF via WKHTMLPDF.
 */
final class RunReport
{

    private LoggerInterface $logger;

    private Environment $twigEnvironment;

    /**
     * @var array<int|string, string|int>
     */
    private const OPTIONS = [
        'header-line',
        'header-font-size' => 9,
        'header-spacing' => 3,
        'footer-left' => 'Generated by Kanopi Studios',
        'footer-right'=>'[page] of [topage] pages',
        'footer-font-size' => 9,
        'footer-spacing' => 3,
        'footer-line',
        'javascript-delay' => '5000'
    ];

    /**
     * @var array<string, string>
     */
    private const REPORTS = [
        'issues.html.twig' => 'issues',
        'vulnerabilities.html.twig' => 'vulnerabilities',
        'hotspots.html.twig' => 'hotspots',
        'duplications.html.twig' => 'duplications',
    ];

    private Pdf $pdf;

    /**
     * @var string
     */
    private const DATE_FORMAT = "Y-m-d H:i:s";

    /**
     * @var string
     */
    private const OUTPUT = "[%datetime%] %level_name% > %message% %context% %extra%\n";

    /**
     * Constructor.
     */
    public function __construct(private readonly SonarQube $sonarQube)
    {
    }

    /**
     * Return the Twig Instance.
     *
     * @return Environment
     */
    private function getTwigEnvironment(): Environment
    {
        if (!isset($this->twigEnvironment)) {
            $filesystemLoader = new FilesystemLoader(__DIR__ . '/../templates');
            $this->twigEnvironment = new Environment($filesystemLoader);
        }

        return $this->twigEnvironment;
    }

    /**
     * Return the PDF instance.
     *
     * @return Pdf
     *   Return Pdf instance.
     */
    private function getPdf(): Pdf
    {
        if (!isset($this->pdf)) {
            $this->pdf = new Pdf(self::OPTIONS);
        }

        return $this->pdf;
    }

    /**
     * Create the report for the given list of projects.
     *
     * @param string|string[] $projects
     *   List of projects to query.
     * @param string $fileName
     *   Filename to save as.
     *
     * @return void
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function createReport(string|array $projects, string $fileName): void
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

        $this->logger->info('Creating Summary for Project', $projects);
        $summaryOutput = $this->getTwigEnvironment()->render('summary.html.twig', [
            'summary' => array_map(static fn(Project $project): array => $project->getSummary(), $data),
        ]);

        $this->logger->info('Add Summary Page to end of the report');
        $this->getPdf()->addPage($summaryOutput);

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
                $report = $this->getTwigEnvironment()->render($index . '.html.twig', [
                    'title' => '',
                    'items' => $items[$index],
                    'summary' => $summary,
                ]);
                $this->logger->info('Adding page to end of the report');
                $this->getPdf()->addPage($report, $options);
            }
        }

        $this->logger->info('Attempting to save the file');
        if (!$this->getPdf()->saveAs($fileName)) {
            echo sprintf('ERROR: %s', $this->getPdf()->getError());
            exit(1);
        }
    }

    /**
     * Static command used to run the Report Generation.
     *
     * @param string $sonarQubeHost
     *   The hostname of the sonarqube api.
     * @param string $sonarQubeUser
     *   The username / api token to use for authentication.
     * @param string $sonarQubePass
     *   The password to use for authentication.
     * @param string|array $project
     *   The list of projects to run a report on.
     * @param string $fileName
     *   The name to save the report as.
     *
     * @return void
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public static function run(
        string $sonarQubeHost,
        string $sonarQubeUser,
        string $sonarQubePass,
        string|array $project,
        string $fileName
    ): void {
        $sonarQube = new SonarQube($sonarQubeHost, $sonarQubeUser, $sonarQubePass);
        $runReport = new RunReport($sonarQube);

        $logger = new Logger('sonarqube-report');
        $streamHandler = new StreamHandler('php://stdout', Logger::DEBUG);
        $lineFormatter = new LineFormatter(self::OUTPUT, self::DATE_FORMAT);
        $streamHandler->setFormatter($lineFormatter);

        $logger->pushHandler($streamHandler);

        $runReport->logger = $logger;

        $runReport->createReport($project, $fileName);
    }
}
