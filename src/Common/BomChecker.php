<?php

namespace SourceAnalyzer\Common;

use SourceAnalyzer\Base\Checker;
use SourceAnalyzer\File;

class BomChecker extends Checker
{
    public function check(File $file)
    {
        $firstBytes = substr($file->getContent(), 0, 3);
        if ($firstBytes == "\xef\xbb\xbf") {
            $this->addFileError($file, "Похоже, что файл начинается с BOM. BOM это специальный 
                невидимый символ, который некоторые редакторы вставляют в начало файла. PHP выведет
                этот код, и он может помешать тебе использовать функции вроде установки
                кук или отправки заголовков (так как их нельзя отправлять после того как выведен хотя
                бы один символ). Сохрани этот файл в кодировке utf-8 без BOM. 
                Wiki: https://ru.wikipedia.org/wiki/%D0%9C%D0%B0%D1%80%D0%BA%D0%B5%D1%80_%D0%BF%D0%BE%D1%81%D0%BB%D0%B5%D0%B4%D0%BE%D0%B2%D0%B0%D1%82%D0%B5%D0%BB%D1%8C%D0%BD%D0%BE%D1%81%D1%82%D0%B8_%D0%B1%D0%B0%D0%B9%D1%82%D0%BE%D0%B2");
        }
    }
    
}