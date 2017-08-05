<?php

namespace SourceAnalyzer\Common;

use SourceAnalyzer\Base\Checker;
use SourceAnalyzer\File;

class ClassNameChecker extends Checker
{
    public function check(File $file)
    {
        if (!$file->isPhpExtension()) {
            return;
        }

        $content = $file->getContent();

        preg_match_all("/\bclass\s+([a-z_]+)\b/ui", $content, $matches, PREG_SET_ORDER);

        if (count($matches) > 1) {
            $classes = array_map(function ($m) { return $m[1]; }, $matches);
            $this->addFileError($file, "В одном файле должен быть объявлен ровно один класс, а тут объявлены такие классы: " . implode(', ', $classes));
        }

        if (!$matches) {
            return;
        }

        $className = $matches[0][1];

        if (!preg_match("/^([A-Z][a-z]*)+$/u", $className)) {
            $this->addFileError($file, "Имя класса в соответствие с стандартом PSR-1 
                ( http://www.php-fig.org/psr/psr-1/ru/ ) должно начинаться с большой буквы 
                и использовать заглавные буквы для разделения слов: SomeAwesomeClass. Имя «{$className}» не 
                соответствует этим требованиям.");
        }

        // Проверим имя файла
        $filename = basename($file->getBasename());
        $base = preg_replace("/\.php$/u", '', $filename);

        if ($base !== $className) {

            $windowsGitNotice = '';
            if (mb_strtolower($base) == mb_strtolower($className)) {
                $windowsGitNotice = " Учти, что под Windows, чтобы гит увидел изменения в регистре 
                    букв в имени файла придется повозиться: http://stackoverflow.com/questions/1793735/change-case-of-a-file-on-windows ";
            }

            $this->addFileError($file, "Согласно PSR-0 и PSR-4 имя файла с классом должно соответствовать
                имени класса с точностью до регистра букв, например MyAwesomeClass => MyAwesomeClass.php.
                Имя файла с классом «{$className}» не соответствует этим правилам. $windowsGitNotice");
        }
    }
}
?>