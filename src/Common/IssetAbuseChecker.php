<?php

namespace SourceAnalyzer\Common;

use SourceAnalyzer\Base\Checker;
use SourceAnalyzer\File;
use SourceAnalyzer\Helper;

/**
 * Ищет выражения
 *
 * isset($x)
 * isset($x->y)
 *
 * указывающие на то, что переменная/свойство может отсутствовать
 */
class IssetAbuseChecker extends Checker
{
    public function check(File $file)
    {
        if (!$file->isPhpExtension()) {
            return;
        }

        $helper = $file->getPhpHelper();
        $helper->findAny(['Expr_Isset'], 
            function ($node, $visitor) use ($file) {
                $this->checkIssetAbuse($node, $visitor, $file);
        });
    }

    private function checkIssetAbuse(\PhpParser\Node $node, Helper\PhpNodeVisitor $visitor, File $file)
    {
        foreach ($node->vars as $var) {
            if ($var->getType() == 'Expr_Variable') {
                $this->reportIssetAbuse($file, $node->getLine(), 'переменная');
                return;
            } elseif ($var->getType() == 'Expr_PropertyFetch') {
                $this->reportIssetAbuse($file, $node->getLine(), 'поле объекта');
                return;
            }
        }
    }

    private function reportIssetAbuse($file, $line, $subject)
    {
        $message = "Неудобно, когда $subject может существовать, а может и нет. Трудно писать
        надежный код в таких условиях. Это стоит переделать так, чтобы необходимости в isset() 
        не было (заметь что использовать isset для проверки наличия элемента в массиве
        вполне нормально).";

        $this->addLineError($file, $line, $message);
    }
}
