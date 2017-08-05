<?php 

namespace Tests\SourceAnalyzer\Common;

use SourceAnalyzer\Common\IssetAbuseChecker;
use SourceAnalyzer\File;

class IssetAbuseCheckerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideCodeExamples
     */
    public function testCheckerWorks($code, $expectedResult)
    {
        $parser = new \PhpParser\Parser(new \PhpParser\Lexer);
        $file = new File($code, 'sample.php');
        $file->setPhpParser($parser);

        $checker = new IssetAbuseChecker();
        $checker->check($file);
        $checker->analyze();

        $errors = $checker->getErrors();

        if ($expectedResult) {
            $this->assertCount(0, $errors);
        } else {
            $this->assertNotEmpty($errors);
        }
    }
    
    public function provideCodeExamples()
    {
        return [
            ['<?php  echo "hello"; ',   true],
            ['<?php isset($x);',        false],
            ['<?php isset($x[0]);',     true],
            ['<?php isset($x[$y]);',    true],
            ['<?php isset($x->y);',     false]
        ];
    }
}
