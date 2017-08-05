<?php

namespace SourceAnalyzer;

use SourceAnalyzer\PathUtil;
use \wapmorgan\UnifiedArchive\UnifiedArchive;

class ThirdPartyVerifier
{
    const STATUS_CONTENT_CHANGED = 'contentChanged';
    const STATUS_RENAMED = 'renamed';
    const STATUS_NOT_CHANGED = 'notChanged';
    const STATUS_RENAMED_AND_CHANGED = 'renamedAndChanged';
    const STATUS_UNKNOWN_FILE = 'unknownFile';

    private $debugEnabled = false;
    private $metadata;
    private $hashIndex;
    private $libraryIndex;

    private $librariesPath;
    private $metadataPath;

    public function __construct($librariesPath, $metadataPath)
    {
        $this->librariesPath = $librariesPath;
        $this->metadataPath = $metadataPath;
    }

    public function enableDebug($debugEnabled = true)
    {
        $this->debugEnabled = $debugEnabled;
    }

    public function indexFiles($dir)
    {
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $dir, 
                \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS
        ));
        $indexes = [];

        foreach ($iter as $file) {
            if ($file->getBasename() == 'metadata.json' || $file->getBasename() == '.gitkeep') {
                continue;
            }

            $this->debug("Index {$file->getBasename()}");
            $relPath = PathUtil::getRelativePath($file->getPathname(), $dir);

            if (preg_match("/\.(tgz|zip|tag\.gz)$/ui", $file->getBasename())) {
                $libraryIndex = $this->indexLibraryArchive($file->getPathname(), $relPath);
            } else {
                $libraryIndex = $this->indexLibraryFile($file->getPathname(), $relPath);
            }

            $indexes[] = $libraryIndex;
        }

        return $indexes;
    }

    private function indexLibraryArchive($arcPath, $relArcPath)
    {
        $archive = UnifiedArchive::open($arcPath);
        if (!$archive) {
            throw new \Exception("Cannot unpack archive '$arcPath'");
        }

        $identifier = $this->identifyArchive($archive, $arcPath);
        if (!$identifier) {
            throw new \Exception("Cannot identify library at $arcPath");
        }

        $this->debug("  identified {$identifier['library']}-{$identifier['version']}");

        $files = [];
        $paths = [];

        foreach ($this->listFilesInArchive($archive) as $filePath) {
            $content = $this->unpackFile($archive, $filePath);
            $content = $this->normalizeContent($content);
            $normalizedPath = $this->normailizePath($filePath, $arcPath);
            $paths[$normalizedPath] = $filePath;

            $hash = md5($content);

            $files[$normalizedPath] = $hash;
        }

        $index = [
            'library'   =>  $identifier['library'],
            'version'   =>  $identifier['version'],
            'archive'   =>  $relArcPath,
            'source'    =>  null,
            'files'     =>  $files,
            'paths'     =>  $paths
        ];

        return $index;
    }

    private function indexLibraryFile($path, $relPath)
    {
        $contents = file_get_contents($path);
        assert(!!$contents);

        $identifier = $this->identifyBySignature($contents);
        if (!$identifier) {
            throw new \Exception("Cannot identify file at $path");
        }

        $this->debug("  identified {$identifier['library']}-{$identifier['version']}");
        $contents = $this->normalizeContent($contents);

        $basename = basename($relPath);
        $hash = md5($contents);
        $files = [$basename => $hash];

        $index = [
            'library'   =>  $identifier['library'],
            'version'   =>  $identifier['version'],
            'archive'   =>  null,
            'source'    =>  $relPath,
            'files'     =>  $files,
            'paths'     =>  []
        ];

        return $index;
    }
    

    private function normalizeContent($content)
    {
        // remove \r
        // $content = str_replace("\r", '', $content);

        // ignore whitespace changes
        // $content = preg_replace("/\s+/", ' ', $content);

        return $content;
    }

    private function normailizePath($path, $archiveName)
    {
        $path = str_replace('\\', '/', $path);

        // strip archive name
        $stripPrefix = pathinfo($archiveName, PATHINFO_FILENAME) . '/';
        if (strpos($path, $stripPrefix) === 0) {
            $path = substr($path, strlen($stripPrefix));
        }

        foreach ($this->getPrefixesToStrip() as $prefix) {
            if (preg_match($prefix, $path)) {
                $path = preg_replace($prefix, '', $path);
                $path = ltrim($path, '/');
                break;
            }
        }

        return $path;
    }

    private function identifyArchive($archive, $archivePath)
    {
        $idFiles = $this->getLibraryIdentifierFiles();
        $identifiers = [];

        foreach ($this->listFilesInArchive($archive) as $path) {
            $base = basename($path);
            if (in_array($base, $idFiles)) {
                $content = $this->unpackFile($archive, $path);
                $identifier = $this->identifyBySignature($content);

                if ($identifier) {
                    $identifiers[] = $identifier;
                }
            }
        }

        // remove repeats
        $seen = [];
        foreach ($identifiers as $identifier) {
            $key = "{$identifier['library']}-{$identifier['version']}";
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = $identifier;
        }

        if (count($seen) > 1) {
            throw new \Exception("Archive $archivePath identified as several libraries: " . 
                implode(', ', array_keys($seen)));
        }

        $seen = array_values($seen);
        return $seen ? $seen[0] : null;
    }

    private function listFilesInArchive($archive)
    {
        $files = [];
        foreach ($archive->getFileNames() as $filename) {
            if (preg_match("#[/\\\\]$#", $filename)) {
                continue;
            }

            $files[] = $filename;
        }

        return $files;
    }

    private function unpackFile($archive, $relPath)
    {
        return $archive->getFileContent($relPath);
    }

    private function debug($text)
    {
        if ($this->debugEnabled) {
            fwrite(STDERR, "$text\n");
        }
    }

    public function saveMetadataFile(array $indexes)
    {
        $json = json_encode($indexes, JSON_PRETTY_PRINT);
        $path = $this->getMetadataPath();
        file_put_contents($path, $json);
    }

    private function identifyBySignature($contents)
    {
        $signatures = [
            '#\*\s+Bootstrap\s+v(?P<version>[\d.]+)\s+#i' => 'bootstrap',
            '#jQuery\s+JavaScript\s+Library\s+v(?P<version>[\d.]+)#i' => 'jquery',
            '#jQuery v(?P<version>[\d.]+)#' => 'jquery'
        ];

        foreach ($signatures as $signature => $name) {
            $m = null;
            if (!preg_match($signature, $contents, $m)) {
                continue;
            }

            return [
                'library'   => $name,
                'version'   => $m['version']
            ];
        }

        return null;
    }

    private function getLibraryIdentifierFiles()
    {
        return [
            'bootstrap.css'
        ];
    }

    private function getPrefixesToStrip()
    {
        return [
            //'#^bootstrap-[\d.]+-dist/#'
        ];
    }

    private function getMetadataPath()
    {
        return $this->metadataPath;
        // return dirname(__DIR__) . '/assets/metadata.json';
    }    

    private function getAssetsPath()
    {
        return $this->librariesPath;
        // return dirname(__DIR__) . '/assets/libraries/';
    }

    private function getMetadata()
    {
        if (!$this->metadata) {
            $data = file_get_contents($this->getMetadataPath());
            assert(!!$data);
            $this->metadata = json_decode($data, true);
            assert(!!$this->metadata);
        }

        return $this->metadata;
    }

    private function getHashIndex()
    {
        if (!$this->hashIndex) {
            $metadata = $this->getMetadata();
            $hashIndex = [];

            foreach ($metadata as $library) {
                $name = $this->getLibraryName($library);
                foreach ($library['files'] as $file => $hash) {
                    $hashIndex[$hash][] = ['file' => $file, 'library' => $name];
                }
            }

            $this->hashIndex = $hashIndex;
        }

        return $this->hashIndex;
    }

    private function getLibraryIndex()
    {
        if (!$this->libraryIndex) {
            $index = [];
            foreach ($this->getMetadata() as $library) {
                $name = $this->getLibraryName($library);
                $index[$name][] = $library;
            }

            $this->libraryIndex = $index;
        }
        
        return $this->libraryIndex;
    }

    private function getLibraryName(array $library)
    {
        return "{$library['library']}-{$library['version']}";
    }
    
    /**
     * There can be several libraries with the same name (e.g. packed version, minified etc)
     */
    private function getLibrariesByName($name)
    {
        assert(!!$name);
        $index = $this->getLibraryIndex();
        return $index[$name];
    }
    
    public function identifyFile($contents)
    {
        $ids = $this->identifyBySignature($contents);
        return $ids ? "{$ids['library']}-{$ids['version']}" : null;
    }

    public function getFileHash($contents)
    {
        $contents = $this->normalizeContent($contents);
        return md5($contents);
    }

    /**
     * @return array  [
     *                   'status'  => self::RESULT_*, 
     *                   'library' => library name, 
     *                   'file'    => file path inside library
     *                ]
     */
    public function getFileStatus($contents, $path)
    {
        $basename = basename($path);
        $fileHash = $this->getFileHash($contents);

        $renameResult = $this->isFileRenamed($fileHash, $path);
        if ($renameResult) {
            return $renameResult;
        }

        $identifier = $this->identifyFile($contents);
        if (!$identifier) {
            return [
                'status'    => self::STATUS_UNKNOWN_FILE,
                'library'   => null, 
                'file'      => null
            ];
        }

        $libraryNames = [$identifier];
        $allLibraries = [];

        foreach (array_unique($libraryNames) as $libraryName) {
            $libraries = $this->getLibrariesByName($libraryName);
            $allLibraries = array_merge($allLibraries, $libraries);
        }

        $matchedFile = null;

        foreach ($allLibraries as $library) {
            foreach ($library['files'] as $relPath => $hash) {
                $libraryName = $this->getLibraryName($library);
                $libraryFileBasename = basename($relPath);

                if ($libraryFileBasename == $basename && $hash == $fileHash) {
                    return array(
                        'status'  => self::STATUS_NOT_CHANGED,
                        'library' => $libraryName,
                        'file'    => $relPath
                    );
                }

                if ($libraryFileBasename == $basename && $hash != $fileHash) {
                    $matchedFile = [
                        'status'    => self::STATUS_CONTENT_CHANGED,
                        'library'   => $libraryName, 
                        'file'      => $relPath
                    ];
                }
            }
        }

        if ($matchedFile) {
            return $matchedFile;
        }

        return [
            'status'    => self::STATUS_RENAMED_AND_CHANGED,
            'library'   => $identifier, 
            'file'      => null
        ];
    }

    public function extractOriginalFile($libraryName, $filePath)
    {
        assert(!!$libraryName);
        $libraries = $this->getLibrariesByName($libraryName);

        foreach ($libraries as $library) {

            if ($library['archive']) {
                $libraryPath = $library['archive'];
                $libraryPath = $this->getAssetsPath() . '/' . $libraryPath;
                assert(file_exists($libraryPath));

                if (!isset($library['paths'][$filePath])) {
                    continue;
                }

                $archive = UnifiedArchive::open($libraryPath);
                assert(!!$archive);

                $fullPath = $library['paths'][$filePath];

                $content = $archive->getFileContent($fullPath);
                assert(false !== $content);
                return $content;
            }

            assert(!!$library['source']);

            // File name must match saved in library metadata
            if (basename($library['source']) == basename($filePath)) {
                $source = $this->getAssetsPath() . '/' . $library['source'];
                $content = file_get_contents($source);
                assert(!!$content);

                return $content;        
            }
        }
        
        throw new \Exception("Failed to find source for library=$libraryName, path=$filePath");
    }

    public function isFileRenamed($hash, $path)
    {
        $index = $this->getHashIndex();
        if (!isset($index[$hash])) {
            return false;
        }

        foreach ($index[$hash] as $match) {
            $libraryName = $match['library'];
            $relName = $match['file'];

            if (PathUtil::endsWith($path, $relName)) {
                return false;
            }
        }

        return [
            'status'    => self::STATUS_RENAMED,
            'library'   => $libraryName, 
            'file'      => $relName
        ];
    }
}