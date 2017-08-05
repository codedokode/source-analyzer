<?php

namespace SourceAnalyzer\Common;

use SourceAnalyzer\Base\Checker;
use SourceAnalyzer\File;

class ExceptionUsageChecker extends Checker
{
    public function check(File $file)
    {
        if (!$file->isPhpExtension()) {
            return;
        }

        $content = $file->getContent();

        preg_match_all("/\bcatch\s*\([^)]+\)\s*\{([^}]+)\}/ui", $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $code = $match[0];
            if (preg_match("/\becho\b|\bprint|\bdie\b/ui", $code)) {
                $quoted = $this->quoteCode($code);
                $this->addFileError($file, "$quoted
                    
                    Здесь ты ловишь исключение и самостоятельно его выводишь. Это плохая идея,
                    так как об исключении узнает пользователь сайта (и ничего не поймет), а 
                    ты не узнаешь, так как оно не пишется в логи. Не надо ловить и выводить исключения
                    через echo, PHP сам по умолчанию ловит все непойманные исключения, записывает
                    их в лог (который ты можешь позже прочесть) и, если включен display_errors в php.ini,
                    выводит информацию об исключении на экран (а если выключен то выводится белая страница, 
                    что конечно не очень красиво).

                    Если ты хочешь выводить красивую страницу-заглушку для пользователя при возникновении
                    исключения, удобнее всего задействовать обработчик непойманных исключений,
                    который задается функцией set_exception_handler (мануал: 
                    http://php.net/manual/ru/function.set-exception-handler.php ). Не забудь выдавать
                    HTTP-код вроде 500, это положено по стандарту HTTP и это подскажет поисковикам
                    не индексировать страницу ошибки.

                    Урок про исключения: https://gist.github.com/codedokode/65d43ca5ac95c762bc1a
                ");
            }
        }
    }

}