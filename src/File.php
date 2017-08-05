<?php

namespace SourceAnalyzer;

use PhpParser\Parser;

class File
{
    private $content;
    private $path;
    private $parsedPhpTree;
    private $phpHelper;

    private $phpParser;

    public function __construct($content, $path)
    {
        $this->content = $content;
        $this->path = $path;
    }
    
    public function setPhpParser(Parser $phpParser)
    {
        $this->phpParser = $phpParser;
    }   

    public function getContent()
    {
        return $this->content;
    }

    public function getPath()
    {
        return $this->path;
    }
    
    public function looksLikeTemplate()
    {
        if (preg_match("/\.(tpl|phtml|html)$/ui", $this->getBasename())) {
            return true;
        }

        if (preg_match("/templates/ui", $this->path)) {
            return true;
        }

        return $this->doesContainHtml() && !$this->doesContainMuchCode();
    }

    public function doesContainHtml($tagsCount = 3) 
    {
        preg_match_all("#</?(a|div|span|h[1-6]|html|body|br|p|table|tr|td|!DOCTYPE)(>|\s)#ui", 
            $this->content, $matches, PREG_SET_ORDER);

        if (count($matches) >= $tagsCount) {
            return true;
        }
    }

    protected function doesContainMuchCode()
    {
        preg_match_all("/<\?(php|\s)[\s\S]*?(\?>|\Z)/ui", $this->content, $matches, PREG_SET_ORDER);
        $codeSize = array_sum(array_map(
            function ($m) { return strlen($m[0]); }, 
            $matches)
        );
        $fileSize = strlen($this->content);

        return $codeSize > $fileSize * 0.5;
    }
    
    public function isPhpExtension()
    {
        return preg_match("/\.(php|inc)$/ui", $this->getBasename());
    }

    public function isPhpTemplateExtension()
    {
        return preg_match("/\.(phtml|html|tpl)$/ui", $this->getBasename());
    }
    
    public function isSqlExtension()
    {
        return preg_match("/\.(sql)$/ui", $this->getBasename());
    }
    
    public function looksLikeTwig()
    {
        if (preg_match("/\.twig(\.|\Z)/ui", $this->getBasename())) {
            return true;
        }

        if (preg_match("/\{%\s*(if|for|block|endblock|extends)\b/ui", $this->getContent())) {
            return true;
        }

        return false;
    }

    public function getBasename()
    {
        return basename($this->path);
    }

    public function parsePhp()
    {
        assert(!!$this->phpParser);

        if (!$this->parsedPhpTree) {
            $this->parsedPhpTree = $this->phpParser->parse($this->content);
        }

        return $this->parsedPhpTree;
    }

    public function getPhpHelper()
    {
        if (!$this->phpHelper) {
            $this->phpHelper = new Helper\PhpHelper($this->parsePhp());
        }

        return $this->phpHelper;
    }
}