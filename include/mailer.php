<?php
/**
 * MyEvent - Minimal mailer (no external libraries).
 *
 * MAIL_DRIVER in .env:
 *   log   Write each message to storage/mail/*.eml (development default).
 *   smtp  Send through an SMTP server with STARTTLS ("tls", port 587) or implicit TLS ("ssl", port 465)
 *         and AUTH LOGIN. Works with Gmail app passwords, Outlook, Mailtrap, SES, etc.
 */

/** Send a plain-text email. Returns true on success; failures are logged, never shown to the user. */
function send_mail(string $to, string $subject, string $body): bool
{
    $fromEmail = env('MAIL_FROM', 'no-reply@localhost');
    $fromName  = env('MAIL_FROM_NAME', APP_NAME);

    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        error_log('[mail] invalid address: ' . $to);
        return false;
    }

    $headers = [
        'Date: ' . date('r'),
        'From: ' . mail_encode_header($fromName) . ' <' . $fromEmail . '>',
        'To: <' . $to . '>',
        'Subject: ' . mail_encode_header($subject),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (parse_url(BASE_URL, PHP_URL_HOST) ?: 'localhost') . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    $message = implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body));

    try {
        if (env('MAIL_DRIVER', 'log') === 'smtp') {
            smtp_send($fromEmail, $to, $message);
        } else {
            $dir = ROOT_PATH . '/storage/mail';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $file = $dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.eml';
            file_put_contents($file, $message);
            // Plain copy for quick reading during development.
            file_put_contents($file . '.txt', "To: $to\nSubject: $subject\n\n$body");
        }
        return true;
    } catch (Throwable $ex) {
        error_log('[mail] ' . $ex->getMessage());
        return false;
    }
}

function mail_encode_header(string $value): string
{
    $value = str_replace(["\r", "\n"], '', $value); // header injection guard
    return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
}

/** Deliver a pre-built RFC 5322 message over SMTP. Throws RuntimeException on failure. */
function smtp_send(string $from, string $to, string $message): void
{
    $host = env('SMTP_HOST', '');
    $port = (int) env('SMTP_PORT', 587);
    $enc  = strtolower((string) env('SMTP_ENCRYPTION', 'tls'));
    if ($host === '') {
        throw new RuntimeException('SMTP_HOST is not configured');
    }

    $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new RuntimeException("SMTP connect failed: $errstr ($errno)");
    }
    stream_set_timeout($fp, 15);

    $read = static function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = static function (?string $command, array $expect) use ($fp, $read): string {
        if ($command !== null) {
            fwrite($fp, $command . "\r\n");
        }
        $reply = $read();
        if (!in_array((int) substr($reply, 0, 3), $expect, true)) {
            $shown = $command !== null && stripos($command, 'AUTH') !== 0 && strlen($command) < 100 ? $command : '[command]';
            throw new RuntimeException('SMTP error after ' . $shown . ': ' . trim($reply));
        }
        return $reply;
    };

    $ehloHost = parse_url(BASE_URL, PHP_URL_HOST) ?: 'localhost';
    $cmd(null, [220]);
    $cmd('EHLO ' . $ehloHost, [250]);
    if ($enc === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
            throw new RuntimeException('STARTTLS negotiation failed');
        }
        $cmd('EHLO ' . $ehloHost, [250]);
    }
    $user = (string) env('SMTP_USERNAME', '');
    if ($user !== '') {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode($user), [334]);
        $cmd(base64_encode((string) env('SMTP_PASSWORD', '')), [235]);
    }
    $cmd('MAIL FROM:<' . $from . '>', [250]);
    $cmd('RCPT TO:<' . $to . '>', [250, 251]);
    $cmd('DATA', [354]);
    // Dot-stuffing (RFC 5321 4.5.2)
    $cmd(preg_replace('/^\./m', '..', $message) . "\r\n.", [250]);
    $cmd('QUIT', [221]);
    fclose($fp);
}
