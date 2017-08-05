<?php

namespace SourceAnalyzer\Common;

use SourceAnalyzer\Base\Checker;
use SourceAnalyzer\File;

class SqlStrictModeChecker extends Checker
{
    private $havePdo = false;
    private $haveMysqli = false;
    private $haveInitCommand = false;
    private $sawSetSqlMode = false;

    public function check(File $file)
    {
        if (!$file->isPhpExtension()) {
            return;
        }

        $content = $file->getContent();

        if (preg_match("/new\s+PDO\s*\(/ui", $content)) {
            $this->havePdo = true;
        }

        if (preg_match("/\bmysqli_connect\s*\(|new\s+mysqli\s*\(/ui", $content)) {
            $this->haveMysqli = true;
        }

        if (preg_match(
            "/SET\s+sql_mode[\s\S]{1,100}(STRICT_TRANS_TABLES|TRADITIONAL|STRICT_ALL_TABLES)/ui", 
            $content)) {

            $this->sawSetSqlMode = true;
        }

        if (preg_match("/\bPDO::MYSQL_ATTR_INIT_COMMAND\b/u", $content)) {
            $this->haveInitCommand = true;
        }
    }

    public function analyze()
    {
        if (!($this->havePdo || $this->haveMysqli)) {
            return;
        }

        $pdoNote = '';
        if ($this->havePdo) {
            $pdoNote = 'В PDO это удобно сделать опцией PDO::MYSQL_ATTR_INIT_COMMAND 
            при создании объекта PDO.';
        }

        if (!$this->sawSetSqlMode) {
            $this->addError("Кажется, ты не используешь строгий режим в MySQL. Зря.  MySQL более 
                тщательно проверяет твои запросы и вместо предупреждений (которые ты не увидишь) 
                выдает ошибки при попытке вставить неправильные данные. Ну к 
                примеру, если у тебя есть колонка типа varchar(200) и ты попытаешься вставить в нее 
                строку из 300 символов, в нестрогом режиме MySQL молча отрежет лишнее 
                (и в базе окажется обрезанная строка), а в строгом выдаст ошибку.

                Использование строгого режима экономит твое время на исправление неправильно
                вставленных данных. Статья на хабре: http://habrahabr.ru/post/116922/

                Включить строгий режим можно сделав при соединении с 
                БД запрос SET sql_mode='STRICT_ALL_TABLES'. $pdoNote

            ");
        }

        if ($this->sawSetSqlMode && $this->havePdo && $this->haveInitCommand) {
            $this->addError("Похоже, что ты включил строгий режим для PDO, но не через опцию
                PDO::MYSQL_ATTR_INIT_COMMAND. Лучше использовать ее, так как в этом случае
                например при автоматическом переподсоединении запрос выполнится снова.");
        }
    }
    
}