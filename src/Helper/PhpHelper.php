<?php

namespace SourceAnalyzer\Helper;

class PhpHelper
{
    private $parsedCode;

    public function __construct(array $parsedCode)
    {
        $this->parsedCode = $parsedCode;
    }

    public function findAny(array $nodeNames, callable $callback)
    {
        $this->findAnyIn($this->parsedCode, $nodeNames, $callback);
    }

    public function findAnyIn(array $nodes, array $nodeNames, callable $callback)
    {
        foreach ($nodeNames as $name) {
            self::checkNodeNameExist($name);
        }

        $nodeNamesHash = array_fill_keys($nodeNames, true);
        $this->iterate($nodes, 
            function ($node, $visitor) 
            use ($nodeNamesHash, $callback) {
                if (isset($nodeNamesHash[$node->getType()])) {
                    return $callback($node, $visitor);
                }
        });
    }

    public function iterate(array $nodes, callable $callback)
    {
        $visitor = new PhpNodeVisitor($callback);
        $traverser = new \PhpParser\NodeTraverser(false /* do not clone */);
        $traverser->addVisitor($visitor);
        $resultNodes = $traverser->traverse($nodes);
    }

    public function iterateNode(\PhpParser\Node $node, callable $callback)
    {
        $this->iterate([$node], $callback);
    }

    private static function checkNodeNameExist($name)
    {
        $className = "PhpParser\\Node\\" . str_replace('_', '\\', $name);
        $classNameDashed = $className . '_';
        assert(class_exists($className) || class_exists($classNameDashed));
    }
    
    /**
     * Checks whether current node is child of specified nodes. $parentPathEnd might look
     * like 'Expr_List/Expr_Variable'
     */
    public static function matchAncestorsPath(array $ancestors, $parentPathEnd)
    {
        $parts = explode('/', trim($parentPathEnd, '/'));

        if (count($ancestors) < count($parts)) {
            return false;
        }

        for ($i = count($parts) - 1, $j = count($ancestors) - 1;  $i >= 0; $i--, $j--) { 

            self::checkNodeNameExist($parts[$i]);

            if ($ancestors[$j]->getType() != $parts[$i]) {
                return false;
            }            
        }

        return true;
    }
    
}