<?php

use SourceAnalyzer\Helper\PhpTypeHintIndex;

require __DIR__ . '/exceptional.php';
require_once __DIR__ . '/../vendor/autoload.php';

$docFile = __DIR__ . '/../assets/php_manual_en.html.gz';
$stream = gzopen($docFile, 'r');
assert(!!$stream);

$indexer = new PhpTypeHintIndex();
$indexer->setDebugEnabled(true);
$index = $indexer->indexPhpDocStream($stream);
$indexer->saveIndex($index);

