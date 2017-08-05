<?php

require_once __DIR__ . '/../lib/exceptional.php';

$file = __DIR__ . '/../assets/php_manual_en.html.gz';
$stream = gzopen($file, 'r');

$bufferSize = 100000;
$buffer = '';
$offset = 0;
$mCount = 0;
$failCount = 0;
$jsonIndex = [];

$pattern = "#<div[^>]+\bmethodsynopsis\b.*?</div>#is";

while (!feof($stream)) {
    
    readStream($stream, $buffer, $bufferSize, $offset);

    preg_match_all($pattern, $buffer, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    $maxOffset = floor($bufferSize / 2);

    foreach ($matches as $matchPair) {
        list($fullMatch, $matchOffset) = $matchPair[0];
        $localOffset = $matchOffset + strlen($fullMatch);
        if ($localOffset > $maxOffset) {
            $maxOffset = $localOffset;
        }

        indexPiece($fullMatch);
    }

    $buffer = substr($buffer, $maxOffset);
}

echo "mcount=$mCount, failCount: $failCount\n";

function readStream($stream, &$buffer, $bufferSize, &$offset) {
    do {
        $needBytes = $bufferSize - strlen($buffer);
        if (!$needBytes) {
            break;
        }

        $piece = fread($stream, $needBytes);
        if ($piece === false) {
            throw new \Exception("Read error at offset $osffset, length=$needBytes");
        }

        // EOF
        if ($piece === '') {
            break;
        }

        $buffer .= $piece;
        $offset += strlen($piece);
    } while (true);
}

function indexPiece($fullMatch) {
    global $mCount, $failCount;
    $mCount++;

    // Fix XML
    $fullMatch = preg_replace('/&(?![#a-z0-9]+;)/', '&amp;', $fullMatch);

    try {
        $xml = new SimpleXMLElement($fullMatch);
    } catch (\Exception $e) {
        if (preg_match("/Opening and ending tag mismatch/i", $e->__toString())) {
            echo "Fail: parse $fullMatch\n";
            $failCount++;
            return;
        }

        throw $e;
    }

    $accessModifier = $xml->xpath('//span[contains(@class, "modifier")]');
    if ($accessModifier) {
        return;
    }

    // echo $fullMatch . "\n\n";

    $methodNameXml = $xml->xpath('//span[contains(@class, "methodname")]');
    $paramsXml = $xml->xpath('//span[contains(@class, "methodparam")]');
    $methodName = strip_tags($methodNameXml[0]->asXml());

    $hints = [];
    foreach ($paramsXml as $paramXml) {
        $typeHintXml = $paramXml->xpath('span[contains(@class, "type")]');
        $typeHint = strval($typeHintXml ? $typeHintXml[0]->asXml() : '');

        $hints[] = $typeHint ? strip_tags($typeHint) : '?';
    }

    // echo "MethodName: $methodName\n";
    // echo "Params: " . implode(', ', $hints);
    // echo "\n\n";

    indexMethod($methodName, $hints);
}

function indexMethod($methodName, $hints) {
    global $jsonIndex;

    if (false !== strpos($methodName, '::')) {
        return;
    }

    $ignoreNames = ['handler'];

    if (in_array($methodName, $ignoreNames)) {
        return;
    }

    $badList = ['?', 'int', 'mixed', 'bool', 'resource', 'string', 'float', 'double', 'boolean', 'integer'];
    $good = array_diff($hints, $badList);

    if (!$good) {
        return;
    }

    echo "$methodName: " . implode(", ", $hints) . "\n";
}

