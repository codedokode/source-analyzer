<?php

namespace SourceAnalyzer\Common;

use SourceAnalyzer\Base\Checker;
use SourceAnalyzer\File;

class SqlDumpChecker extends Checker
{
    public function check(File $file)
    {
        $isSqlExtension = $file->isSqlExtension();
        $content = $file->getContent();
        $isSqlContent = preg_match('/\bCREATE\s+TABLE\b/ui', $content);

        // Проверим что SQL файл приложен
        if (!($isSqlExtension || $isSqlContent)) {
            return;
        }

        if (!$isSqlExtension){
            $this->addFileError($file, "Файл с SQL дампом принято называть с 
                расширением .sql, например students.sql");
        }

        $this->sqlDumps[] = $file;

        // Проверим, нет ли неправильной кодировки у таблиц
        if (preg_match("/\bDEFAULT\s+CHARSET\s*=\s*([a-zA-Z0-9_\-]+)/i", $content, $m)) {
            $charset = mb_strtolower($m[1]);
            if ($charset == 'utf-8') {
                $this->addFileError($file, "указана кодировка $charset, хотя в MySQL 
                    ее надо писать как utf8, без дефиса.");
            } elseif ($charset != 'utf8') {
                $this->addFileError($file, "указана кодировка $charset, но лучше 
                    использовать кодировку utf8. А то русские буквы поддерживаются далеко
                    не во всех кодировках.");
            }
        }

        // Проверим не используется ли InnoDB
        if (preg_match("/\bENGINE\s*=\s*(\S+)/i", $content, $m)) {
            $engine = mb_strtolower($m[1]);

            if ($engine == 'myisam') {
                $this->addFileError($file, "
                    выбран движок MyISAM, но лучше использовать InnoDB так как он 
                    поддерживает внешние ключи и транзакции и вообще новее. Подробнее про разницу между 
                    InnoDB и MyISAM можно прочесть тут: 

                    - http://itif.ru/otlichiya-myisam-innodb/
                    - http://memo.undr.su/2009/09/04/chem-otlichaetsya-myisam-ot-innodb/comment-page-1/
                    - http://habrahabr.ru/post/64851/
                ");
            }
        }

        // Ищем неправильные примеры ENUM
        $matches = null;
        preg_match_all("/ENUM\s*(\(\s*\d+\s*\)|NULL|NOT\s+NULL|DEFAULT|,)/i", $content, $matches, PREG_SET_ORDER);
        if ($matches) {
            $firstMatch = $matches[0];

            $this->addFileError($file, "Правильно ли ты используешь ENUM вот тут? 

                {$this->quoteCode($firstMatch[0])}

            ENUM используется примерно так: `column` ENUM ('a', 'b') NOT NULL

            Прочти про этот тип здесь: http://phpclub.ru/mysql/doc/column-types.html
");
        }

        // Используется ли ENUM правильно
        preg_match_all("/ENUM\s*\([^)]+\)/i", $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {

            // Проверим, нет ли русских букв внутри ENUM
            if (preg_match("/[а-яё]+/ui", $match[0], $badMatch)) {
                $this->addFileError($file, "Для значений ENUM не стоит использовать кириллицу (у тебя 
                    там есть символы '{$badMatch[0]}'). Принято использовать латинские буквы.");

            } elseif (preg_match("/[^\s()'\",a-z0-9_\-]+/ui", $match[0], $badMatch)) {
                $this->addFileError($file, "Для значений ENUM принято исплоьзовать латинские 
                    буквы и цифры, а у тебя встречаются символы '{$badMatch[0]}'.");

            }
        }
       
        // Нет ли CREATE DATABASE и подобного
        if (preg_match("/\b(CREATE\s+DATABASE\b|CREATE\s+USER\b|USE\s+[`a-zA-Z0-9_]+\s*;)/iu", $content, $m)) {
            $this->addFileError($file, "Не надо в SQL дампе указывать команду '{$m[0]}'. У разных
                пользователей могут быть разные имена баз данных, пользователей и потому не 
                стоит их писать в дампе.");
        }
    }
}