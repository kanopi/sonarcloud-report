#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Kanopi\SonarQube\RunReport;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$sonarQubeHost = $_ENV['SONARQUBE_HOST'] ?? 'https://sonarcloud.io';
$sonarQubeUser = $_ENV['SONARQUBE_USER'];
$sonarQubePass = $_ENV['SONARQUBE_PASS'];
$sonarQubeProjects = explode(',', $_ENV['SONARQUBE_PROJECTS']);
$sonarQubeReportDir = $_ENV['SONARQUBE_REPORT_DIR'] ?? './';
$sonarQubeFile = $sonarQubeReportDir . ($_ENV['SONARQUBE_REPORT_FILE'] ?? 'report.pdf');

if (empty($sonarQubeHost) || empty($sonarQubeUser) || empty($sonarQubeProjects)) {
    echo "One of the following variables are not defined: SONARQUBE_HOST, SONARQUBE_USER, SONARQUBE_PROJECTS";
    die;
}

RunReport::run(
    $sonarQubeHost,
    $sonarQubeUser,
    $sonarQubePass,
    $sonarQubeProjects,
    $sonarQubeFile
);
