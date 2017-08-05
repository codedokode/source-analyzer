<?php 

namespace Tests\SourceAnalyzer\Common;

use SourceAnalyzer\Common\TypeHintMissingChecker;
use SourceAnalyzer\File;

class TypeHintMissingCheckerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideCodeExamples
     */
    public function testCanDetectMissingTypeHints($code, $needsTypeHints)
    {
        $code = '<?php ' . $code;
        $checker = new TypeHintMissingChecker;
        $phpParser = new \PhpParser\Parser(new \PhpParser\Lexer\Emulative);

        $file = new File($code, "/fictional/name.php");    
        $file->setPhpParser($phpParser);

        $checker->check($file);
        $checker->analyze();

        $errors = $checker->getErrors();

        if ($needsTypeHints) {
            $this->assertNotEmpty($errors);            
        } else {
            $this->assertEmpty($errors);
        }
    }
    
    public function provideCodeExamples()
    {
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

        $examples = [];

        foreach ($positiveTests as $name => $code) {
            $examples[$name] = [$code, true];
        }

        foreach ($negativeTests as $name => $code) {
            $examples[$name] = [$code, false];
        }

        return $examples;
    }
}
