<?php

namespace SourceAnalyzer\Common;

use SourceAnalyzer\Base\Checker;
use SourceAnalyzer\File;

class XhtmlChecker extends Checker
{
    public function check(File $file)
    {
        // Не проверять JS/SVG
        if (preg_match("/\.(svg|js)$/iu", $file->getBasename())) {
            return;
        }

        if (preg_match("#<[a-z][^>]{1,100}/\s*>#u", $file->getContent(), $m)) {

            $shortTag = $m[0];
            if (mb_strlen($shortTag) > 35) {
                $shortTag = mb_substr($m[0], 0, 30) . '…' . mb_substr($m[0], -5);
            }

            $this->addFileError($file, "Слеш в конце одиночного тега ({$shortTag}) используется только
                в XHTML и XML, в HTML он не ставится.");
        }
    }
}
