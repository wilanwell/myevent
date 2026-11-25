<?php

namespace MyEvent\Tests\Support;

/**
 * Reads the emails that the "log" mail driver wrote to MAIL_LOG_DIR during a test,
 * so tests can assert who was emailed and what they were told.
 */
final class MailSpy
{
    public static function dir(): string
    {
        return (string) getenv('MAIL_LOG_DIR');
    }

    public static function clear(): void
    {
        foreach (glob(self::dir() . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /** @return list<array{to:string, subject:string, text:string, html:?string, raw:string}> */
    public static function messages(): array
    {
        $out = [];
        $files = glob(self::dir() . '/*.eml') ?: [];
        sort($files);
        foreach ($files as $eml) {
            $txt = (string) file_get_contents($eml . '.txt');
            [$head, $text] = array_pad(explode("\n\n", $txt, 2), 2, '');
            preg_match('/^To: (.*)$/m', $head, $to);
            preg_match('/^Subject: (.*)$/m', $head, $subject);
            $out[] = [
                'to'      => $to[1] ?? '',
                'subject' => $subject[1] ?? '',
                'text'    => $text,
                'html'    => is_file($eml . '.html') ? (string) file_get_contents($eml . '.html') : null,
                'raw'     => (string) file_get_contents($eml),
            ];
        }
        return $out;
    }

    /** @return list<string> */
    public static function recipients(): array
    {
        return array_column(self::messages(), 'to');
    }
}
