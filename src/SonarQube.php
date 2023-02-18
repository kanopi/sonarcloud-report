<?php

namespace Kanopi\SonarQube;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class SonarQube
 */
final class SonarQube
{

    private readonly Client $client;

    /**
     * @var string[]
     */
    private const METRICS = [
        'code_smells',
        'coverage',
        'bugs',
        'vulnerabilities',
        'security_hotspots',
        'duplicated_lines',
        'lines',
        'ncloc',
    ];

    /**
     * Constructor
     *
     * @param string $host
     *   Host name of SonarQube service.
     * @param string $user
     *   Username for authentication.
     * @param string $pass
     *   Password for authentication.
     */
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

    /**
     * Query the endpoint.
     *
     * @param string $endpoint
     *   The endpoint to query.
     * @param array $query
     *   Query parameters.
     *
     * @return array
     *   Return the data from the query.
     */
    private function query(string $endpoint, array $query = []): array
    {
        try {
            $response = $this->client->get($endpoint, ['query' => $query]);
            return (array)json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (GuzzleException $guzzleException) {
            echo sprintf("ERROR: %s", $guzzleException->getMessage());
            die;
        }
    }

    /**
     * Return a list of all measure components.
     *
     * @param string $project
     *   Project name to query.
     * @param array $metrics
     *   List of metrics to return.
     *
     * @return array
     *   Return data.
     */
    public function getMeasuresComponents(string $project, array $metrics = []): array
    {
        $metrics = $metrics === [] ? self::METRICS : $metrics;
        return $this->query('/api/measures/component', [
            'component' => $project,
            'metricKeys' => implode(',', $metrics)
        ]);
    }

    /**
     * Return the measure component tree.
     *
     * @param string $project
     *   Project name to query.
     * @param array $metrics
     *   List of all metrics to return.
     * @param int $page
     *   Page number to request and query.
     *
     * @return array
     *   Return all data.
     */
    public function getMeasuresComponentsTree(string $project, array $metrics, int $page = 1): array
    {
        return $this->query('/api/measures/component_tree', [
           'component' => $project,
           'metricKeys' => implode(',', $metrics),
            'ps' => 500,
            'p' => $page,
        ]);
    }

    /**
     * Return a list of all metrics.
     *
     * @param int $page
     *   Query specific page number.
     *
     * @return array
     *   Return all data.
     */
    public function getMetrics(int $page = 1): array
    {
        return $this->query('/api/metrics/search', [
            'ps' => 500,
            'p' => $page,
        ]);
    }

    /**
     * Query a list of all issues for a project.
     *
     * @param string $project
     *   Project name.
     * @param int $page
     *   Page number to query.
     * @param array $facetTypes
     *   Query a specific number of facets.
     * @param array $types
     *   A list of all issue types.
     *
     * @return array
     *   Return all data.
     */
    public function getIssuesSearch(string $project, int $page = 1, array $facetTypes = [], array $types = []): array
    {
        return $this->query('/api/issues/search', [
            'componentKeys' => $project,
            'ps' => 500,
            'p' => $page,
            'facets' => implode(',', $facetTypes),
            'types' => implode(',', $types),
        ]);
    }

    /**
     * Return a list of all Hotspots for a project.
     *
     * @param string $project
     *   Project name.
     * @param int $page
     *   Query specific page number.
     *
     * @return array
     *   Return all data.
     */
    public function getHotSpotsSearch(string $project, int $page = 1): array
    {
        $query = [
            'projectKey' => $project,
            'ps' => 500,
            'p' => $page,
        ];
        return $this->query('/api/hotspots/search', $query);
    }

    /**
     * Return source code for specific file.
     *
     * @param string $key
     *   File key to query.
     * @param string|int $from
     *   Line number to start at.
     * @param string|int $to
     *   Line number to end at.
     *
     * @return array
     *   Return all data.
     */
    public function getSourceLines(string $key, string|int $from, string|int $to): array
    {
        return $this->query('/api/sources/lines', [
            'key' => $key,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * Return snippet for specific issue key.
     *
     * @param string $issueKey
     *   Issue key to query.
     *
     * @return array
     *   Return all data.
     */
    public function getSourceSnippet(string $issueKey): array
    {
        return $this->query('/api/sources/issue_snippets', [
            'issueKey' => $issueKey,
        ]);
    }

    /**
     * Return details about hotspot.
     *
     * @param string $hotspot
     *   Hotspot ID to query.
     *
     * @return array
     *   Return all data.
     */
    public function getHotSpot(string $hotspot): array
    {
        return $this->query('/api/hotspots/show', [
            'hotspot' => $hotspot,
        ]);
    }

    /**
     * Return project summary.
     *
     * @param string $project
     *   Project to query.
     *
     * @return array
     *   Return all data.
     */
    public function getProjectAnalyses(string $project): array
    {
        return $this->query('/api/project_analyses/search', [
            'project' => $project,
        ]);
    }

    /**
     * Return a specific rule.
     *
     * @param string $rule
     *   Rule ID to query.
     *
     * @return array
     *   Return the data.
     */
    public function getRule(string $rule): array
    {
        return $this->query('/api/rules/show', [
            'key' => $rule,
        ]);
    }

    /**
     * Return a list of all rules.
     *
     * @param int $page
     *   Page number to query.
     *
     * @return array
     *   Return all data.
     */
    public function searchRules(int $page = 1): array
    {
        return $this->query('/api/rules/search', [
            'ps' => 500,
            'p' => $page
        ]);
    }

    /**
     * Can the query continue on to the next page.
     *
     * @param int $page
     *   Current page number.
     * @param int $perPage
     *   Number of items available per page.
     * @param int $total
     *   Total number of items across all pages.
     *
     * @return bool
     *   Can continue to the next page.
     */
    public function continueToNextPage(int &$page, int $perPage, int $total): bool
    {
        ++$page;
        return (
            ($page * $perPage < $total) &&
            ($page * $perPage <= 10000)
        );
    }

    /**
     * Return a list of all duplications for the specific file key.
     *
     * @param string $key
     *   File key to query.
     *
     * @return array
     *   Return all data.
     */
    public function getDuplications(string $key): array
    {
        return $this->query('/api/duplications/show', [
            'key' => $key,
        ]);
    }

    /**
     * Return a list of all duplications.
     *
     * @param string $project
     *   Project name to query.
     * @param int $page
     *   Page number to query.
     *
     * @return array
     *   Return all data.
     */
    public function getDuplicationsTree(string $project, int $page = 1): array
    {
        return $this->getMeasuresComponentsTree($project, [
            'duplicated_lines',
            'duplicated_blocks',
            'duplicated_lines_density',
            'duplicated_files',
        ], $page);
    }
}
