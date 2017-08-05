<?php

namespace SourceAnalyzer\Helper;

class PhpNodeVisitor extends \PhpParser\NodeVisitorAbstract
{
    private $enterNodeCallback;
    private $leaveNodeCallback;

    private $ancestors = [];

    public function __construct(callable $enterNodeCallback, callable $leaveNodeCallback = null)
    {
        $this->enterNodeCallback = $enterNodeCallback;
        $this->leaveNodeCallback = $leaveNodeCallback;
    }

    public function beforeTraverse(array $nodes)
    {
        $this->ancestors = [];
        return parent::beforeTraverse($nodes);
    }
    
    public function enterNode(\PhpParser\Node $node)
    {
        $result = call_user_func($this->enterNodeCallback, $node, $this);
        $this->ancestors[] = $node;
        return $result;
    }
    
    public function leaveNode(\PhpParser\Node $node)
    {
        $topNode = array_pop($this->ancestors);
        assert($topNode === $node);

        if ($this->leaveNodeCallback) {
            return call_user_func($this->leaveNodeCallback, $node, $this);
        }
    }
    
    public function afterTraverse(array $nodes)
    {
        parent::afterTraverse($nodes);
        if ($this->ancestors) {
            throw new \Exception("Ancestor list is not empty after traversing");
        }
    }

    public function getAncestors()
    {
        return $this->ancestors;
    }
    
    public function getParent()
    {
        return $this->ancestors ? end($this->ancestors) : null;
    }

    public function getParentWithNumber($number)
    {
        $index = count($this->ancestors) - $number;
        return $index < 0 ? null : $this->ancestors[$index];
    }

    private function getParentPath()
    {
        $path = [];
        foreach ($this->ancestors as $node) {
            $path[] = $node->getType();
        }

        return $path;
    }

    public function isChildOf($path)
    {
        return PhpHelper::matchAncestorsPath($this->ancestors, $path);
    }        
}