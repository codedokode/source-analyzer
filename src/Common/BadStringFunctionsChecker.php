<?php

namespace SourceAnalyzer\Common;

use SourceAnalyzer\Base\Checker;
use SourceAnalyzer\File;

class BadStringFunctionsChecker extends Checker
{
    private $haveMbEncoding = false;
    private $haveMbFuncs = false;

    public function check(File $file)
    {
        if (!$file->isPhpExtension()) {
            return;
        }

        $content = $file->getContent();

        preg_match_all(
            "/\b(strrev|strlen|substr|strpos|ucfirst|lcfirst|wordwrap|str_pad|strtolower|strtoupper)\s*\(/ui", 
            $content, 
            $matches, 
            PREG_SET_ORDER
        );

        $quotes = [];
        foreach ($matches as $match) {
            $quotes[] = $this->quoteCode($match[0]);
        }

        if ($matches) {
            $quoted = implode("\n", $quotes);

            $this->addFileError($file, "$quoted\n
                Ты используешь функции, которые работают неправильно с нелатинскими символами в utf8. Они 
                рассчитаны на однобайтные кодировки, а utf-8 многобайтная. Надо использовать функции вроде 
                mb_strlen, которые корректно поддерживают utf-8. Даже если ты работаешь только с латинскими
                строками, лучше придерживаться единого стиля во всей программе и использовать mb-функции.

                Подробнее про строки и utf-8: https://gist.github.com/codedokode/ff99e357e9860ea169b8
            ");
        }

        if (preg_match("/\b(mb_\w+)\s*\(/ui", $content)) {
            $this->haveMbFuncs = true;
        }

        if (preg_match("/mb_internal_encoding\s*\(/ui", $content)) {
            $this->haveMbEncoding = true;
        }
    }

    public function analyze()
    {
        if ($this->haveMbFuncs && !$this->haveMbEncoding) {
            $this->addError("Ты используешь функции mb_xxx в своем коде, а указал ли 
                ты для них используемую кодировку с помощью mb_internal_encoding(...)?
                Без этого эти функции могут неправильно обрабатывать строки.");
        }
    }
}