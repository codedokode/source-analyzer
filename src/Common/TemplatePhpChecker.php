<?php

namespace SourceAnalyzer\Common;

use SourceAnalyzer\Base\Checker;
use SourceAnalyzer\File;

class TemplatePhpChecker extends Checker
{
    private $sawSpecial = false;
    private $sawTwig = false;

    public function check(File $file)
    {
        $haveSpecial = false;
        $contents = $file->getContent();        

        if (preg_match("/\bhtmlspecialchars\s*\(/ui", $contents)) {
            $haveSpecial = true;
            $this->sawSpecial = true;
        }

        if ($file->looksLikeTwig()) {
            $this->sawTwig = true;
        }

        if ($haveSpecial && !preg_match("/\bENT_QUOTES\b/u", $contents)) {
            $this->addFileError($file, "Похоже, ты забыл указать флаг ENT_QUOTES при 
                использовании htmlspecialchars(), это плохо так как в этом случае эта
                функция не будет экранировать одиночные кавычки. Мануал: 
                http://php.net/manual/ru/function.htmlspecialchars.php
            ");
        }

        if (!$file->looksLikeTemplate()) {
            return;
        }

        // Не используй <?php echo
        if (preg_match("/<\?php\s+echo/ui", $contents)) {
            $this->addFileError($file, "Используй <?= вместо <?php echo в шаблоне, так
                как он короче и лучше читается.");            
        }

        // Альтернативный синтаксис if
        if (preg_match("/<\?php\s*\}/ui", $contents)) {
            $this->addFileError($file, "В шаблонах надо использовать специальные версии команд 
                if/for/foreach с двоеточием, так как они гораздо лучше заметны в гуще HTML кода.

                Мануал: https://php.net/manual/ru/control-structures.alternative-syntax.php");
        }

        // суперглобальные массивы
        if (preg_match("/\$_(POST|GET|SESSION|COOKIE|SERVER)\b/u", $contents, $m)) {
            $this->addFileError($file, "Не используй суперглобальные переменные (в данном
                случае {$m[0]}) в шаблонах. Шаблон должен использовать только те данные, которые
                ему явно передал контроллер.");
        }
    }   

    public function analyze()
    {
        if (!$this->sawSpecial && !$this->sawTwig) {
            $this->addError("Похоже, что ты не используешь htmlspecialchars. Это плохая идея, 
                так как в этом случае легко получить уязвимость XSS (злоумышленник может
                вывести произвольный HTML код на страницу). Прочти мой урок на эту тему:
                https://github.com/codedokode/pasta/blob/master/security/xss.md
            ");
        }
    }
    
}