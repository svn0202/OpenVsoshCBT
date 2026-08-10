<?php

namespace Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PageTimerTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, string, string, string}>
     */
    public static function timerModeProvider(): iterable
    {
        yield 'regular clock' => [[], '/admin/code/index.php', 'Time', 'FJ_start_timer(false, '];
        yield 'exam countdown' => [
            ['examtime' => '90.5', 'timeout_logout' => '1'],
            '/public/code/tce_test_execute.php',
            'Remaining',
            'FJ_start_timer(true, ',
        ];
    }

    /**
     * @param array<string, mixed> $request
     */
    #[DataProvider('timerModeProvider')]
    public function testTimerRenderingPreservesModeAndRuntimeSettings(
        array $request,
        string $scriptName,
        string $label,
        string $startCall,
    ): void {
        $settingsSource = <<<'PHP'
<?php
function openvsosh_get_runtime_settings(): array
{
    return [
        'timer_warning_color' => '#ffee00',
        'timer_critical_color' => '#cc0000',
        'timer_warning_seconds' => 300,
        'timer_critical_seconds' => 60,
    ];
}

function openvsosh_contrast_text(string $color): string
{
    return $color === '#ffee00' ? '#111111' : '#ffffff';
}
PHP;
        [$status, $output] = \F_tcecode_run_process(
            [
                PHP_BINARY,
                '-r',
                '$root = sys_get_temp_dir() . "/openvsosh-timer-" . uniqid(); '
                    . 'mkdir($root . "/shared/code", 0700, true); '
                    . 'copy($argv[1], $root . "/shared/code/tce_page_timer.php"); '
                    . 'file_put_contents($root . "/shared/code/tce_functions_openvsosh_settings.php", '
                    . 'base64_decode($argv[4], true)); '
                    . 'define("K_NEWLINE", "\\n"); define("K_PATH_SHARED_JSCRIPTS", "/shared/js/"); '
                    . '$_REQUEST = json_decode($argv[2], true); $_SERVER["SCRIPT_NAME"] = $argv[3]; '
                    . '$l = ["w_remaining" => "Remaining", "w_time" => "Time", '
                    . '"w_clock_timer" => "Clock", "m_exam_end_time" => "Finished"]; '
                    . 'ob_start(); require $root . "/shared/code/tce_page_timer.php"; $output = ob_get_clean(); '
                    . 'unlink($root . "/shared/code/tce_page_timer.php"); '
                    . 'unlink($root . "/shared/code/tce_functions_openvsosh_settings.php"); '
                    . 'rmdir($root . "/shared/code"); rmdir($root . "/shared"); rmdir($root); echo $output;',
                dirname(__DIR__) . '/shared/code/tce_page_timer.php',
                json_encode($request, JSON_THROW_ON_ERROR),
                $scriptName,
                base64_encode($settingsSource),
            ],
            dirname(__DIR__) . '/shared/code',
        );

        self::assertSame(0, $status, $output);
        self::assertStringContainsString('action="' . $scriptName . '"', $output);
        self::assertStringContainsString('--timer-warning-bg:#ffee00;--timer-warning-text:#111111;', $output);
        self::assertStringContainsString('--timer-critical-bg:#cc0000;--timer-critical-text:#ffffff"', $output);
        self::assertStringContainsString('class="timerlabel">' . $label . ':</label>', $output);
        self::assertStringContainsString('title="Clock"', $output);
        self::assertStringContainsString('FJ_configure_timer(300,60,', $output);
        self::assertStringContainsString($startCall, $output);
        self::assertStringContainsString("'Finished'", $output);
        self::assertStringContainsString('/shared/js/timer.js?v=20260729-1', $output);
    }
}
