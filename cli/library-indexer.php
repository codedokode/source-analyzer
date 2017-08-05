<?php

use SourceAnalyzer\ThirdPartyVerifier;

require __DIR__ . '/exceptional.php';
require_once __DIR__ . '/../vendor/autoload.php';


$libraryDir = __DIR__ . '/../assets/libraries/';
$metadataPath = __DIR__ . '/../assets/metadata.json';

$indexer = new ThirdPartyVerifier($libraryDir, $metadataPath);
$indexer->enableDebug();
$index = $indexer->indexFiles($libraryDir);
$indexer->saveMetadataFile($index);

// var_dump($index);
