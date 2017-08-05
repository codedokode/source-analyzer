<?php 

namespace Tests\SourceAnalyzer;

use SourceAnalyzer\ThirdPartyVerifier;

class ThirdPartyVerifierTest extends \PHPUnit_Framework_TestCase
{
    private $metadataPath;

    public function setUp()
    {
        $this->metadataPath = tempnam(sys_get_temp_dir(), 'source-analyzer-test');
    }
    
    public function tearDown()
    {
        if (file_exists($this->metadataPath)) {
            unlink($this->metadataPath);
        }
    }

    private function getAssetsDir()
    {
        return __DIR__ . '/assets/third-party-verifier';
    }

    public function testVerifierCanIndexFiles()
    {
        $librariesDir = $this->getAssetsDir() . '/libraries';
        $this->assertTrue(is_dir($librariesDir));

        $verifier = new ThirdPartyVerifier($librariesDir, $this->metadataPath);
        $indexes = $verifier->indexFiles($librariesDir);
        $this->assertNotEmpty($indexes);
    }

    public function testVerifierCanDetectChanges()
    {
        $assetsDir = $this->getAssetsDir();
        $librariesDir = $this->getAssetsDir() . '/libraries';
        $this->assertTrue(is_dir($librariesDir));

        $verifier = new ThirdPartyVerifier($librariesDir, $this->metadataPath);
        $indexes = $verifier->indexFiles($librariesDir);
        $verifier->saveMetadataFile($indexes);

        $renamedFile = $assetsDir . '/test-files-unchanged/renamed-bootstrap.css';
        $changedFile = $assetsDir . '/test-files-changed/bootstrap-theme.css';
        $normalFile = $assetsDir .'/test-files-unchanged/css/bootstrap-theme.css';

        $renamedJq = $assetsDir . '/test-files-unchanged/renamed-jquery-33.3.3.js';
        $normalJq = $assetsDir . '/test-files-unchanged/jquery-33.3.3.js';
        $changedJq = $assetsDir . '/test-files-changed/jquery-33.3.3.js';
        $changedAndRenamedJq = $assetsDir  . '/test-files-changed/renamed-and-changed-jquery-33.3.3.js';
        $unknownFile = $assetsDir . '/test-files-unchanged/unknown.file';

        $expectedStatuses = [
            $renamedFile            => ThirdPartyVerifier::STATUS_RENAMED, 
            $changedFile            => ThirdPartyVerifier::STATUS_CONTENT_CHANGED, 
            $normalFile             => ThirdPartyVerifier::STATUS_NOT_CHANGED,
            $renamedJq              => ThirdPartyVerifier::STATUS_RENAMED, 
            $normalJq               => ThirdPartyVerifier::STATUS_NOT_CHANGED, 
            $changedJq              => ThirdPartyVerifier::STATUS_CONTENT_CHANGED, 
            $changedAndRenamedJq    => ThirdPartyVerifier::STATUS_RENAMED_AND_CHANGED,
            $unknownFile            => ThirdPartyVerifier::STATUS_UNKNOWN_FILE
        ];

        foreach ($expectedStatuses as $path => $expectedStatus) {
            $content = file_get_contents($path);

            $library = $verifier->identifyFile($content);
            // if (!$library) {
            //     echo "  not identified\n";
            // } else {
            //     echo "  idd as $library\n";
            // }

            $hash = $verifier->getFileHash($content);
            $changeResult = $verifier->getFileStatus($content, $path);
            $isRenamed = $verifier->isFileRenamed($hash, $path);

            // if (!$isRenamed) {
            //     echo "  not renamed\n";
            // } else {
            //     echo "  renamed (lib={$isRenamed['library']}, file={$isRenamed['file']})\n";
            // }

            $this->assertNotEmpty($changeResult);
            $status = $changeResult['status'];

            if ($status == ThirdPartyVerifier::STATUS_RENAMED) {
                $this->assertNotEmpty($isRenamed);
            } else {
                $this->assertFalse($isRenamed);
            }

            if ($status == ThirdPartyVerifier::STATUS_NOT_CHANGED) {
                // echo "  not changed\n";
            } elseif ($status == ThirdPartyVerifier::STATUS_UNKNOWN_FILE) {
                // echo "  cannot match to library file\n";
            } elseif ($status == ThirdPartyVerifier::STATUS_RENAMED_AND_CHANGED) {
                // echo "  renamed and changed, lib={$changeResult['library']}\n";
            } elseif ($status == ThirdPartyVerifier::STATUS_RENAMED) {
                // echo "  renamed, original={$changeResult['file']}\n";
            } elseif ($status == ThirdPartyVerifier::STATUS_CONTENT_CHANGED) {
                // echo "  changed (lib: {$changeResult['library']}, file: '{$changeResult['file']}'):\n";
                $origin = $verifier->extractOriginalFile(
                    $changeResult['library'], 
                    $changeResult['file']
                );
                // $opts = ['ignoreWhitespace' => true];
                // $originLines = explode("\n", $origin);
                // $contentLines = explode("\n", $content);

                // $diff = new Diff($originLines, $contentLines, $opts);
                // $renderer = new Diff_Renderer_Text_Unified();

                 // echo  $dummy = $diff->Render($renderer);
                // echo "\n\n";
            }

            $this->assertEquals($expectedStatus, $status);
        }
    }
}


