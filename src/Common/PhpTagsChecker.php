<?php 

namespace SourceAnalyzer\Common;

use SourceAnalyzer\Base\Checker;
use SourceAnalyzer\File;

class PhpTagsChecker extends Checker
{
    public function check(File $file)
    {
        // Не проверять SVG
        if (!($file->isPhpExtension() || $file->isPhpTemplateExtension())) {
            return;
        }

        $contents = $file->getContent();

        // tags usage
        if (preg_match("/<\?(?!php)(?!=)/ui", $contents)) {
            $this->addFileError($file, "Не используй короткий открывающий тег <? так как 
                он может быть отключен в настройках. Используй либо длинный открывающий
                тег <?php для кода, либо короткий <?= для вывода значений в шаблонах.");
        }

        $hasTemplateTags = preg_match("/<\?=/", $contents);
        $isTemplate = $file->looksLikeTemplate() || $hasTemplateTags;

        $isPhp = $file->isPhpExtension();

        if ($isTemplate || !$isPhp) {
            return;
        }

        if ($hasTemplateTags) {
            $this->addFileError($file, "А зачем ты в файле с кодом испльзовал тег для вывода <?= ?");
        }

        // Do not put ? > at end
        if (!$isTemplate && preg_match("/\?>\s*\Z/u", $contents)) {
            $this->addFileError($file, "Не ставь тег ?> в конце файлов с кодом. За ним 
                легко оставить пробелы или пустые строки, при разборе файла PHP выведет эти
                пробелы. А так как после вывода хоть одного символа нельзя отправлять заголовки 
                (а также ставить куки, начинать сессию) ты можешь получить ошибки которые трудно 
                будет найти. ");
        }

        preg_match_all("/<\?php\b/u", $contents, $matches, PREG_SET_ORDER);
        if (count($matches) > 1) {
            $this->addFileError($file, "Почему-то <?php в этом файле с кодом использован 
                более одного раза.");
        }

        // HTML код/echo в файлах
        preg_match_all("/\b(echo|print|printf)\b/ui", $contents, $echoMatches);
        if (count($echoMatches) > 2 || $file->doesContainHtml(3)) {
            $this->addFileError($file, "Похоже, что ты выводишь данные прямо из файла с кодом? Это
                плохая идея, так как смесь логики и HTML выглядит как каша. Весь вывод и HTML код 
                надо вынести в отдельные файлы-шаблоны, например, в папке templates, как описано тут: 
                http://www.phpinfo.su/articles/practice/shablony_v_php.html ");
        }
    }
}