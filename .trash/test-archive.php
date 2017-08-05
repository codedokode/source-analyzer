<?php

use \wapmorgan\UnifiedArchive\UnifiedArchive;

require_once __DIR__ . '/../lib/exceptional.php';
require_once __DIR__ . '/../vendor/autoload.php';

$files = __DIR__ . '/../assets/*';
foreach (glob($files) as $file) {
    echo "$file\n";
    $archive = UnifiedArchive::open($file);
    if (!$archive) {
        echo "  (failed)\n";
        continue;
    }

    foreach ($archive->getFileNames() as $relPath) {
        $data = $archive->getFileData($relPath);
        if ($data->uncompressed_size === 0) {
            continue;
        }
        
        $content = $archive->getFileContent($relPath);
        $hash = substr($content, 0, 20);
        echo "  $relPath\n";
        echo $hash . "...\n";
    }
}