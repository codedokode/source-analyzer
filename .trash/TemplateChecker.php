<?php

namespace Base;

use SourceAnalyzer\Base\Checker;

abstract class TemplateChecker extends Checker
{
    protected function looksLikeTemplate($contents, $path)
    {
        if (preg_match("/\.(tpl|phtml|html)$/ui", $path)) {
            return true;
        }

        if (preg_match("/templates/ui", $path)) {
            return true;
        }

        return $this->doesContainHtml($contents) && !$this->doesContainMuchCode($contents);
    }

    protected function doesContainHtml($contents, $tagsCount = 3) 
    {
        preg_match_all("#</?(a|div|span|h[1-6]|html|body|br|p|table|tr|td|!DOCTYPE)(>|\s)#ui", 
            $contents, $matches, PREG_SET_ORDER);

        if (count($matches) >= $tagsCount) {
            return true;
        }
    }

    protected function doesContainMuchCode($contents)
    {
        preg_match_all("/<\?(php|\s)[\s\S]*?(\?>|\Z)/ui", $contents, $matches, PREG_SET_ORDER);
        $codeSize = array_sum(array_map(
            function ($m) { return strlen($m[0]); }, 
            $matches)
        );
        $fileSize = strlen($contents);

        return $codeSize > $fileSize * 0.5;
    }
    
}