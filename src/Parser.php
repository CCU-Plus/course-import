<?php

namespace CCUPLUS\CourseImport;

use Illuminate\Support\Str;
use PHPHtmlParser\Dom;
use PHPHtmlParser\Dom\Collection;
use PHPHtmlParser\Dom\HtmlNode;
use PHPHtmlParser\Exceptions\ChildNotFoundException;

class Parser
{
    /**
     * 開課表格各欄位標題.
     *
     * @var array
     */
    protected $headers = [];

    /**
     * 取得系所開課資料.
     *
     * @param string $path
     *
     * @return array
     */
    public function parse(string $path): array
    {
        $dom = (new Dom)->loadFromFile($path, [
            'enforceEncoding' => 'UTF-8',
            'cleanupInput' => false,
            'preserveLineBreaks' => true,
        ]);

        /** @var HtmlNode[] $rows */

        $rows = $dom->find('tr')->toArray();

        $this->headers = array_map(function (HtmlNode $node) {
            return trim($node->firstChild()->text());
        }, array_shift($rows)->find('th')->toArray());

        return [
            'code' => basename($path, '.html'),
            'name' => Str::after($dom->find('title', 0)->text(), '--'),
            'courses' => array_map(function (HtmlNode $row) {
                return $this->rowToArray($row->find('td'));
            }, $rows),
        ];
    }

    /**
     * 將課程資料轉為 key => value 形式.
     *
     * @param Collection $columns
     *
     * @return array
     */
    protected function rowToArray(Collection $columns): array
    {
        foreach ($this->keys() as $key => $value) {
            /** @var HtmlNode $node */

            $node = $columns[$key]->firstChild();

            $converter = sprintf('parse%s', ucfirst($value));

            $result[$value] = method_exists($this, $converter)
                ? $this->{$converter}($node)
                : trim($node->text());
        }

        return $result ?? [];
    }

    /**
     * 取得各欄位名稱.
     *
     * @return array
     */
    protected function keys(): array
    {
        $keys = [
            'grade', 'code', 'class', 'name', 'professor',
            'hours', 'credit', 'type', 'time', 'location',
            'maximum', 'outline', 'remark',
        ];

        $map = [
            '開課學制' => ['needle' => 'maximum', 'replacement' => 'rule'], // 研究所課程
            '向度' => ['needle' => 'grade', 'replacement' => 'dimension'], // 通識課程
        ];

        foreach ($map as $key => $value) {
            if (in_array($key, $this->headers, true)) {
                array_splice($keys, array_search($value['needle'], $keys) + 1, 0, $value['replacement']);
            }
        }

        if (!in_array('課程大綱', $this->headers, true)) {
            array_splice($keys, array_search('outline', $keys), 1);
        }

        return $keys;
    }

    /**
     * 取得課程中文及英文名稱.
     *
     * @param HtmlNode $node
     *
     * @return array
     */
    private function parseName(HtmlNode $node): array
    {
        /** @var HtmlNode $cht */
        /** @var HtmlNode $eng */

        [$cht, , $eng] = $node->getChildren();

        return [
            'cht' => trim($cht->text()),
            'eng' => trim($eng->text()),
        ];
    }

    /**
     * 取得課程授課教授.
     *
     * @param HtmlNode $node
     *
     * @return array
     */
    private function parseProfessor(HtmlNode $node): array
    {
        // 格式：`A B `

        return array_values(
            array_filter(
                explode(' ', trim($node->text()))
            )
        );
    }

    /**
     * 取得課程上課時數.
     *
     * @param HtmlNode $node
     *
     * @return array
     */
    private function parseHours(HtmlNode $node): array
    {
        // 格式：`2 2/0/0`

        [$total, $detail] = explode(' ', trim($node->text()));

        [$regular, $experiment, $discussion] = explode('/', $detail);

        return compact('total', 'regular', 'experiment', 'discussion');
    }

    /**
     * 取得課程上課時間.
     *
     * @param HtmlNode $node
     *
     * @return array
     */
    private function parseTime(HtmlNode $node): array
    {
        $days = $this->splitTimeByDay(trim($node->text()));

        foreach ($days as &$period) {
            $period = array_map('trim', explode(',', $period));
        }

        return $days;
    }

    /**
     * 以星期切割課程授課時間.
     *
     * @param string $time
     *
     * @return array
     */
    private function splitTimeByDay(string $time): array
    {
        $chars = preg_split('//u', $time, null, PREG_SPLIT_NO_EMPTY);

        $map = ['一' => '1', '二' => '2', '三' => '3', '四' => '4', '五' => '5', '六' => '6', '日' => '7'];

        $key = null;

        foreach ($chars as $char) {
            if (in_array($char, array_keys($map), true)) {
                $key = $map[$char];
            } else {
                $result[$key][] = $char;
            }
        }

        return array_map(function ($chars) {
            return trim(implode('', $chars));
        }, $result ?? []);
    }

    /**
     * 取得課程大綱連結.
     *
     * @param HtmlNode $node
     *
     * @return string
     *
     * @throws ChildNotFoundException
     */
    private function parseOutline(HtmlNode $node): string
    {
        $link = $node->firstChild()->getAttribute('href');

        return str_replace('http://', 'https://', trim($link));
    }
}
