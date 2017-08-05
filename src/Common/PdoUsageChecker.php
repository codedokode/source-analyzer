<?php

namespace SourceAnalyzer\Common;

use SourceAnalyzer\Base\Checker;
use SourceAnalyzer\File;

class PdoUsageChecker extends Checker
{
    private $isPdoUsed = false;
    private $haveSetCharset = false;
    private $haveExceptionMode = false;

    public function check(File $file)
    {
        if (!$file->isPhpExtension()) {
            return;
        }

        $content = $file->getContent();

        if (preg_match("/new\s+PDO\b/ui",$content, $m)) {
            $this->isPdoUsed = true;
        }

        if (preg_match("/(new\s+PDO\s*\([^)]+|mysql:[a-z0-9_\-;\s]+);\s*charset\s*=/ui", $content, $m)) {
            $this->haveSetCharset = true;
        }

        if (preg_match("/SET\s+(NAMES|CHARACTER\s+SET)\s+/ui", $content, $m)) {
            $this->haveSetCharset = true;
        }

        if (preg_match("/PDO::ERRMODE_EXCEPTION/u", $content)) {
            $this->haveExceptionMode = true;
        }
    }
    
    public function analyze()
    {
        if (!$this->isPdoUsed) {
            return;
        }

        if (!$this->haveSetCharset) {
            $this->addError("Задал ли ты кодировку соединения с базой при работе через PDO? Это
                можно сделать либо параметром charset= при соединении 
                либо командой SET NAMES в PDO::MYSQL_ATTR_INIT_COMMAND. 
                Мануал: http://php.net/manual/ru/ref.pdo-mysql.connection.php");
        }

        if (!$this->haveExceptionMode) {
            $this->addError("Задал ли ты для PDO режим выбрасывания исключений при
                ошибках через PDO::ERRMODE_EXCEPTION? Без него ошибки либо
                тихо игнорируются, либо ты должен вручную после каждого действия с базой
                проверять, не произошла ли ошибка, через if. 

                - Про режимы обработки ошибок PDO: http://php.net/manual/ru/pdo.error-handling.php
                - Про исключения: https://gist.github.com/codedokode/65d43ca5ac95c762bc1a
            ");
        }
    }
}