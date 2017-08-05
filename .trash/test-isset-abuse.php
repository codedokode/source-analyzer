<?php

require_once __DIR__ . '/../lib/exceptional.php';
require_once __DIR__ . '/../vendor/autoload.php';

$examples = [
    // code, isOk
    ['<?php  echo "hello"; ',   true],
    ['<?php isset($x);',        false],
    ['<?php isset($x[0]);',      true],
    ['<?php isset($x[$y]);',     true],
    ['<?php isset($x->y);',     false]
];

foreach ($examples as $example) {
    list($code, $isOk) = $example;
    testIssetAbuse($code, $isOk);
}

function testIssetAbuse($code, $isOk) {

    $parser = new PhpParser\Parser(new PhpParser\Lexer);

    $file = new File($code, 'sample.php');
    $file->setPhpParser($parser);
    echo "Test $code...\n";

    $checker = new \Common\IssetAbuseChecker();
    $checker->check($file);
    $checker->analyze();

    $errors = $checker->getErrors();

    if ($isOk) {
        assert(empty($errors));
    } else {
        assert(!empty($errors));
    }
}

