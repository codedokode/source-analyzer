<?php

namespace SourceAnalyzer\Helper;

class PhpTypeHintIndex
{
    private $statParsedDecls;
    private $statParseErrors;
    private $statRecordsAdded;

    private $debugEnabled = false;

    private $indexedFunctions;
    private $index;

    public function setDebugEnabled($debugEnabled = true)
    {
        $this->debugEnabled = $debugEnabled;
    }
    
    private function printDebug($text)
    {
        if ($this->debugEnabled) {
            fwrite(STDERR, $text . "\n");
        }
    }

    /**
     * Parses PHP doc file for list of standart functions with type hints
     */
    public function indexPhpDocStream($stream)
    {
        $bufferSize = 100000;
        $buffer = '';
        $offset = 0;

        $this->statParsedDecls = 0;
        $this->statParseErrors = 0;
        $this->statRecordsAdded = 0;
        $this->indexedFunctions = [];

        $pattern = "#<div[^>]+\bmethodsynopsis\b.*?</div>#is";

        while (!feof($stream)) {
            $this->readStream($stream, $buffer, $bufferSize, $offset);

            preg_match_all($pattern, $buffer, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            $maxOffset = floor($bufferSize / 2);

            // Find max offset to remove data from buffer
            foreach ($matches as $matchPair) {
                list($fullMatch, $matchOffset) = $matchPair[0];
                $localOffset = $matchOffset + strlen($fullMatch);
                if ($localOffset > $maxOffset) {
                    $maxOffset = $localOffset;
                }

                $this->indexFunctionDoc($fullMatch);
            }

            $buffer = substr($buffer, $maxOffset);
        }

        $this->addSpecialFunctions();

        $this->printDebug(sprintf("Scanned %d declarations, %d parse errors, %d records added\n",
            $this->statParsedDecls,
            $this->statParseErrors,
            $this->statRecordsAdded
        ));

        return $this->indexedFunctions;
    }

    private function addSpecialFunctions()
    {
        $this->indexedFunctions['count'] = ['array'];
    }

    public function saveIndex(array $indexedFunctions)
    {
        $path = $this->getIndexFile();    
        file_put_contents($path, json_encode($indexedFunctions, JSON_PRETTY_PRINT));
    }

    private function loadIndexFromFile()
    {
        $path = $this->getIndexFile();
        $content = file_get_contents($path);
        $index = json_decode($content, true);

        return $index;
    }
    
    private function getIndex()
    {
        if (null === $this->index) {
            $this->index = $this->loadIndexFromFile();
        }

        return $this->index;
    }
    
    private function getIndexFile()
    {
        return (__DIR__) . '/../../assets/type-hints.json';
    }
    
    private function readStream($stream, &$buffer, $bufferSize, &$offset) 
    {
        do {
            $needBytes = $bufferSize - strlen($buffer);
            if (!$needBytes) {
                break;
            }

            $piece = fread($stream, $needBytes);
            if ($piece === false) {
                throw new \Exception("Read error at offset $osffset, length=$needBytes");
            }

            // EOF
            if ($piece === '') {
                break;
            }

            $buffer .= $piece;
            $offset += strlen($piece);
        } while (true);
    }
    
    private function indexFunctionDoc($html)
    {
        // Fix XML
        $html = preg_replace('/&(?![#a-z0-9]+;)/', '&amp;', $html);
        $this->statParsedDecls++;

        try {
            $xml = new \SimpleXMLElement($html);
        } catch (\Exception $e) {
            if (preg_match("/Opening and ending tag mismatch/i", $e->__toString())) {
                $this->printDebug("Warning: failed to parse $html");
                $this->statParseErrors++;
                return;
            }

            throw $e;
        }

        $accessModifier = $xml->xpath('//span[contains(@class, "modifier")]');
        if ($accessModifier) {
            return;
        }

        $methodNameXml = $xml->xpath('//span[contains(@class, "methodname")]');
        $paramsXml = $xml->xpath('//span[contains(@class, "methodparam")]');
        $methodName = strip_tags($methodNameXml[0]->asXml());

        $hints = [];
        foreach ($paramsXml as $paramXml) {
            $typeHintXml = $paramXml->xpath('span[contains(@class, "type")]');
            $typeHint = strval($typeHintXml ? $typeHintXml[0]->asXml() : '');

            $hints[] = $typeHint ? strip_tags($typeHint) : '?';
        }

        $this->indexMethod($methodName, $hints);
    }
    
    private function indexMethod($methodName, array $hints)
    {
        if (false !== strpos($methodName, '::')) {
            return;
        }

        $methodName = mb_strtolower($methodName);
        $ignoreNames = ['handler'];

        if (in_array($methodName, $ignoreNames)) {
            return;
        }

        $badList = $this->getScalarTypes();
        $good = array_diff($hints, $badList);

        if (!$good) {
            return;
        }

        $this->statRecordsAdded++;
        $this->indexedFunctions[$methodName] = $hints;
    }

    private function getScalarTypes()
    {
        return ['?', 'int', 'mixed', 'bool', 'resource', 'string', 
            'float', 'double', 'boolean', 'integer'];
    }

    public function getHintsForFunction($functionName)
    {
        $index = $this->getIndex();
        $functionName = mb_strtolower($functionName);
        return isset($index[$functionName]) ? $index[$functionName] : null;
    }
    
    /**
     * $argIndex is zero-based
     */
    public function isTypeHinted($functionName, $argIndex)
    {
        $hints = $this->getHintsForFunction($functionName);
        if (!$hints) {
            return null;
        }

        if (!isset($hints[$argIndex])) {
            return null;
        }

        $type = $hints[$argIndex];
        $scalar = $this->getScalarTypes();

        return in_array($type, $scalar) ? null : $type;
    }
}