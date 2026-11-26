<?php

namespace MyEvent\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChartsTest extends TestCase
{
    /** @return array<string, array{int, int, list<int>}> */
    public static function axes(): array
    {
        return [
            'empty'   => [0, 1, [0, 1]],
            'tiny'    => [3, 4, [0, 1, 2, 3, 4]],
            'exact'   => [20, 25, [0, 5, 10, 15, 20, 25]],
            'twenty-two' => [22, 30, [0, 10, 20, 30]],
            'hundred' => [100, 150, [0, 50, 100, 150]],
            'odd'     => [137, 150, [0, 50, 100, 150]],
        ];
    }

    #[DataProvider('axes')]
    public function testAxisUsesRoundSteps(int $max, int $top, array $ticks): void
    {
        $axis = report_axis($max);
        $this->assertSame($top, $axis['max']);
        $this->assertSame($ticks, $axis['ticks']);
        $this->assertLessThanOrEqual(5, count($axis['ticks']) - 1);
    }

    public function testFormatRate(): void
    {
        $this->assertSame('75%', format_rate(75.0));
        $this->assertSame('83.3%', format_rate(83.3));
        $this->assertSame('–', format_rate(null));
        $this->assertNull(report_rate(1, 0));
        $this->assertSame(33.3, report_rate(1, 3));
    }

    public function testColumnChartLabelsOnlyThePeakAndTheLatestMonth(): void
    {
        $html = render_column_chart([
            ['label' => 'Jan 2026', 'count' => 2],
            ['label' => 'Feb 2026', 'count' => 9],
            ['label' => 'Mar 2026', 'count' => 4],
        ], 'registrations', 'Registrations per month');

        $this->assertSame(3, substr_count($html, 'class="viz-col"'));
        $this->assertSame(2, substr_count($html, 'class="viz-value"'));
        $this->assertStringContainsString('data-tip="Feb 2026: 9 registrations"', $html);
        $this->assertStringContainsString('highest 9 in Feb 2026', $html);
        $this->assertStringContainsString('height:90%', $html, '9 of an axis topped at 10');
    }

    public function testChartsEscapeLabels(): void
    {
        $html = render_bar_list([['label' => '<script>x</script>', 'count' => 1]], 'things');
        $this->assertStringNotContainsString('<script>', $html);

        $this->assertStringContainsString('No things yet', render_bar_list([['label' => 'A', 'count' => 0]], 'things'));
        $this->assertStringContainsString('No registrations in this period', render_column_chart([], 'registrations', 'x'));
    }

    public function testBarListSharesAddUp(): void
    {
        $html = render_bar_list([['label' => 'Student', 'count' => 3], ['label' => 'Staff', 'count' => 1]], 'registrations');

        $this->assertStringContainsString('Student: 3 registrations (75%)', $html);
        $this->assertStringContainsString('width:100%', $html);
        $this->assertStringContainsString('width:33.333%', $html);
    }

    public function testRateMeter(): void
    {
        $this->assertStringContainsString('width:100%', render_rate_meter(120.0), 'over-full events are capped');
        $this->assertStringContainsString('–', render_rate_meter(null));
    }
}
