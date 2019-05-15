<?php

namespace CCUPLUS\CourseImport;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;

class Downloader
{
    /**
     * 課程壓縮檔下載網址.
     *
     * @var string
     */
    protected $url = 'https://kiki.ccu.edu.tw/~ccmisp06/Course/zipfiles/%s.tgz';

    /**
     * 下載課程資料壓縮檔.
     *
     * @param string $semester
     *
     * @return string
     *
     * @throws BadResponseException
     * @throws RequestException
     * @throws RuntimeException
     */
    public function download(string $semester): string
    {
        $response = (new Client)->get(sprintf($this->url, $semester), [
            'connect_timeout' => 5,
            'timeout' => 60,
        ]);

        $path = $this->tempfile();

        file_put_contents($path, $response->getBody()->getContents());

        return $path;
    }

    /**
     * 創建暫存檔並取得檔案位址.
     *
     * @return string
     */
    protected function tempfile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ccu-plus-course-import-');

        if (false === $path) {
            throw new RuntimeException('Could not create temp file.');
        }

        $pathWithExtension = sprintf('%s.tgz', $path);

        if (!rename($path, $pathWithExtension)) {
            unlink($path);

            throw new RuntimeException('Could not create temp file.');
        }

        return $pathWithExtension;
    }
}
