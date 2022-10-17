<?php

namespace Kanopi\SonarQube;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class SonarQube
{

    protected Client $client;

    protected array $metrics = ['code_smells', 'coverage', 'bugs', 'vulnerabilities', 'security_hotspots', 'duplicated_lines', 'lines', 'ncloc'];

    protected array $summary = [];

    protected array $issues = [];

    public function __construct(string $host, string $user, string $pass)
    {
        $this->client = new Client([
            'base_uri' => $host,
            'auth' => [$user, $pass],
            'headers' => [
                'Content-type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);
    }

    protected function query(string $endpoint, array $query = [])
    {
        try {
            $response = $this->client->get($endpoint, ['query' => $query]);
            return json_decode($response->getBody(), true);
        } catch (GuzzleException $exception) {
            echo sprintf("ERROR: %s", $exception->getMessage());
            die;
        }
    }

    protected function getMeasuresComponents(string $project, array $metrics = [])
    {
        return $this->query('/api/measures/component', [
            'component' => $project,
            'metricKeys' => implode(',', $metrics)
        ]);
    }

    protected function getIssuesSearch(string $project, array $facets = [], int $page = 1)
    {
        return $this->query('/api/issues/search', [
            'componentKeys' => $project,
            'ps' => 500,
            'p' => $page,
            'facets' => implode(',', $facets)
        ]);
    }

    protected function getProjectAnalyses(string $project)
    {
        return $this->query('/api/project_analyses/search', [
            'project' => $project
        ]);
    }

    // Helper Functions

    protected function getSeverityLevel(string $severity)
    {
        $severityLevel = [
            'BLOCKER' => -5,
            'CRITICAL' => -4,
            'MAJOR' => -3,
            'MINOR' => -2,
            'INFO' => -1
        ];

        return $severityLevel[$severity] ?? 0;
    }

    protected function getProjectIssues(string $project)
    {
        $continue = true;
        $page = 1;

        $issues = [];

        while ($continue) {
            $issueData = $this->getIssuesSearch($project, [], $page);
            $components = $issueData['components'];

            foreach ($issueData['issues'] AS $issue) {
                $component = $issue['component'];
                if (!isset($issues[$component])) {
                    $issues[$component] = $this->findComponent($components, $component);
                    $issues[$component]['items'] = [];
                }

                // if line isn't set use the textRange attribute
                if (!isset($issue['line'])) {
                    $issue['line'] = $issue['textRange']['startLine'];
                }
                $issue['severity_level'] = $this->getSeverityLevel($issue['severity']);

                $issues[$component]['items'][] = $issue;
            }

            $total = $issueData['total'];
            $perPage = $issueData['ps'];
            if ($continue = (($page + 1) * $perPage < $total)) {
                $page++;
            }
        }

        // Loop through and sort by severity_level then line
        foreach ($issues AS $component => &$items) {

            try {
                array_multisort(
                    array_column($items['items'], 'severity_level'),
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

        return $issues;
    }

    protected function getLastRun(string $project)
    {
        $response = $this->getProjectAnalyses($project);
        return $response['analyses'][0]['date'];
    }

    protected function getSeveritySummary(string $project)
    {
        $response = $this->getIssuesSearch($project, ['severities']);
        return $response['facets'][0];
    }

    protected function findMetric(array $data, string $metric) {
        foreach ($data['component']['measures'] AS $measure) {
            if ($measure['metric'] === $metric) {
                return $measure['value'];
            }
        }
    }

    protected function findSeverity(array $data, string $metric) {
        foreach ($data['values'] AS $value) {
            if ($value['val'] === $metric) {
                return $value['count'];
            }
        }
    }

    protected function findComponent(array $components, string $component) {
        foreach ($components AS $item) {
            if ($item['key'] === $component) {
                return $item;
            }
        }
    }

    public function getProjectSummary(string $project) {
        if (!isset($this->summary[$project])) {
            $measures = $this->getMeasuresComponents($project, $this->metrics);
            $severities = $this->getSeveritySummary($project);
            $lastRun = $this->getLastRun($project);

            $this->summary[$project] = [
                'name' => $measures['component']['name'],
                'lastrun' => $lastRun,
                'info' => $this->findSeverity($severities, 'INFO'),
                'minor' => $this->findSeverity($severities, 'MINOR'),
                'major' => $this->findSeverity($severities, 'MAJOR'),
                'critical' => $this->findSeverity($severities, 'CRITICAL'),
                'blocker' => $this->findSeverity($severities, 'BLOCKER'),
                'code_smell' => $this->findMetric($measures, 'code_smells'),
                'bugs' => $this->findMetric($measures, 'bugs'),
                'vulnerability' => $this->findMetric($measures, 'vulnerabilities'),
                'hotspot' => $this->findMetric($measures, 'security_hotspots'),
                'lines' => $this->findMetric($measures, 'ncloc'),
            ];
        }
        return $this->summary[$project];
    }

    public function getProjectRun(string $project) {
        if (!isset($this->issues[$project])) {
            $summary = $this->getProjectSummary($project);
            $issues = $this->getProjectIssues($project);
            $this->issues[$project] = [
                'info' => [
                    'name' => $summary['name'],
                    'lastrun' => $summary['lastrun'],
                    'summary' => $summary,
                ],
                'issues' => $issues,
            ];
        }
        return $this->issues[$project];
    }

}