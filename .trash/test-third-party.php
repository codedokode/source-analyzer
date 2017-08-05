<?php

require_once __DIR__ . '/../lib/exceptional.php';
require_once __DIR__ . '/../vendor/autoload.php';

$renamedFile = __DIR__ . '/test-files-unchanged/renamed.css';
$changedFile = __DIR__ . '/test-files-changed/bootstrap-theme.css';
$normalFile = __DIR__ .'/test-files-unchanged/css/bootstrap-theme.css';

$renamedJq = __DIR__ . '/test-files-unchanged/jquery-1.11.2.js';
$normalJq = __DIR__ . '/test-files-unchanged/jquery-1.11.2.js';
$changedJq = __DIR__ . '/test-files-changed/jquery-1.11.2.js';
$changedAndRenamedJq = __DIR__  . '/test-files-changed/renamed-and-changed-jquery-1.11.2.js';
$unknownFile = __DIR__ . '/test-files-unchanged/unknown.file';

$files = [
    $renamedFile            => ThirdPartyVerifier::STATUS_RENAMED, 
    $changedFile            => ThirdPartyVerifier::STATUS_CONTENT_CHANGED, 
    $normalFile             => ThirdPartyVerifier::STATUS_NOT_CHANGED,
    $renamedJq              => ThirdPartyVerifier::STATUS_RENAMED, 
    $normalJq               => ThirdPartyVerifier::STATUS_NOT_CHANGED, 
    $changedJq              => ThirdPartyVerifier::STATUS_CONTENT_CHANGED, 
    $changedAndRenamedJq    => ThirdPartyVerifier::STATUS_RENAMED_AND_CHANGED,
    $unknownFile            => ThirdPartyVerifier::STATUS_UNKNOWN_FILE
];

$checker = new ThirdPartyVerifier();

foreach ($files as $file => $expectedStatus) {
    $content = file_get_contents($file);
    echo "$file\n";

    $library = $checker->identifyFile($content);
    if (!$library) {
        echo "  not identified\n";
    } else {
        echo "  idd as $library\n";
    }

    $hash = $checker->getFileHash($content);
    $changeResult = $checker->getFileStatus($content, $file);
    $isRenamed = $checker->isFileRenamed($hash, $file);

    if (!$isRenamed) {
        echo "  not renamed\n";
    } else {
        echo "  renamed (lib={$isRenamed['library']}, file={$isRenamed['file']})\n";
    }

    assert(!!$changeResult);
    $status = $changeResult['status'];

    if ($status == ThirdPartyVerifier::STATUS_RENAMED) {
        assert(!!$isRenamed);
    } else {
        assert(!$isRenamed);
    }

    if ($status == ThirdPartyVerifier::STATUS_NOT_CHANGED) {
        echo "  not changed\n";
    } elseif ($status == ThirdPartyVerifier::STATUS_UNKNOWN_FILE) {
        echo "  cannot match to library file\n";
    } elseif ($status == ThirdPartyVerifier::STATUS_RENAMED_AND_CHANGED) {
        echo "  renamed and changed, lib={$changeResult['library']}\n";
    } elseif ($status == ThirdPartyVerifier::STATUS_RENAMED) {
        echo "  renamed, original={$changeResult['file']}\n";
    } elseif ($status == ThirdPartyVerifier::STATUS_CONTENT_CHANGED) {
        echo "  changed (lib: {$changeResult['library']}, file: '{$changeResult['file']}'):\n";
        $origin = $checker->extractOriginalFile($changeResult['library'], $changeResult['file']);
        $opts = ['ignoreWhitespace' => true];
        $originLines = explode("\n", $origin);
        $contentLines = explode("\n", $content);

        $diff = new Diff($originLines, $contentLines, $opts);
        $renderer = new Diff_Renderer_Text_Unified();

        echo $diff->Render($renderer);
        echo "\n\n";
    }

    assert($status == $expectedStatus);
}
