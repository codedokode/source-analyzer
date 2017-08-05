<?php 

require __DIR__ . '/../vendor/autoload.php';

$code = <<<'EOF'
<?php

isset($x);
isset($x[1]);
isset($x->y);

function t1 (TypeHint $a) { echo 1; }

class A {
    public function t2($b) { echo 2; }
}

$t3 = function ($c) use ($d) {echo 3; };
$x->field[$y] = $z[$w] + $r;

list($a, $b, $c[$d]) = [];

array_pop($ty);
foreach ($a as $b) { };

$z->meth();

$w();

EOF;

$parser = new PhpParser\Parser(new PhpParser\Lexer\Emulative);
$nodeDumper = new PhpParser\NodeDumper;

$stmts = $parser->parse($code);

echo $nodeDumper->dump($stmts), "\n";

