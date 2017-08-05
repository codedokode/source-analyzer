<?php

namespace SourceAnalyzer\StudentList;

use SourceAnalyzer\Base\Checker;
use SourceAnalyzer\File;

class SqlChecker extends Checker
{
    private $sqlDumps = [];
    private $enums = [];
    private $notNulls = [];
    private $haveAutoInc = false;
    private $haveYear = false;

    public function check(File $file)
    {
        $isSqlExtension = $file->isSqlExtension();
        $content = $file->getContent();
        $isSqlContent = preg_match('/\bCREATE\s+TABLE\b/ui', $content);

        // Проверим что SQL файл приложен
        if (!($isSqlExtension || $isSqlContent)) {
            return;
        }

        $this->sqlDumps[] = $file->getPath();

        preg_match_all("/ENUM\s*\([^)]+\)/i", $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $this->enums[] = $match[0];
        }

        // Используется ли NOT NULL
        preg_match_all("/\bNOT\s+NULL\b/i", $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $this->notNulls[] = $match[0];
        }

        if (preg_match("/\bAUTO_INCREMENT\b/ui", $content, $m)) {
            $this->haveAutoInc = true;
        }

        if (preg_match("/\bYEAR(\s+|\s*\(\d+\)\s*)(NULL|NOT\s+NULL|DEAFULT)/ui", $content)) {
            $this->haveYear = true;
        }
    }

    public function analyze()
    {
        if (!$this->sqlDumps) {
            $this->addError("В проекте не хватает SQL дампа.");

            // дальше нет смысла проверять
            return;
        }

        if (!$this->enums) {
            $this->addError("Для полей пола и места жительства (и для любых других
                полей где есть выбор одного из нескольких вариантов) стоит использовать
                тип ENUM. Он идеально подходит для них так как сразу видно какие варианты
                значений существуют и БД не позволит вставить неправильные значения. Если 
                ты с ним не знаком, почитай про существующие в MySQL типы колонок:

                http://phpclub.ru/mysql/doc/column-types.html
            ");
        } elseif (count($this->enums) == 1) {
            $this->addError("В этой задаче минимум 2 поля, для которых стоит использовать тип ENUM —
                это пол и место жительства. У тебя ENUM использован всего 1 раз, что странно.");
        }

        if (count($this->notNulls) < 5) {
            $this->addError("У тебя редко встречается NOT NULL у столбцов таблиц. В SQL если у колонки 
                указано NOT NULL и нет значения по умолчанию, это обычно значит что поле обязательно
                для заполнения. В этой задаче все поля обязательны для заполнения и надо отметить
                их как NOT NULL.");
        }

        if (!$this->haveAutoInc) {
            $this->addError("В MySQL есть специальное обозначение AUTO_INCREMENT 
                для того, чтобы id автоматически генерировались при вставке. Почему 
                ты его не хочешь использовать?");
        }

        if (!$this->haveYear) {
            $this->addError("Похоже что ты не использовал тип YEAR для хранения года рождения, а
                ведь такой тип специально для этого придуман. Если ты про него не знал, то
                почитай про то какие типы есть в MySQL: 

                http://phpclub.ru/mysql/doc/column-types.html               
            ");
        }
    }
}