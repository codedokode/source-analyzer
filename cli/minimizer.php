<?php

use Masterminds\HTML5;
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/exceptional.php';

$name = 'minimizer';
function usage() {
    fwrite(STDERR,  "Usage: script number menu-selector menu-selector  menu-selector ...\n".
                    "       modifies HTML code from STDIN by increasing/decreasing text length and menu items count\n");
}


if (count($argv) < 2) {
    fwrite(STDERR, "$name: Invalid invocation\n");
    usage();    
    die();
}

$multiplier = $argv[1];
$menuSelectors = array_slice($argv, 2);

if (!is_numeric($multiplier)) {
    fwrite(STDERR, "$name: multiplier must be a number ($multiplier given)\n");
    usage();
    die();
}

$multiplier = floatval($multiplier);

$input = file_get_contents('php://stdin');
$htmlParser = new HTML5();
$dom = $htmlParser->loadHtml($input);

// Walk all text nodes
$dom->normalize();
$xpath = new DOMXPath($dom);
$textNodes = $xpath->query("//text()");

foreach ($textNodes as $textNode) {
    // Do not change whitespace nodes
    if (trim($textNode->nodeValue) === '') {
        continue;
    }

    $textNode->data = replaceText($textNode->data, $multiplier);
}

// Find all menus
foreach ($menuSelectors as $menuSelector) {

    $items = qp($dom, $menuSelector);
    $oldCount = $items->count();
    if ($oldCount < 2) {
        fwrite(STDERR, "$name: selector '$menuSelector' yields less than 2 nodes, probably mistake, will skip\n");
        continue;
    }

    $newCount = round($oldCount * $multiplier);

    if ($newCount > $oldCount) {

        $middleItem = $items->eq(floor($oldCount / 2));

        for ($i = 0; $i < $newCount - $oldCount; $i++) {
            $middleItem->insertAfter($middleItem);
        }

    } else {

        $count = $items->count();
        $delete = $oldCount - $newCount;
        $from = max(0, floor(($count - $delete) / 2));
        $till = min($count, $from + $delete);

        for ($i = $from; $i < $till; $i++) {
            $middleItem = $items->eq($i);
            $middleItem->remove();
        }
    }
}

echo $htmlParser->saveHTML($dom);

function replaceText($text, $multiplier) {
    static $faker;

    if (!$faker) { 
        $faker = Faker\Factory::create('ru_RU');
    }

    $text = preg_replace("/(\s)\s+/", '$1', $text);
    $oldLength = mb_strlen($text);
    $newLength = round($oldLength * $multiplier);

    if ($newLength > $oldLength) {

        $diff = $newLength - $oldLength;
        if ($diff > 15) {
            $text .= ' ' . $faker->realText($newLength - $oldLength - 1);
        } else {
            $text .= ' ' . mb_substr($faker->realText(15), 0, $diff) . '…';
        }

        return $text;
    }

    if ($newLength == $oldLength) {
        return $text;
    }

    $text = mb_substr($text, 0, $newLength) . '…';
    return $text;
}
