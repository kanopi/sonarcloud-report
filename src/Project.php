<?php

namespace Kanopi\SonarQube;

use Exception;
use JsonException;
use ValueError;

/**
 * Class Project.
 *
 * Project class related to a specific SonarQube project and used
 * for generating the report.
 */
final class Project
{
    private array $summary = [];

    private array $items = [];

    private string $name = '';

    private array $severities = [];

    private array $measures = [];

    private string $lastRun = '';

    /**
     * Constructor
     *
     * @param SonarQube $sonarQube
     *   SonarQube API Client.
     * @param string $projectKey
     *   Project key to query.
     */
    public function __construct(protected readonly SonarQube $sonarQube, protected readonly string $projectKey)
    {
    }

    /**
     * Return projects label.
     *
     * @return string
     *   Project label.
     */
    public function getName(): string
    {
        if ($this->name === '') {
            $measures = $this->getMeasuresComponents();
            $this->name = (string)$measures['component']['name'];
        }

        return $this->name;
    }

    /**
     * Return summary for project.
     *
     * @return array<string, string>
     *   Summary details.
     */
    public function getSummary(): array
    {
        if ($this->summary === []) {
            $this->summary = [
                'name' => $this->getName(),
                'lastrun' => $this->getLastRun(),
                'info' => $this->getTotalSeverity('INFO'),
                'minor' => $this->getTotalSeverity('MINOR'),
                'major' => $this->getTotalSeverity('MAJOR'),
                'critical' => $this->getTotalSeverity('CRITICAL'),
                'blocker' => $this->getTotalSeverity('BLOCKER'),
                'code_smell' => $this->getTotalMeasures('code_smells'),
                'bugs' => $this->getTotalMeasures('bugs'),
                'vulnerability' => $this->getTotalMeasures('vulnerabilities'),
                'hotspot' => $this->getTotalMeasures('security_hotspots'),
                'lines' => $this->getTotalMeasures('ncloc'),
            ];
        }

        return $this->summary;
    }

    /**
     * Return a list of all items for project. Including issues, hotspots, vulnerabilities, and duplications.
     *
     * @return array
     *   Return all data.
     *
     * @throws Exception
     */
    public function getItems(): array
    {
        if ($this->items === []) {
            $this->items = [
                'issues' => $this->getProjectIssues(),
                'hotspots' => $this->getProjectHotSpots(),
                'vulnerabilities' => $this->getProjectVulnerabilities(),
                'duplications' => $this->getDuplications(),
            ];
        }

        return $this->items;
    }

    /**
     * Helper functions used for getting total number of measures.
     *
     * @param string $type
     *   Type of items to query.
     *
     * @return string|null
     *   Total number of items.
     */
    private function getTotalMeasures(string $type): ?string
    {
        $measures = $this->getMeasuresComponents();
        return Util::findMetric($measures, $type);
    }

    /**
     * Helper functions used for getting total number of severity items.
     *
     * @param string $type
     *   Type of items to query.
     *
     * @return string|null
     *   Total number of items.
     */
    private function getTotalSeverity(string $type): ?string
    {
        $severities = $this->getSeveritySummary();
        return Util::findSeverity($severities, $type);
    }

    /**
     * Return date of last run.
     *
     * @return string
     *   Date of last run.
     */
    private function getLastRun(): string
    {
        if ($this->lastRun === '') {
            $response = $this->sonarQube->getProjectAnalyses($this->projectKey);
            $this->lastRun = (string)$response['analyses'][0]['date'] ?? 0;
        }

        return $this->lastRun;
    }

    /**
     * Get all project measure components.
     *
     * @return array
     *   Return all data.
     */
    private function getMeasuresComponents(): array
    {
        if ($this->measures === []) {
            $this->measures = $this->sonarQube->getMeasuresComponents($this->projectKey);
        }

        return $this->measures;
    }

    /**
     * Get a list of all severities.
     *
     * @return array
     *   Return all data.
     */
    private function getSeveritySummary(): array
    {
        if ($this->severities === []) {
            $this->severities = $this->sonarQube->getIssuesSearch($this->projectKey, 1, ['severities']);
        }

        return (array)$this->severities['facets'][0];
    }

    /**
     * Return all duplications.
     *
     * @return array{
     *     items: mixed[],
     *     summary: mixed[],
     *     lines: mixed[]|string,
     *     files: mixed[]|string,
     *     blocks: mixed[]|string
     * }
     *   Return all data.
     */
    public function getDuplications(): array
    {
        $duplications = $this->getProjectDuplications();

        $info = [
            'lines' => 'duplicated_lines',
            'files' => 'duplicated_files',
            'blocks' => 'duplicated_blocks',
        ];

        // Loop through the different types of duplications.
        foreach ($info as $key => $filter) {
            // Filter out items that have duplications.
            $info[$key] = array_filter($duplications, static fn($item): bool => $item['measures'][$filter] > 0);
            // For anything but files sort in descending order.
            if ($key !== 'files') {
                uasort($info[$key], static fn($a, $b): int => $a['measures'][$filter] <=> $b['measures'][$filter]);
                $info[$key] = array_reverse($info[$key], true);
            }
        }

        return [
            'items' => $duplications,
            'summary' => $this->getProjectDuplicationsSummary(),
            'lines' => $info['lines'],
            'files' => $info['files'],
            'blocks' => $info['blocks'],
        ];
    }

    /**
     * Return a list of all vulnerabilities.
     *
     * @return array
     *   Return all data.
     *
     * @throws Exception
     */
    private function getProjectVulnerabilities(): array
    {
        $issues = [];

        $continue = true;
        $page = 1;
        while ($continue) {
            $issueData = $this->sonarQube->getIssuesSearch($this->projectKey, $page, [], ['VULNERABILITY']);
            $components = $issueData['components'];

            foreach ($issueData['issues'] as $issue) {
                $component = $issue['component'];
                if (!isset($issues[$component])) {
                    $issues[$component] = Util::findComponent($components, $component);
                    $issues[$component]['items'] = [];
                }

                // if line isn't set use the textRange attribute
                $issue['line'] ??= $issue['textRange']['startLine'] ?? '';

                $issue['severity_level'] = Util::getSeverityLevel($issue['severity']);

                $source = $this->sonarQube->getSourceSnippet($issue['key']);

                $issue['source'] = array_reduce(
                    $source[$issue['component']]['sources'] ?? [],
                    static fn($source, $item): string => $source . (strip_tags((string) $item['code']) . PHP_EOL)
                );

                $issue['rule_info'] = $this->sonarQube->getRule($issue['rule'])['rule'] ?? null;

                $issues[$component]['items'][] = $issue;
            }

            $total = $issueData['paging']['total'];
            $perPage = $issueData['paging']['pageSize'];
            $continue = $this->sonarQube->continueToNextPage($page, $perPage, $total);
        }

        return $this->sortElements($issues, 'severity_level');
    }

    /**
     * Sort all elements based on a specific sorting key.
     *
     * @param array $issues
     *   Items to sort.
     * @param string $sortKey
     *   Sort key to sort on.
     *
     * @return array
     *   Return the sorted data.
     *
     * @throws Exception
     */
    private function sortElements(array $issues, string $sortKey): array
    {
        foreach ($issues as $component => &$items) {
            try {
                $col = array_column($items['items'], $sortKey);
                array_multisort(
                    $col,
                    SORT_ASC,
                    SORT_REGULAR,
                    array_column($items['items'], 'line'),
                    SORT_ASC,
                    SORT_REGULAR,
                    $items['items']
                );
            } catch (ValueError $valueError) {
                throw new Exception(
                    sprintf('ERROR: %s with %s', $valueError->getMessage(), $component),
                    $valueError->getCode(),
                    $valueError
                );
            }
        }

        ksort($issues);

        return $issues;
    }

    /**
     * Query elements of a specific type and apply a callback to transform them.
     *
     * @param string $searchFunction
     *   Search function to use for querying elements. Comes from SonarQube client.
     * @param string $dataKey
     *   The key of the elements to loop through.
     * @param callable $callback
     *   Function used for applying adjustments.
     *
     * @return array
     *   Return the modified data.
     */
    private function queryElements(string $searchFunction, string $dataKey, callable $callback): array
    {
        $issues = [];

        $continue = true;
        $page = 1;
        while ($continue) {
            $issueData = $this->sonarQube->$searchFunction($this->projectKey, $page);
            $components = $issueData['components'];

            foreach ($issueData[$dataKey] as $issue) {
                $component = $issue['component'];
                if (!isset($issues[$component])) {
                    $issues[$component] = Util::findComponent($components, $component);
                    $issues[$component]['items'] = [];
                }

                $issue = $callback($issue);

                $issues[$component]['items'][] = $issue;
            }

            $total = $issueData['paging']['total'];
            $perPage = $issueData['paging']['pageSize'];
            $continue = $this->sonarQube->continueToNextPage($page, $perPage, $total);
        }

        return $issues;
    }

    /**
     * Return all project issues.
     *
     * @return array
     *   Return all data.
     *
     * @throws Exception
     */
    private function getProjectIssues(): array
    {
        $issues = $this->queryElements('getIssuesSearch', 'issues', static function ($issue) {
            // if line isn't set use the textRange attribute
            $issue['line'] ??= $issue['textRange']['startLine'] ?? '';
            $issue['severity_level'] = Util::getSeverityLevel($issue['severity']);
            return $issue;
        });

        return $this->sortElements($issues, 'severity_level');
    }

    /**
     * Return project hotspots.
     *
     * @return array
     *   Return all data.
     *
     * @throws Exception
     */
    private function getProjectHotSpots(): array
    {
        $hotspots = $this->queryElements('getHotSpotsSearch', 'hotspots', function ($hotspot) {
            $hotspot['vulnerability_level'] = Util::getVulnerabilityLevel($hotspot['vulnerabilityProbability']);

            $hotspot['line'] ??= $hotspot['textRange']['startLine'] ?? '';

            $source = $this->sonarQube->getSourceSnippet($hotspot['key']);
            $hotspot['source'] = array_reduce(
                $source[$hotspot['component']]['sources'] ?? [],
                static fn($source, $item): string => $source . (strip_tags((string) $item['code']) . PHP_EOL)
            );

            $hotspot['info'] = $this->sonarQube->getHotSpot($hotspot['key']);

            return $hotspot;
        });

        return $this->sortElements($hotspots, 'vulnerability_level');
    }

    /**
     * Return all project duplications.
     *
     * @return array
     *   Return all data.
     */
    private function getProjectDuplications(): array
    {
        $duplications = [];

        $continue = true;
        $page = 1;
        while ($continue) {
            $metricData = $this->sonarQube->getDuplicationsTree($this->projectKey, $page);

            // Loop through all elements.
            foreach ($metricData['components'] as $metric) {
                // if the element isn't a file skip.
                if ($metric['qualifier'] !== 'FIL') {
                    continue;
                }

                $metric['measures'] = array_combine(
                    array_column($metric['measures'], 'metric'),
                    array_column($metric['measures'], 'value')
                );

                $duplications[$metric['key']] = $metric;
            }

            $total = $metricData['paging']['total'];
            $perPage = $metricData['paging']['pageSize'];
            $continue = $this->sonarQube->continueToNextPage($page, $perPage, $total);
        }

        ksort($duplications);
        return $duplications;
    }

    /**
     * Return a summary of project duplications.
     *
     * @return array
     *   Return all data.
     *
     * @throws JsonException
     */
    private function getProjectDuplicationsSummary(): array
    {
        $metricData = $this->sonarQube->getDuplicationsTree($this->projectKey);
        $data = [];
        foreach ($metricData['baseComponent']['measures'] as $value) {
            $data[$value['metric']] = $value['value'];
        }

        return $data;
    }
}
