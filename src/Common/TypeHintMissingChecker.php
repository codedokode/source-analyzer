<?php

namespace SourceAnalyzer\Common;

use SourceAnalyzer\Base\Checker;
use SourceAnalyzer\File;
use SourceAnalyzer\Helper\PhpTypeHintIndex;
use \SourceAnalyzer\Helper;

class TypeHintMissingChecker extends Checker
{
    private $toldAboutTypeHint = false;
    private $typeHintIndexer;

    public function __construct()
    {
        $this->typeHintIndexer = new PhpTypeHintIndex;
    }

    /** TODO: remove this temporary workaround */
    public function testInjectTypeHintIndex(PhpTypeHintIndex $index)
    {
        $this->typeHintIndexer = $index;
    }

    public function check(File $file)
    {
        if (!$file->isPhpExtension()) {
            return;
        }

        $helper = $file->getPhpHelper();
        $helper->findAny(['Stmt_Function', 'Stmt_ClassMethod'], 
            function ($node, $visitor) use ($file, $helper) {
                $this->checkFunction($node, $visitor, $file, $helper);
        });
    }

    private function checkFunction(\PhpParser\Node $node, Helper\PhpNodeVisitor $visitor, File $file, $helper)
    {
        // Select all function arguments without type hint
        $argNames = $this->collectNonTypeHintedArgs($node);
        if (!$argNames) {
            return;
        }

        $arrayArgs = [];
        $objectArgs = [];
        $callableArgs = [];
        $reassignedArgs = [];

        // Walk function body to see if any of them is used as array/object/callable or 
        // is reassigned
        $helper->iterateNode($node, function ($subNode, $subVisitor) 
            use ($argNames, &$arrayArgs, &$objectArgs, &$callableArgs, &$reassignedArgs) {

            $name = '';
            if ($this->isAssignment($subNode, $subVisitor, $name)) {
                $reassignedArgs[$name] = $name;
            }

            if ($this->isObjectUsage($subNode, $subVisitor, $name)) {
                $objectArgs[$name] = $name;
            }

            if ($this->isArrayUsage($subNode, $subVisitor, $name)) {
                $arrayArgs[$name] = $name;
            }

            if ($this->isCallableUsage($subNode, $subVisitor, $name)) {
                $callableArgs[$name] = $name;
            }
        });

        // Remove reassigned vars
        $argNames = array_diff($argNames, $reassignedArgs);

        // Report arrayOrObjectArgs
        $arrayArgs = array_intersect($argNames, $arrayArgs);
        $this->maybeSuggestAddingTypeHint($arrayArgs, "array", $visitor, $node, $file);

        $objectArgs = array_intersect($argNames, $objectArgs);
        $objectArgs = array_diff($objectArgs, $arrayArgs);
        $this->maybeSuggestAddingTypeHint($objectArgs, "class", $visitor, $node, $file);

        $callableArgs = array_intersect($argNames, $callableArgs);
        $callableArgs = array_diff($callableArgs, $objectArgs, $arrayArgs);
        $this->maybeSuggestAddingTypeHint($callableArgs, "callable", $visitor, $node, $file);
    }

    private function maybeSuggestAddingTypeHint(array $names, $typeHint, $visitor, $node, $file)
    {
        if (!$names) {
            return;
        }

        $names = array_values($names);

        if ($node->getType() == 'Stmt_ClassMethod') {
            $className = $visitor->getParent()->name;
            $location = "методе {$className}#{$node->name}";
        } else {
            $location = "функции {$node->name}";
        }

        if (count($names) > 1) {
            $argDesc = "аргументы " . implode(", ", $names);
        } else {
            $argDesc = "аргумент \$" . $names[0];
        }

        $typeHintName = ($typeHint == 'class') ? 'с именем класса' : $typeHint;

        $aboutTypeHints = '';
        if (!$this->toldAboutTypeHint) {
            $this->toldAboutTypeHint = true;

            $aboutTypeHints = "

Тайп хинты позволяют указать, что аргумент функции должен быть определенного типа (например, быть 
объектом определенного класса или его наследника). Тайп хинт делает код понятнее (так как 
видно какого типа переменная) и надежнее (так как PHP не позволит передать что-то неразрешенное 
и ты сразу увидишь ошибку). Используй их везде.

Мануал: http://php.net/manual/ru/language.oop5.typehinting.php";
        }

        $this->addFileError($file, "В {$location} {$argDesc} можно пометить тайп хинтом {$typeHintName}." .
            $aboutTypeHints);
    }

    private function collectNonTypeHintedArgs(\PhpParser\Node $node)
    {
        $args = [];

        foreach ($node->params as $param) {
            if (!$param->type) {
                $args[] = $param->name;
            }
        }

        return $args;
    }

    private function hasTypeHintForArgument(\PhpParser\Node $node, $visitor, $expectedType, &$name)
    {
        if ($node->getType() != 'Expr_Variable') {
            return false;
        }

        $parent = $visitor->getParent();
        $grandParent = $visitor->getParentWithNumber(2);

        if ($parent->getType() != 'Arg') {
            return false;
        }

        if ($grandParent->getType() != 'Expr_FuncCall') {
            return false;
        }

        $functionNameNode = $grandParent->name;
        if ($functionNameNode->getType() != "Name") {
            return false;
        }

        if ($functionNameNode->isQualified()) {
            return false;
        }

        $functionName = $functionNameNode->getLast();

        $argIndex = array_search($parent, $grandParent->args, true);
        if ($argIndex === false) {
            return false;
        }

        $typeHint = $this->typeHintIndexer->isTypeHinted($functionName, $argIndex);
        if (!$typeHint) {
            return false;
        }

        $name = $node->name;

        if ($expectedType == 'array' || $expectedType == 'callable') {
            return $typeHint == $expectedType;
        } 

        return $typeHint != 'array' && $typeHint != 'callable';
    }

    private function isAssignment(\PhpParser\Node $node, $visitor, &$name)
    {
        if ($node->getType() != 'Expr_Variable') {
            return false;
        }

        if (!$this->isSimpleVariable($node)) {
            return false;
        }

        $parent = $visitor->getParent();
        $grandParent = $visitor->getParentWithNumber(2);

        if ($parent->getType() == 'Expr_Assign' && $parent->var === $node) {
            $name = $node->name;
            return true;
        }

        if ($parent->getType() == 'Expr_List' && $grandParent->getType() == 'Expr_Assign' &&
            $grandParent->var === $parent) {

            $name = $node->name;
            return true;
        }

        return false;

        // $isVar = 
        //     $visitor->isChildOf('Expr_Assign') || 
        //     $visitor->isChildOf('Expr_Assign/Expr_List');

        // if (!$isVar) {
        //     return false;
        // }

        // $name = $node->name;
        // return true;
    }

    private function isSimpleVariable(\PhpParser\Node $node)
    {
        if ($node->name instanceof \PhpParser\Node) {
            return false;
        }

        return true;
    }

    private function isArrayUsage(\PhpParser\Node $node, $visitor, &$name)
    {
        if ($this->hasTypeHintForArgument($node, $visitor, 'array', $name)) {
            return true;
        }

        $parent = $visitor->getParent();

        // foreach
        if ($node->getType() == 'Expr_Variable' && $parent->getType() == 'Stmt_Foreach' && 
            $parent->expr === $node) {

            $name = $node->name;
            return true;
        }

        // $a[...]
        if ($node->getType() == 'Expr_Variable' && $parent->getType() == 'Expr_ArrayDimFetch' &&
            $parent->var === $node) {

            $name = $node->name;
            return true;
        }

        return false;
    }
    
    private function isObjectUsage(\PhpParser\Node $node, $visitor, &$name)
    {
        if ($this->hasTypeHintForArgument($node, $visitor, 'class', $name)) {
            return true;
        }

        $parent = $visitor->getParent();

        // $a->b
        if ($node->getType() == 'Expr_Variable' && $parent->getType() == 'Expr_PropertyFetch' &&
            $parent->var === $node) {

            $name = $node->name;
            return true;
        }

        // $a->method();
        if ($node->getType() == 'Expr_Variable' && $parent->getType() == 'Expr_MethodCall' &&
            $parent->var === $node) {

            $name = $node->name;
            return true;
        }

        return false;
    }

    private function isCallableUsage(\PhpParser\Node $node, $visitor, &$name)
    {
        if ($this->hasTypeHintForArgument($node, $visitor, 'callable', $name)) {
            return true;
        }

        $parent = $visitor->getParent();

        // $a();
        if ($node->getType() == 'Expr_Variable' && $parent->getType() == 'Expr_FuncCall' &&
            $parent->name === $node) {

            $name = $node->name;
            return true;
        }

        return false;
    }
}