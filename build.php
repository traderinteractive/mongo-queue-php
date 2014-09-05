#!/usr/bin/env php
<?php
chdir(__DIR__);

$returnStatus = null;
passthru('composer install', $returnStatus);
if ($returnStatus !== 0) {
    exit(1);
}

require 'vendor/autoload.php';

$phpcsCLI = new PHP_CodeSniffer_CLI();
$phpcsArguments = [
    'standard' => [__DIR__ . '/vendor/dominionenterprises/dws-coding-standard/DWS'],
    'files' => ['src', 'tests', 'build.php'],
    'warningSeverity' => 0,
];
$phpcsViolations = $phpcsCLI->process($phpcsArguments);
if ($phpcsViolations > 0) {
    exit(1);
}

$phpunitConfiguration = PHPUnit_Util_Configuration::getInstance(__DIR__ . '/phpunit.xml');
$phpunitArguments = ['coverageHtml' => __DIR__ . '/coverage', 'configuration' => $phpunitConfiguration];
$testRunner = new PHPUnit_TextUI_TestRunner();
$result = $testRunner->doRun($phpunitConfiguration->getTestSuiteConfiguration(), $phpunitArguments);
if (!$result->wasSuccessful()) {
    exit(1);
}

$cloverCoverage = new PHP_CodeCoverage_Report_Clover();
file_put_contents('clover.xml', $cloverCoverage->process($result->getCodeCoverage()));

$coverageFactory = new PHP_CodeCoverage_Report_Factory();
$coverageReport = $coverageFactory->create($result->getCodeCoverage());
if ($coverageReport->getNumExecutedLines() !== $coverageReport->getNumExecutableLines()) {
    file_put_contents('php://stderr', "Code coverage was NOT 100%\n");
    exit(1);
}

echo "Code coverage was 100%\n";
