<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/exceptional.php';
require_once __DIR__ . '/studentProjectChecker.php';

if (count($argv) != 2) {
    usage();
    die();
}

function usage() {
    fwrite(STDERR, "Usage: script project-dir\n" . 
                   "        checks student list task for obvious errors\n");
}

$projectDir = $argv[1];

$checkers = array_merge(
    getCheckers('Common'),
    getCheckers('StudentList')
);

checkProjectInDirectory($projectDir, $checkers);
analyzeProject($checkers);

