<?php

namespace Test;

final class FormattingReport extends \TcePdfReport
{
    /** @var list<string> */
    public array $htmlBlocks = [];

    /** @var list<string> */
    public array $bookmarks = [];

    /** @var array<array-key,mixed>|null */
    public ?array $detailsData = null;

    public bool $detailsOnlyText = false;

    public function __construct()
    {
    }

    public function writeReportHTML(string $html): void
    {
        $this->htmlBlocks[] = $html;
    }

    public function setBookmark(
        string $name,
        string $link = '',
        int $level = 0,
        int $page = -1,
        float $posx = 0,
        float $posy = 0,
        string $fstyle = '',
        string $color = '',
    ): void {
        $this->bookmarks[] = $name;
    }

    /** @param array<array-key,mixed> $data */
    public function printUserTestDetails($data, $onlytext = false): void
    {
        $this->detailsData = $data;
        $this->detailsOnlyText = $onlytext;
    }
}
