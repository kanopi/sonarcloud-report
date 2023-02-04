<?php

namespace Kanopi\SonarQube;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class SonarQube
{

    protected Client $client;

    protected array $metrics = ['code_smells', 'coverage', 'bugs', 'vulnerabilities', 'security_hotspots', 'duplicated_lines', 'lines', 'ncloc'];

    protected array $issueTypes = ['CODE_SMELL', 'VULNERABILITY', 'BUG'];

    protected array $facetTypes = ['severity'];

    protected array $ruleItems = [];
    protected array $metricItems = [];

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

    public function getMeasuresComponents(string $project, array $metrics = [])
    {
        $metrics = empty($metrics) ? $this->metrics : $metrics;
        return $this->query('/api/measures/component', [
            'component' => $project,
            'metricKeys' => implode(',', $metrics)
        ]);
    }

    public function getMeasuresComponentsTree(string $project, array $metrics, int $page = 1)
    {
        return $this->query('/api/measures/component_tree', [
           'component' => $project,
           'metricKeys' => implode(',', $metrics),
            'ps' => 500,
            'p' => $page,
        ]);
    }

    public function getMetrics(int $page = 1)
    {
        return $this->query('/api/metrics/search', [
            'ps' => 500,
            'p' => $page,
        ]);
    }

    public function getIssuesSearch(string $project, int $page = 1, array $facetTypes = [], array $types = [])
    {
        return $this->query('/api/issues/search', [
            'componentKeys' => $project,
            'ps' => 500,
            'p' => $page,
            'facets' => implode(',', $facetTypes),
            'types' => implode(',', $types),
        ]);
    }

    public function getHotSpotsSearch(string $project, int $page = 1)
    {
        $query = [
            'projectKey' => $project,
            'ps' => 500,
            'p' => $page,
        ];
        return $this->query('/api/hotspots/search', $query);
    }

    public function getSourceLines(string $key, string|int $from, string|int $to)
    {
        return $this->query('/api/sources/lines', [
            'key' => $key,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function getSourceSnippet(string $issueKey)
    {
        return $this->query('/api/sources/issue_snippets', [
            'issueKey' => $issueKey,
        ]);
    }

    public function getHotSpot(string $hotspot)
    {
        return $this->query('/api/hotspots/show', [
            'hotspot' => $hotspot,
        ]);
    }

    public function getProjectAnalyses(string $project)
    {
        return $this->query('/api/project_analyses/search', [
            'project' => $project,
        ]);
    }

    public function getRule(string $rule)
    {
        return $this->query('/api/rules/show', [
            'key' => $rule,
        ]);
    }

    public function searchRules(int $page = 1)
    {
        return $this->query('/api/rules/search', [
            'ps' => 500,
            'p' => $page
        ]);
    }

    public function continueToNextPage(&$page, $perPage, $total)
    {
        $page++;
        return (
            ($page * $perPage < $total) &&
            ($page * $perPage <= 10000)
        );
    }

    public function getAllRules()
    {
        if (!empty($this->ruleItems)) {
            return $this->ruleItems;
        }

        $rules = [];
        $page = 1;
        $continue = true;
        while($continue) {
            $rulesData = $this->searchRules($page);
            foreach ($rulesData['rules'] AS $rule) {
                $rules[$rule['key']] = $rule;
            }

            $perPage = $rulesData['ps'];
            $total = $rulesData['total'];
            $continue = $this->continueToNextPage($page, $perPage, $total);
        }

        $this->ruleItems = $rules;
        return $rules;
    }

    public function getAllMetrics()
    {
        if (!empty($this->metricItems)) {
            return $this->metricItems;
        }

        $metrics = [];
        $page = 1;
        $continue = true;
        while ($continue) {
            $metricData = $this->getMetrics($page);
            foreach ($metricData['metrics'] AS $metric) {
                $metrics[$metric['key']] = $metric;
            }

            $perPage = $metricData['ps'];
            $total = $metricData['total'];
            $continue = $this->continueToNextPage($page, $perPage, $total);
        }
        $this->metricItems = $metrics;
        return $metrics;
    }

    public function getDuplications(string $key)
    {
        return $this->query('/api/duplications/show', [
            'key' => $key,
        ]);
    }

    public function getDuplicationsTree(string $project, int $page = 1)
    {
        return $this->getMeasuresComponentsTree($project, [
            'duplicated_lines',
            'duplicated_blocks',
            'duplicated_lines_density',
            'duplicated_files',
        ], $page);
    }
}
