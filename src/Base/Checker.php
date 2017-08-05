<?php

namespace SourceAnalyzer\Base;

use SourceAnalyzer\File;

abstract class Checker
{
    private $errors = [];
    
    abstract function check(File $file);
    public function analyze() {}

    protected function addError($text)
    {
        $this->errors[] = $this->cleanupText($text);
    }

    protected function addFileError(File $file, $error)
    {
        $path = $file->getPath();
        $text = "В файле {$this->cleanupFileName($path)}:\n\n" . $error;
        $this->addError($text);        
    }

    protected function addLineError(File $file, $line, $error)
    {
        $text  =  "В файле {$this->cleanupFileName($file->getPath())}, строка $line:\n\n" . $error;
        $this->addError($text);
    }

    private function cleanupText($text)
    {
        // single newlines
        $text = trim($text);
        $text = preg_replace("/\r/", ' ', $text);

        $lines = explode("\n", $text);
        foreach ($lines as &$line) {
            $line = ltrim($line);

            if (substr($line, 0, 1) != '>') {
                $line = preg_replace("/[ \t\r]+/", ' ', $line);
            }
        }
        
        unset($line);
        $text = implode("\n", $lines);

        // $text = preg_replace("/[ \t\r]+/", ' ', $text);
        $text = preg_replace_callback("/(\n\s*)(-|>)?/", function ($m) {
            $newLines = substr_count($m[1], "\n");
            $nextChar = isset($m[2]) ? $m[2] : '';

            $haveMinus = $nextChar == '-';
            $haveQuote = $nextChar == '>';
            $haveTilda = $nextChar == '~';

            if ($newLines > 1) {
                return "\n\n" . $nextChar;
            }

            if ($haveMinus || $haveQuote || $haveTilda) {
                return "\n" . $nextChar;
            }

            return ' ' . $nextChar;

        }, $text);

        return $text;
    }

    private function cleanupFileName($file)
    {
        $file = str_replace("\\", "/", $file);
        return $file;
    }
    
    
    protected function quoteCode($code)
    {
        $code = trim($code);
        $quoted = "> " . str_replace("\n", "\n> ", $code);

        return $quoted;
    }
    

    public function getErrors()
    {
        return $this->errors;
    }
}