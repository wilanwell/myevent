<?php

namespace MyEvent\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../tools/i18n.php';

final class TranslationTest extends TestCase
{
    private static ?array $keys = null;

    private static function keys(): array
    {
        return self::$keys ??= i18n_scan(dirname(__DIR__, 2));
    }

    protected function tearDown(): void
    {
        set_locale('en');
        $_SESSION = [];
        unset($_COOKIE[LOCALE_COOKIE], $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    public function testEveryWrappedStringHasAMalayTranslation(): void
    {
        $missing = array_diff_key(self::keys(), translations('ms'));
        $this->assertSame([], array_map(static fn($where) => $where[0], $missing),
            'Add these to lang/ms.php (php tools/i18n.php --missing lists them).');
    }

    public function testNoStaleTranslations(): void
    {
        $this->assertSame([], array_keys(array_diff_key(translations('ms'), self::keys())),
            'These translations are no longer used by the code: remove them from lang/ms.php.');
    }

    public function testTranslationsKeepTheirPlaceholdersAndAreNotEmpty(): void
    {
        foreach (translations('ms') as $en => $ms) {
            $this->assertNotSame('', trim($ms), "empty translation for: $en");
            preg_match_all('/\{(\w+)\}/', $en, $a);
            preg_match_all('/\{(\w+)\}/', $ms, $b);
            sort($a[1]);
            sort($b[1]);
            $this->assertSame($a[1], $b[1], "placeholders differ for: $en");
        }
    }

    public function testTranslateWithPlaceholders(): void
    {
        $this->assertSame('Welcome back, Aisyah', __('Welcome back, {name}', ['name' => 'Aisyah']));
        set_locale('ms');
        $this->assertSame('Selamat kembali, Aisyah', __('Welcome back, {name}', ['name' => 'Aisyah']));
        $this->assertSame('Text that has no translation', __('Text that has no translation'), 'falls back to English');
    }

    public function testEscapingHelpers(): void
    {
        set_locale('ms');
        $this->assertSame('Selamat kembali, &lt;b&gt;', t('Welcome back, {name}', ['name' => '<b>']));
        $this->assertSame('Dianjurkan oleh <strong>Ali</strong>', th('Organised by {name}', ['name' => '<strong>Ali</strong>']));
    }

    public function testPlurals(): void
    {
        $this->assertSame('1 place left', _n('{n} place left', '{n} places left', 1));
        $this->assertSame('3 places left', _n('{n} place left', '{n} places left', 3));
        set_locale('ms');
        $this->assertSame('Tinggal 3 tempat', _n('{n} place left', '{n} places left', 3));
    }

    public function testMalayDates(): void
    {
        $ts = strtotime('2026-03-02 21:05:00'); // a Monday in March
        $this->assertSame('Monday, 2 March 2026, 9:05 PM', ldate('l, j F Y, g:i A', $ts));
        set_locale('ms');
        $this->assertSame('Isnin, 2 Mac 2026, 9:05 PTG', ldate('l, j F Y, g:i A', $ts));
        $this->assertSame('Isn, 2 Mac', ldate('D, j M', $ts));
        $this->assertSame('Mei 2026', ldate('M Y', '2026-05-10'));
        $this->assertSame('Ogo 2026', ldate('M Y', '2026-08-10'));
        $this->assertSame('Ahad', ldate('l', '2026-11-29'));
    }

    /** @return array<string, array{string, ?string}> */
    public static function acceptLanguages(): array
    {
        return [
            'malay first'       => ['ms-MY,ms;q=0.9,en;q=0.8', 'ms'],
            'english first'     => ['en-GB,en;q=0.9,ms;q=0.5', 'en'],
            'q values decide'   => ['en;q=0.4, ms;q=0.8', 'ms'],
            'unsupported only'  => ['fr-FR,fr;q=0.9', null],
            'unsupported first' => ['zh-CN,ms;q=0.5', 'ms'],
            'refused language'  => ['ms;q=0, en;q=0.1', 'en'],
            'empty'             => ['', null],
        ];
    }

    #[DataProvider('acceptLanguages')]
    public function testAcceptLanguage(string $header, ?string $expected): void
    {
        $this->assertSame($expected, locale_from_accept_language($header));
    }

    public function testDetectionOrder(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'ms';
        $this->assertSame('ms', detect_locale(), 'browser');

        $_COOKIE[LOCALE_COOKIE] = 'en';
        $this->assertSame('en', detect_locale(), 'cookie beats browser');

        $_SESSION['locale'] = 'ms';
        $this->assertSame('ms', detect_locale(), 'session beats cookie');

        $this->assertSame('en', detect_locale('en'), 'the account beats everything');

        $_SESSION['locale'] = 'xx';
        $_COOKIE[LOCALE_COOKIE] = '../../etc';
        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $this->assertSame('en', detect_locale(), 'junk is ignored, default applies');
    }

    public function testWithLocaleRestoresTheLanguageEvenAfterAnError(): void
    {
        $this->assertSame('Papan pemuka', with_locale('ms', fn() => __('Dashboard')));
        $this->assertSame('en', current_locale());

        try {
            with_locale('ms', static function () {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame('en', current_locale());
    }

    public function testStatusLabelsAndFees(): void
    {
        set_locale('ms');
        $this->assertStringContainsString('>Diluluskan<', status_badge('Approved'));
        $this->assertStringContainsString('badge-success', status_badge('Approved'), 'colour still follows the stored value');
        $this->assertSame('Dalam talian', status_label('Online'));
        $this->assertSame('Percuma', format_fee(0));
        $this->assertSame('Something custom', status_label('Something custom'));
    }
}
