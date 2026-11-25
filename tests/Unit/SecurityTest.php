<?php

namespace MyEvent\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase
{
    public function testEscapesHtmlSpecialCharactersIncludingQuotes(): void
    {
        $this->assertSame('&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt; &amp; &quot;', e("<script>alert('x')</script> & \""));
    }

    public function testEscapeAcceptsNullAndNumbers(): void
    {
        $this->assertSame('', e(null));
        $this->assertSame('42', e(42));
    }

    public function testEscapeReplacesInvalidUtf8InsteadOfReturningEmpty(): void
    {
        $this->assertNotSame('', e("ok \xC3\x28"));
    }

    public function testRenderMessageRestoresOnlyStrongTags(): void
    {
        $html = render_message("Hi <strong>Ali</strong> <img src=x onerror=alert(1)>\nline two");

        $this->assertStringContainsString('<strong>Ali</strong>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img', $html);
        $this->assertStringContainsString('<br', $html);
    }

    public function testRenderMessageDoesNotRestoreStrongWithAttributes(): void
    {
        $this->assertStringNotContainsString('<strong onclick', render_message('<strong onclick="x()">hi</strong>'));
    }

    public function testJsProducesASafeStringLiteral(): void
    {
        $out = js("</script><script>alert('x')</script>");

        $this->assertStringStartsWith('"', $out);
        $this->assertStringNotContainsString('<', $out);
        $this->assertStringNotContainsString("'", $out);
        $this->assertSame("</script><script>alert('x')</script>", json_decode($out));
    }

    public function testCsrfTokenIsStableWithinASession(): void
    {
        $_SESSION = [];
        $token = csrf_token();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, csrf_token());
        $this->assertStringContainsString('value="' . $token . '"', csrf_field());
    }

    public function testCurrentUserAndRoleHelpersReadTheSession(): void
    {
        $_SESSION = [];
        $this->assertFalse(is_logged_in());
        $this->assertNull(current_user_id());

        $_SESSION = ['loggedin' => true, 'user_id' => '7', 'role' => 'User'];
        $this->assertTrue(is_logged_in());
        $this->assertFalse(is_admin());
        $this->assertSame(7, current_user_id());

        $_SESSION['role'] = 'Admin';
        $this->assertTrue(is_admin());
        $_SESSION = [];
    }

    /** @return array<string, array{string, string}> */
    public static function filenames(): array
    {
        return [
            'spaces and punctuation' => ['Tech Talk: AI & You!', 'Tech_Talk_AI_You.csv'],
            'header injection'       => ["a\"b\r\nSet-Cookie: x", 'a_b_Set-Cookie_x.csv'],
            'path traversal'         => ['../../etc/passwd', 'etc_passwd.csv'],
            'nothing usable'         => ['!!!', 'export.csv'],
        ];
    }

    #[DataProvider('filenames')]
    public function testSafeFilename(string $input, string $expected): void
    {
        $this->assertSame($expected, safe_filename($input));
    }

    public function testSafeFilenameIsCappedAndKeepsExtension(): void
    {
        $name = safe_filename(str_repeat('a', 200), 'ics');

        $this->assertSame(84, strlen($name));
        $this->assertStringEndsWith('.ics', $name);
    }

    public function testCsvRowNeutralisesFormulaInjection(): void
    {
        $this->assertSame(
            ["'=HYPERLINK(\"x\")", "'+1", "'-2", "'@SUM(A1)", 'Ali', '', '3'],
            csv_row(['=HYPERLINK("x")', '+1', '-2', '@SUM(A1)', 'Ali', null, 3])
        );
    }
}
