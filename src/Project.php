<?php

namespace Kanopi\SonarQube;

/**
 * Class Project.
 *
 * Project class related to a specific SonarQube project and used
 * for generating the report.
 */
class Project
{
    protected SonarQube $sonarQube;

    protected string $project;

    protected array $summary;

    protected array $items;

    /**
     * Constructor
     *
     * @param SonarQube $sonarQube
     *   SonarQube API Client.
     * @param string $projectKey
     *   Project key to query.
     */
    public function __construct(SonarQube $sonarQube, string $projectKey)
    {
        $this->project = $projectKey;
        $this->sonarQube = $sonarQube;
    }

    /**
     * Return projects label.
     *
     * @return string
     *   Project label.
     */
    public function getName(): string
    {
        $measures = $this->getMeasuresComponents();
        return $measures['component']['name'];
    }

    /**
     * Return summary for project.
     *
     * @return array<string, string>
     *   Summary details.
     */
    public function getSummary(): array
    {
        if (empty($this->summary)) {
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
     */
    public function getItems(): array
    {
        if (empty($this->items)) {
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
     * @return string
     *   Total number of items.
     */
    protected function getTotalMeasures(string $type): string
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
     * @return string
     *   Total number of items.
     */
    protected function getTotalSeverity(string $type): string
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
    protected function getLastRun(): string
    {
        $response = $this->sonarQube->getProjectAnalyses($this->project);
        return $response['analyses'][0]['date'];
    }

    /**
     * Get all project measure components.
     *
     * @return mixed
     *   Return all data.
     */
    protected function getMeasuresComponents(): mixed
    {
        return $this->sonarQube->getMeasuresComponents($this->project);
    }

    /**
     * Get a list of all severities.
     *
     * @return array
     *   Return all data.
     */
    protected function getSeveritySummary(): array
    {
        $response = $this->sonarQube->getIssuesSearch($this->project, 1, ['severities']);
        return $response['facets'][0];
    }

    /**
     * Return all duplications.
     *
     * @return array
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
        foreach ($info AS $key => $filter) {
            // Filter out items that have duplications.
            $info[$key] = array_filter($duplications, function($item) use ($filter) {
                return $item['measures'][$filter] > 0;
            });
            // For anything but files sort in descending order.
            if ($key !== 'files') {
                uasort($info[$key], function ($a, $b) use ($filter) {
                    if ($a['measures'][$filter] === $b['measures'][$filter]) return 0;
                    return $a['measures'][$filter] < $b['measures'][$filter] ? -1 : 1;
                });
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
     */
    protected function getProjectVulnerabilities(): array
    {
        $issues = [];

        $continue = true;
        $page = 1;
        while ($continue) {
            $issueData = $this->sonarQube->getIssuesSearch($this->project, $page, [], ['VULNERABILITY']);
            $components = $issueData['components'];

            foreach ($issueData['issues'] AS $issue) {
                $component = $issue['component'];
                if (!isset($issues[$component])) {
                    $issues[$component] = Util::findComponent($components, $component);
                    $issues[$component]['items'] = [];
                }

                // if line isn't set use the textRange attribute
                if (!isset($issue['line'])) {
                    $issue['line'] = $issue['textRange']['startLine'] ?? '';
                }
                $issue['severity_level'] = Util::getSeverityLevel($issue['severity']);

                $source = $this->sonarQube->getSourceSnippet($issue['key']);

                $issue['source'] = array_reduce($source[$issue['component']]['sources'] ?? [], function($source, $item) {
                    $source .= ( strip_tags($item['code']) . PHP_EOL);
                    return $source;
                });

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
     */
    protected function sortElements(array $issues, string $sortKey): array
    {
        foreach ($issues AS $component => &$items) {
            try {
                array_multisort(
                    array_column($items['items'], $sortKey),
                    SORT_ASC,
                    SORT_REGULAR,
                    array_column($items['items'], 'line'),
                    SORT_ASC,
                    SORT_REGULAR,
                    $items['items']
                );
            } catch(\ValueError $exception) {
                echo sprintf('ERROR: %s with %s', $exception->getMessage(), $component);
                die;
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
     * @param callable $callback
     *   Function used for applying adjustments.
     *
     * @return array
     *   Return the modified data.
     */
    protected function queryElements(string $searchFunction, callable $callback): array
    {
        $issues = [];

        $continue = true;
        $page = 1;
        while ($continue) {
            $issueData = $this->sonarQube->$searchFunction($this->project, $page);
            $components = $issueData['components'];

            foreach ($issueData['issues'] AS $issue) {
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
     */
    protected function getProjectIssues(): array
    {
        $issues = $this->queryElements('getIssuesSearch', function($issue) {
            // if line isn't set use the textRange attribute
            if (!isset($issue['line'])) {
                $issue['line'] = $issue['textRange']['startLine'] ?? '';
            }
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
     */
    protected function getProjectHotSpots(): array
    {
        $hotspots = $this->queryElements('getHotSpotsSearch', function($hotspot) {
            $hotspot['vulnerability_level'] = Util::getVulnerabilityLevel($hotspot['vulnerabilityProbability']);

            $source = $this->sonarQube->getSourceSnippet($hotspot['key']);
            $hotspot['source'] = array_reduce($source[$hotspot['component']]['sources'] ?? [], function($source, $item) {
                $source .= ( strip_tags($item['code']) . PHP_EOL);
                return $source;
            });

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
    protected function getProjectDuplications(): array
    {
        $duplications = [];

        $continue = true;
        $page = 1;
        while ($continue) {
            $metricData = $this->sonarQube->getDuplicationsTree($this->project, $page);

            // Loop through all elements.
            foreach ($metricData['components'] AS $metric) {
                // if the element isn't a file skip.
                if ($metric['qualifier'] !== 'FIL') continue;
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
     */
    protected function getProjectDuplicationsSummary(): array
    {
        $metricData = $this->sonarQube->getDuplicationsTree($this->project);
        $data = [];
        foreach ($metricData['baseComponent']['measures'] AS $value) {
            $data[$value['metric']] = $value['value'];
        }
        return $data;
    }
}
