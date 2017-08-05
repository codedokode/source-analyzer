<?php

require_once __DIR__ . '/../lib/exceptional.php';
require_once __DIR__ . '/../vendor/autoload.php';

$positiveTests = [
    'works-in-function'         =>  'function test($a) { return $a[0]; }',
    'works-in-method'           =>  'class A { public function test($a) { return $a[0]; } }',
    'detects-in-foreach'        =>  'function test($a) { foreach ($a as $b) {}; }',
    'detects-array-usage'       =>  'function test($a) { return $a[1]; } ',
    'detects-array-function'    =>  'function test($a) { array_push($a, 1); }',
    'detects-field-access'      =>  'function test($a) { return $a->field; }',
    'detects-method-call'       =>  'function test($a) { return $a->method($b); }',
    'detects-object-function'   =>  'function test($a) { date_modify($a, "test"); }',
    'detects-callable'          =>  'function test($a) { $a($b); }',
    'detects-callable-passed'   =>  'function test($a) { return array_map([], $a); }',
    'detects-partially-hinted'  =>  'function test(array $a, $b, array $c) { return $a[0] + $b[0] + $c[0]; }',
    'ignores-right-side-assign' =>  'function test($a) { $b = $a; return $a[0]; }'
];

$negativeTests = [
    'ok-if-reassignment'        =>  'function test($a) { $a = [1, 2]; return $a[0]; }',
    'ok-if-already-hinted'      =>  'function test(array $a) { return $a[0]; }',
    'ok-if-hinted-class'        =>  'function test(MyClass $a) { return $a->field; } ',
    'ok-if-hinted-callable'     =>  'function test(callable $a) { return $a(); }',
    'ok-if-not-in-function'     =>  '$a[1] = 2;',
    'ok-if-not-hintable'        =>  'function test($a) { return $a + 1; }',
    'ok-if-reassigned-with-list'=>  'function test($a) { list($a, $b) = [[0], [0]]; return $a[0]; }'
];

foreach ($positiveTests as $name => $code) {
    runTestCase($code, $name, true);
}

foreach ($negativeTests as $name => $code) {
    runTestCase($code, $name, false);
}

function runTestCase($code, $name, $expectedError) {
    $code = '<?php ' . $code;
    $checker = new \Common\TypeHintMissingChecker;
    $phpParser = new PhpParser\Parser(new PhpParser\Lexer\Emulative);

    $file = new \File($code, "/fictional/$name.php");    
    $file->setPhpParser($phpParser);

    $checker->check($file);
    $checker->analyze();

    $haveErrors = !!$checker->getErrors();

    if ($haveErrors !== $expectedError) {
        $expectedStr = intval($expectedError);
        $actualStr = intval($haveErrors);
        throw new \Exception("Test: $name, Expected result: $expectedStr, actual: $actualStr");
    }

    echo "[OK] $name\n";
}