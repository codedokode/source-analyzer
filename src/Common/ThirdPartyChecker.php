<?php

namespace SourceAnalyzer\Common;

use SourceAnalyzer\Base\Checker;
use SourceAnalyzer\File;
use \SourceAnalyzer\ThirdPartyVerifier;

class ThirdPartyChecker extends Checker
{
    private $verifier;
    private $librariesSeen;

    public function __construct()
    {
        $assetsDir = dirname(dirname(__DIR__)) . '/assets/';
        $libPath = $assetsDir . '/libraries/';
        $metadataPath = $assetsDir . '/metadata.json';

        $this->verifier = new ThirdPartyVerifier($libPath, $metadataPath);
    }
    
    public function check(File $file)
    {
        $whyChangingIsBad = "Изменять файлы из стронних библиотек плохая идея по двум причинам. 
            Во-первых, человек, разбирающийся в твоем коде, вряд ли сможет найти твои правки 
            в куче кода. Он будет наивно думать что библиотека не изменена. 
            Во-вторых, когда библиотеку захотят обновить, либо потеряются твои 
            правки, либо эти правки придется переносить как-то вручную. Представь себе какой 
            это объем труда. К тому же эти изменения могут еще и оказаться несовместимыми с новой 
            версией библиотеки.

            Никогда не меняй файлы внешних библиотек.

            Или же ты просто используешь неофициальную или нестандартную сборку? Лучше 
            использовать официальную.
        ";

        $content = $file->getContent();

        $changeData = $this->verifier->getFileStatus($content, $file->getPath());
        $status = $changeData['status'];

        if ($status == ThirdPartyVerifier::STATUS_NOT_CHANGED || 
            $status == ThirdPartyVerifier::STATUS_UNKNOWN_FILE) {
            // файл не изменен, все ок
            return;
        }

        if ($status == ThirdPartyVerifier::STATUS_RENAMED) {
            $this->addFileError($file, "Упс, похоже что файл {$file->getPath()} это переименованный 
                файл '{$changeData['file']}' из библиотеки {$changeData['library']}. Не переименовывай и 
                не изменяй файлы из внешних библиотек. 

                $whyChangingIsBad");

            return;
        }

        if ($status == ThirdPartyVerifier::STATUS_RENAMED_AND_CHANGED) {
            $this->addFileError($file, "Так-так. Похоже, что файл {$file->getPath()} это изменный 
                и переименованный файл из библиотеки {$library}. Ты думал, робот это не заметит?

                $whyChangingIsBad
            ");

            return;
        }

        assert($status == ThirdPartyVerifier::STATUS_CONTENT_CHANGED);

        $origin = $this->verifier->extractOriginalFile($changeData['library'], $changeData['file']);
        $originLines = $this->preprocessFileForCompare($origin);
        $contentLines = $this->preprocessFileForCompare($content);

        $opts = ['ignoreWhitespace' => true, 'ignoreNewLines' => true];
        $diff = new \Diff($originLines, $contentLines, $opts);
        $renderer = new \Diff_Renderer_Text_Unified();
        $changes = $diff->Render($renderer);

        $changes = $this->shortenDiff($changes);

        $this->addFileError($file, "Похоже, что ты правил файл {$file->getPath()}, который является 
            частью библиотеки {$changeData['library']}. Не делай так. Ты думал, робот это не заметит?

            $whyChangingIsBad

            Вот список изменений (в формате https://ru.wikipedia.org/wiki/Diff ): 

            {$this->quoteCode($changes)}
        ");
    }

    private function preprocessFileForCompare($content)
    {
        $lines = explode("\n", $content);
        $result = [];

        foreach ($lines as &$line) {
            $line = rtrim($line);
            if (trim($line) === '') {
                continue;
            }

            $result[] = $line;
        }

        return $result;
    }

    private function shortenDiff($diff)
    {
        $limit = 70;
        $lineMax = 20;

        $lines = explode("\n", $diff);
        foreach ($lines as &$line) {
            if (mb_strlen($line) > $limit) {
                $line = mb_substr($line, 0, $limit) . '…';
            }
        }

        if (count($lines) > $lineMax) {
            $left = count($lines) - $lineMax;
            $lines = array_slice($lines, 0, $lineMax);
            $lines[] = "... (остальные строки ($left) не показаны) ...";
        }

        return implode("\n", $lines);
    }

    public function analyze()
    {
        
    }
}