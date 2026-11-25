<?php

namespace MyEvent\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    protected function tearDown(): void
    {
        $_GET = [];
    }

    public function testFormatFee(): void
    {
        $this->assertSame('Free', format_fee(0));
        $this->assertSame('Free', format_fee(null));
        $this->assertSame('RM 10.00', format_fee('10'));
        $this->assertSame('RM 1,250.50', format_fee(1250.5));
    }

    /** @return array<string, array{mixed, float}> */
    public static function fees(): array
    {
        return [
            'free text'      => ['Free', 0.0],
            'currency'       => ['RM10', 10.0],
            'decimals'       => ['10.505', 10.51],
            'negative sign'  => ['-5', 5.0],
            'empty'          => ['', 0.0],
        ];
    }

    #[DataProvider('fees')]
    public function testParseFee(mixed $input, float $expected): void
    {
        $this->assertSame($expected, parse_fee($input));
    }

    public function testValidDate(): void
    {
        $this->assertTrue(valid_date('2026-02-28'));
        $this->assertTrue(valid_date('2028-02-29'));
        $this->assertFalse(valid_date('2026-02-29'));
        $this->assertFalse(valid_date('2026-13-01'));
        $this->assertFalse(valid_date('26-1-1'));
        $this->assertFalse(valid_date(null));
    }

    public function testValidTime(): void
    {
        $this->assertTrue(valid_time('09:30'));
        $this->assertTrue(valid_time('23:59:59'));
        $this->assertFalse(valid_time('24:00'));
        $this->assertFalse(valid_time('9:30'));
        $this->assertFalse(valid_time('12:60'));
        $this->assertFalse(valid_time(''));
    }

    public function testInitials(): void
    {
        $this->assertSame('NA', initials('nur aisyah binti ali'));
        $this->assertSame('Ś', initials('śmigły'));
        $this->assertSame('?', initials('   '));
        $this->assertSame('?', initials(null));
    }

    public function testStatusBadgeEscapesUnknownValues(): void
    {
        $this->assertSame('<span class="badge badge-success">Approved</span>', status_badge('Approved'));
        $this->assertStringContainsString('&lt;b&gt;', status_badge('<b>'));
        $this->assertStringContainsString('Unknown', status_badge(null));
    }

    public function testDatestubMarksPastDates(): void
    {
        $this->assertStringContainsString('is-past', datestub('2001-01-01'));
        $future = datestub(date('Y-m-d', strtotime('+3 days')));
        $this->assertStringNotContainsString('is-past', $future);
        $this->assertStringContainsString('TBA', datestub(null));
    }

    public function testPaginateClampsTheRequestedPage(): void
    {
        $_GET['page'] = '99';
        $p = paginate(42, 10);
        $this->assertSame(['page' => 5, 'per_page' => 10, 'total' => 42, 'pages' => 5, 'offset' => 40], $p);

        $_GET['page'] = '-3';
        $this->assertSame(1, paginate(42, 10)['page']);

        $_GET = [];
        $this->assertSame(1, paginate(0)['pages']);
    }

    public function testRenderPaginationShowsRangeAndCurrentPage(): void
    {
        $_GET['page'] = '2';
        $_SERVER['REQUEST_URI'] = '/myevent/browse_events.php?page=2&q=ai';
        $_GET['q'] = 'ai';

        $html = render_pagination(paginate(25, 10), 'events');

        $this->assertStringContainsString('Showing 11–20 of 25 events', $html);
        $this->assertStringContainsString('aria-current="page">2</a>', $html);
        $this->assertStringContainsString('q=ai', $html);
        $this->assertSame('', render_pagination(paginate(0)));
    }

    public function testRatingStarsRoundToHalves(): void
    {
        $html = rating_stars(3.6);

        $this->assertSame(3, substr_count($html, 'fa-solid fa-star"'));
        $this->assertSame(1, substr_count($html, 'fa-star-half-stroke'));
        $this->assertSame(1, substr_count($html, 'is-empty'));
        $this->assertStringContainsString('3.6 out of 5 stars', $html);
    }

    public function testPosterUrlFallsBackToPlaceholderAndRejectsPaths(): void
    {
        $this->assertStringEndsWith('assets/img/event-placeholder.svg', poster_url(null));
        $this->assertStringEndsWith('assets/img/event-placeholder.svg', poster_url('../config.php'));
        $this->assertStringEndsWith('assets/img/event-placeholder.svg', poster_url('missing.png'));
        $this->assertNull(avatar_url('../../.env'));
    }
}
