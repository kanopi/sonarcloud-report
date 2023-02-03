<?php

namespace Kanopi\SonarQube;

/**
 *
 */
class Project
{
    protected SonarQube $sonarQube;
    protected string $project;

    protected array $summary;

    protected array $items;

    /**
     * @param SonarQube $sonarQube
     * @param string $projectKey
     */
    public function __construct(SonarQube $sonarQube, string $projectKey)
    {
        $this->project = $projectKey;
        $this->sonarQube = $sonarQube;
    }

    public function getName()
    {
        $measures = $this->getMeasuresComponents();
        return $measures['component']['name'];
    }

    public function getSummary()
    {
        if (empty($this->summary)) {
            $this->summary = [
                'name' => $this->getName(),
                'lastrun' => $this->getLastRun(),
                'info' => $this->getTotalInfo(),
                'minor' => $this->getTotalMinor(),
                'major' => $this->getTotalMajor(),
                'critical' => $this->getTotalCritical(),
                'blocker' => $this->getTotalBlocked(),
                'code_smell' => $this->getTotalCodeSmells(),
                'bugs' => $this->getTotalBugs(),
                'vulnerability' => $this->getTotalVulnerabilities(),
                'hotspot' => $this->getTotalHotSpots(),
                'lines' => $this->getTotalLines(),
            ];
        }

        return $this->summary;
    }

    public function getItems()
    {
        if (empty($this->items)) {
            $this->items = [
                'issues' => $this->getIssues(),
                'hotspots' => $this->getHotSpots(),
                'vulnerabilities' => $this->getVulnerabilities(),
                'duplications' => $this->getDuplications(),
            ];
        }

        return $this->items;
    }

    protected function getTotalMeasures($type)
    {
        $measures = $this->getMeasuresComponents();
        return Util::findMetric($measures, $type);
    }

    public function getTotalCodeSmells()
    {
        return $this->getTotalMeasures('code_smells');
    }

    public function getTotalBugs()
    {
        return $this->getTotalMeasures('bugs');
    }

    public function getTotalVulnerabilities()
    {
        return $this->getTotalMeasures('vulnerabilities');
    }

    public function getTotalHotSpots()
    {
        return $this->getTotalMeasures('security_hotspots');
    }

    public function getTotalLines()
    {
        return $this->getTotalMeasures('ncloc');
    }

    protected function getTotalSeverity($type)
    {
        $severities = $this->getSeveritySummary();
        return Util::findSeverity($severities, $type);
    }

    public function getTotalInfo()
    {
        return $this->getTotalSeverity('INFO');
    }

    public function getTotalMinor()
    {
        return $this->getTotalSeverity('MINOR');
    }

    public function getTotalMajor()
    {
        return $this->getTotalSeverity('MAJOR');
    }

    public function getTotalCritical()
    {
        return $this->getTotalSeverity('CRITICAL');
    }

    public function getTotalBlocked()
    {
        return $this->getTotalSeverity('BLOCKER');
    }

    protected function getLastRun()
    {
        $response = $this->sonarQube->getProjectAnalyses($this->project);
        return $response['analyses'][0]['date'];
    }

    protected function getMeasuresComponents()
    {
        return $this->sonarQube->getMeasuresComponents($this->project);
    }

    protected function getSeveritySummary()
    {
        $response = $this->sonarQube->getIssuesSearch($this->project, 1, ['severities']);
        return $response['facets'][0];
    }

    public function getIssues()
    {
        return $this->getProjectIssues();
    }

    public function getVulnerabilities()
    {
        return $this->getProjectVulnerabilities();
    }

    public function getHotSpots()
    {
        return $this->getProjectHotSpots();
    }

    public function getDuplications()
    {
        $duplications = $this->getProjectDuplications();

        $info = [
            'lines' => 'duplicated_lines',
            'files' => 'duplicated_files',
            'blocks' => 'duplicated_blocks',
        ];

        foreach ($info AS $key => $filter) {
            $info[$key] = array_filter($duplications, function($item) use ($filter) {
                return $item['measures'][$filter] > 0;
            });
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

    protected function getProjectVulnerabilities()
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
            if ($continue = (($page + 1) * $perPage < $total)) {
                $page++;
            }
        }

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
        ksort($issues);

        return $issues;
    }

    protected function getProjectIssues()
    {
        $issues = [];

        $continue = true;
        $page = 1;
        while ($continue) {
            $issueData = $this->sonarQube->getIssuesSearch($this->project, $page);
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

                $issues[$component]['items'][] = $issue;
            }

            $total = $issueData['paging']['total'];
            $perPage = $issueData['paging']['pageSize'];
            if ($continue = (($page + 1) * $perPage < $total)) {
                $page++;
            }
        }

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
        ksort($issues);

        return $issues;
    }

    protected function getProjectHotSpots()
    {
        $hotspots = [];

        $continue = true;
        $page = 1;
        while ($continue) {
            $hotspotsData = $this->sonarQube->getHotSpotsSearch($this->project, $page);
            $components = $hotspotsData['components'];

            foreach ($hotspotsData['hotspots'] AS $hotspot) {
                $component = $hotspot['component'];
                if (!isset($hotspots[$component])) {
                    $hotspots[$component] = Util::findComponent($components, $component);
                    $hotspots[$component]['items'] = [];
                }

                $hotspot['vulnerability_level'] = Util::getVulnerabilityLevel($hotspot['vulnerabilityProbability']);

                $source = $this->sonarQube->getSourceSnippet($hotspot['key']);
                $hotspot['source'] = array_reduce($source[$hotspot['component']]['sources'] ?? [], function($source, $item) {
                    $source .= ( strip_tags($item['code']) . PHP_EOL);
                    return $source;
                });

                $hotspot['info'] = $this->sonarQube->getHotSpot($hotspot['key']);

                $hotspots[$component]['items'][] = $hotspot;
            }

            $total = $hotspotsData['paging']['total'];
            $perPage = $hotspotsData['paging']['pageSize'];
            if ($continue = (($page + 1) * $perPage < $total)) {
                $page++;
            }
        }

        foreach ($hotspots AS $component => &$items) {
            try {
                array_multisort(
                    array_column($items['items'], 'vulnerability_level'),
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

        ksort($hotspots);
        return $hotspots;
    }

    protected function getProjectDuplications()
    {
        $duplications = [];

        $continue = true;
        $page = 1;
        while ($continue) {
            $metricData = $this->sonarQube->getDuplicationsTree($this->project, $page);

            foreach ($metricData['components'] AS $metric) {
                if ($metric['qualifier'] !== 'FIL') continue;
                $metric['measures'] = array_combine(
                    array_column($metric['measures'], 'metric'),
                    array_column($metric['measures'], 'value')
                );

//                if ($metric['measures']['duplicated_lines'] == 0 &&
//                    $metric['measures']['duplicated_files'] == 0 &&
//                    $metric['measures']['duplicated_blocks'] == 0) {
//                    continue;
//                }

                $duplications[$metric['key']] = $metric;
            }

            $total = $metricData['paging']['total'];
            $perPage = $metricData['paging']['pageSize'];
            if ($continue = (($page + 1) * $perPage < $total)) {
                $page++;
            }
        }

        ksort($duplications);
        return $duplications;
    }

    protected function getProjectDuplicationsSummary()
    {
        $metricData = $this->sonarQube->getDuplicationsTree($this->project);
        $data = [];
        foreach ($metricData['baseComponent']['measures'] AS $value)
        {
            $data[$value['metric']] = $value['value'];
        }
        return $data;
    }
}