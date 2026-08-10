<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../shared/code/tce_functions_svg_graph.php';

final class SvgGraphTest extends TestCase
{
    public function testBuildsBothSeriesAtRequestedDimensions(): void
    {
        $svg = f_get_svg_graph_code('10v20x30v40', 100, 250);

        self::assertStringStartsWith('<?xml version="1.0"', $svg);
        self::assertStringContainsString('<svg width="100" height="250"', $svg);
        self::assertStringContainsString('<polyline fill="none" stroke="#ff0000"', $svg);
        self::assertStringContainsString('<polyline fill="none" stroke="#0000ff"', $svg);
        self::assertStringEndsWith("</svg>\n", $svg);
    }

    public function testOutputsGeneratedGraph(): void
    {
        ob_start();
        f_get_svg_graph('10v20x30v40', 100, 250);
        $svg = (string) ob_get_clean();

        self::assertStringContainsString('<svg width="100" height="250"', $svg);
        self::assertStringEndsWith("</svg>\n", $svg);
    }

    public function testLargeGraphPreservesFivePointLabelCadence(): void
    {
        $svg = f_get_svg_graph_code(implode('x', array_fill(0, 101, '10v20')), 300, 250);

        self::assertStringContainsString('>95</text>', $svg);
        self::assertStringContainsString('>100</text>', $svg);
        self::assertStringNotContainsString('>101</text>', $svg);
        self::assertSame(202, substr_count($svg, '<circle '));
    }
}
