<?php

namespace Kanopi\SonarQube;

/**
 * Utility Class used for altering functions.
 */
class Util
{

    /**
     * Search data for a specific metric element.
     *
     * @param array $data
     *   Data to search through.
     * @param string $metric
     *   Metric to query and return data for.
     *
     * @return string|null
     *   Return the data.
     */
    public static function findMetric(array $data, string $metric): ?string
    {
        foreach ($data['component']['measures'] AS $measure) {
            if ($measure['metric'] === $metric) {
                return $measure['value'];
            }
        }

        return null;
    }

    /**
     * Return the value for the specific
     *
     * @param array $data
     *   Data to search through.
     * @param string $metric
     *   Metric to query and return data for.
     *
     * @return string|null
     *   Return the data.
     */
    public static function findSeverity(array $data, string $metric): ?string
    {
        foreach ($data['values'] AS $value) {
            if ($value['val'] === $metric) {
                return $value['count'];
            }
        }

        return null;
    }

    /**
     * Return the Component for the
     *
     * @param array $components
     *   Data to search through.
     * @param string $component
     *   Component to search for.
     *
     * @return mixed
     *   Return the data.
     */
    public static function findComponent(array $components, string $component): mixed
    {
        foreach ($components AS $item) {
            if ($item['key'] === $component) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Return the severity level.
     *
     * @param string $severity
     *   Severity to search for.
     *
     * @return int
     *   Return level number.
     */
    public static function getSeverityLevel(string $severity): int
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

    /**
     * Return the vulnerability level.
     *
     * @param string $vulnerability
     *   Vulnerability to search for.
     *
     * @return int
     *   Return level number.
     */
    public static function getVulnerabilityLevel(string $vulnerability): int
    {
        $level = [
            'HIGH' => -1,
            'MEDIUM' => -2,
            'LOW' => -1,
        ];
        return $level[$vulnerability] ?? 0;
    }
}
