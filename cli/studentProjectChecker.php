<?php

use SourceAnalyzer\File;

function checkProjectInDirectory($projectDir, array $checkers)
{
    $phpParser = new PhpParser\Parser(new PhpParser\Lexer\Emulative);

    assert(is_dir($projectDir));
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($projectDir));

    foreach ($iterator as $relPath => $splFile) {

        if (!$splFile->isFile()) {
            continue;
        }

        $path = $splFile->getPathname();

        if (preg_match("#([/\\\\]|\A)\.git[/\\\\]#", $path)) {
            continue;
        }

        if (preg_match("#([/\\\\]|\A)vendor[/\\\\]#", $path)) {
            continue;
        }

        $contents = file_get_contents($path);

        $file = new File($contents, $path);
        $file->setPhpParser($phpParser);

        checkFile($checkers, $file);
    }
}

function checkFile(array $checkers, File $file)
{
    foreach ($checkers as $checker) {
        $checker->check($file);
    }    
}

function analyzeProject(array $checkers)
{
    foreach ($checkers as $checker) {
        $checker->analyze();
        foreach ($checker->getErrors() as $error) {
            echo $error . "\n\n";
        }
    }
}

function getCheckers($namespace, array $skipClasses = array())
{
    $mask = __DIR__ . "/../src/$namespace/*.php";
    $files = glob($mask);

    assert($files !== false);
    $checkers = [];

    foreach ($files as $file) {
        $className = pathinfo(basename($file), PATHINFO_FILENAME);
        $fullClassName = '\\SourceAnalyzer\\' . $namespace .'\\' . $className;

        // Skip
        if (in_array($className, $skipClasses)) {
            continue;
        }

        $checkers[] = new $fullClassName;
    }

    return $checkers;
}
