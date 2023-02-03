<?php

namespace Kanopi\SonarQube;

class Util
{
    public static function findMetric(array $data, string $metric) {
        foreach ($data['component']['measures'] AS $measure) {
            if ($measure['metric'] === $metric) {
                return $measure['value'];
            }
        }
    }

    public static function findSeverity(array $data, string $metric) {
        foreach ($data['values'] AS $value) {
            if ($value['val'] === $metric) {
                return $value['count'];
            }
        }
    }

    public static function findComponent(array $components, string $component) {
        foreach ($components AS $item) {
            if ($item['key'] === $component) {
                return $item;
            }
        }
    }

    public static function getSeverityLevel(string $severity)
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

    public static function getVulnerabilityLevel(string $vulnerability)
    {
        $level = [
            'HIGH' => -1,
            'MEDIUM' => -2,
            'LOW' => -1,
        ];
        return $level[$vulnerability] ?? 0;
    }
}
