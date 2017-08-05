<?php

namespace SourceAnalyzer;

class PathUtil
{
    public static function normalize($path)
    {
        $path = str_replace('\\', '/', $path);
        $path = preg_replace("#/(\./)+#", '/', $path);
        $path = preg_replace("#^./#", '', $path);
        $path = preg_replace("#(.)/$#", '$1', $path);

        return $path;
    }
    
    public static function endsWith($path, $subPath)
    {
        $path = self::normalize($path);
        $subPath = self::normalize($subPath);
        
        $pathParts = explode('/', $path);
        $subPathParts = explode('/', $subPath);

        while (count($subPathParts)) {
            $a = array_pop($subPathParts);
            $b = array_pop($pathParts);

            if ($a !== $b) {
                return false;
            }
        }

        return true;
    }
    
    public static function getRelativePath($path, $base)
    {
        $path = self::normalize(realpath($path));
        $base = self::normalize(realpath($base));

        $pathParts = explode('/', $path);
        $baseParts = explode('/', $base);

        for ($i = 0; $i < count($baseParts); $i++) {
            if ($i >= count($pathParts)) {
                break;
            }

            if ($pathParts[$i] !== $baseParts[$i]) {
                break;
            }
        }

        $leftParts = count($baseParts) - $i;
        assert($leftParts >= 0);

        if ($leftParts == 0) {
            $relPath = implode('/', array_slice($pathParts, $i));
            return $relPath;
        }

        $prefix = str_repeat('../', $leftParts);
        $tail = implode('/', array_slice($pathParts, $i));

        $relPath = $prefix . $tail;
        return $relPath;
    }
}