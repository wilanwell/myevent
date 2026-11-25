<?php
/**
 * MyEvent - Minimal mailer (no external libraries).
 *
 * MAIL_DRIVER in .env:
 *   log   Write each message to storage/mail/*.eml (development default).
 *   smtp  Send through an SMTP server with STARTTLS ("tls", port 587) or implicit TLS ("ssl", port 465)
 *         and AUTH LOGIN. Works with Gmail app passwords, Outlook, Mailtrap, SES, etc.
 */

/**
 * Send an email now. Plain text is always included; when $html is given the message is
 * multipart/alternative so every client shows something sensible.
 * Returns true on success; failures are logged (and $error filled), never shown to visitors.
 */
function send_mail(string $to, string $subject, string $text, ?string $html = null, ?string &$error = null): bool
{
    $fromEmail = env('MAIL_FROM', 'no-reply@localhost');
    $fromName  = env('MAIL_FROM_NAME', APP_NAME);
    $host      = parse_url(BASE_URL, PHP_URL_HOST) ?: 'localhost';

    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'invalid address: ' . $to;
        error_log('[mail] ' . $error);
        return false;
    }

    $headers = [
        'Date: ' . date('r'),
        'From: ' . mail_encode_header($fromName) . ' <' . $fromEmail . '>',
        'To: <' . $to . '>',
        'Subject: ' . mail_encode_header($subject),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $host . '>',
        'MIME-Version: 1.0',
        'Auto-Submitted: auto-generated',
    ];
    if ($html === null) {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: base64';
        $body = chunk_split(base64_encode($text));
    } else {
        $boundary = 'b_' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($text))
              . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($html))
              . "--$boundary--\r\n";
    }
    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;

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
            // Readable copies for development.
            file_put_contents($file . '.txt', "To: $to\nSubject: $subject\n\n$text");
            if ($html !== null) {
                file_put_contents($file . '.html', $html);
            }
        }
        return true;
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
        error_log('[mail] ' . $error);
        return false;
    }
}

/* ---------------------------------------------------------------------
   Outbox queue
   --------------------------------------------------------------------- */

/**
 * Queue an email for tools/send_mail.php. With MAIL_DRIVER=log (development) it is
 * delivered immediately instead, so there is nothing to schedule on a laptop.
 */
function queue_mail(mysqli $conn, string $to, string $subject, string $text, ?string $html = null): void
{
    if (env('MAIL_DRIVER', 'log') !== 'smtp') {
        send_mail($to, $subject, $text, $html);
        return;
    }
    $stmt = $conn->prepare('INSERT INTO mail_queue (to_email, subject, body_text, body_html) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssss', $to, $subject, $text, $html);
    $stmt->execute();
    $stmt->close();
}

const MAIL_MAX_ATTEMPTS = 5;

/**
 * Deliver due queued emails. Failed sends are retried with growing delays
 * (5, 15, 45, 135 minutes) and marked failed after MAIL_MAX_ATTEMPTS.
 * @return array{sent:int, failed:int, retry:int}
 */
function process_mail_queue(mysqli $conn, int $limit = 50): array
{
    $stats = ['sent' => 0, 'failed' => 0, 'retry' => 0];
    $due = db_all($conn,
        "SELECT mail_id, to_email, subject, body_text, body_html, attempts FROM mail_queue
         WHERE status = 'pending' AND next_attempt_at <= NOW() ORDER BY mail_id LIMIT ?", 'i', [$limit]);

    foreach ($due as $mail) {
        // Claim the row so two workers running at once never send the same email twice.
        $claim = $conn->prepare("UPDATE mail_queue SET attempts = attempts + 1, next_attempt_at = NOW() + INTERVAL 10 MINUTE
                                 WHERE mail_id = ? AND status = 'pending' AND attempts = ?");
        $claim->bind_param('ii', $mail['mail_id'], $mail['attempts']);
        $claim->execute();
        $claimed = $claim->affected_rows === 1;
        $claim->close();
        if (!$claimed) {
            continue;
        }

        $error = null;
        if (send_mail($mail['to_email'], $mail['subject'], $mail['body_text'], $mail['body_html'], $error)) {
            $stmt = $conn->prepare("UPDATE mail_queue SET status = 'sent', sent_at = NOW(), last_error = NULL WHERE mail_id = ?");
            $stmt->bind_param('i', $mail['mail_id']);
            $stats['sent']++;
        } else {
            $attempts = (int) $mail['attempts'] + 1;
            $final = $attempts >= MAIL_MAX_ATTEMPTS;
            $delay = 5 * (3 ** ($attempts - 1));
            $err = mb_substr((string) $error, 0, 500);
            $stmt = $conn->prepare('UPDATE mail_queue SET status = ?, last_error = ?, next_attempt_at = NOW() + INTERVAL ? MINUTE WHERE mail_id = ?');
            $status = $final ? 'failed' : 'pending';
            $stmt->bind_param('ssii', $status, $err, $delay, $mail['mail_id']);
            $stats[$final ? 'failed' : 'retry']++;
        }
        $stmt->execute();
        $stmt->close();
    }
    return $stats;
}

/* ---------------------------------------------------------------------
   Branded HTML template
   --------------------------------------------------------------------- */

/**
 * Build the HTML version of a notification email. Inline styles only (email clients
 * ignore stylesheets); light background; works without images.
 *
 * $facts: [label => value] rows shown in a small table (e.g. date, venue).
 */
function email_html(string $heading, string $intro, ?string $buttonLabel = null, ?string $buttonUrl = null,
                    array $facts = [], string $footnote = ''): string
{
    $rows = '';
    foreach ($facts as $label => $value) {
        $rows .= '<tr><td style="padding:6px 0;color:#5e6578;font-size:14px;width:110px;vertical-align:top">' . e($label) . '</td>'
               . '<td style="padding:6px 0;color:#10162f;font-size:14px;font-weight:600">' . e($value) . '</td></tr>';
    }
    $button = $buttonLabel && $buttonUrl
        ? '<p style="margin:28px 0 8px"><a href="' . e($buttonUrl) . '" style="display:inline-block;background:#2b44e0;color:#ffffff;'
          . 'text-decoration:none;font-weight:700;font-size:15px;padding:12px 22px;border-radius:10px">' . e($buttonLabel) . '</a></p>'
        : '';

    return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . e($heading) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f4f6fb;font-family:Segoe UI,Helvetica,Arial,sans-serif;color:#2a3044">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6fb;padding:32px 12px"><tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px">'
        . '<tr><td style="padding:0 4px 16px;font-size:18px;font-weight:800;color:#10162f">' . e(APP_NAME)
        . ' <span style="display:inline-block;width:10px;height:10px;border-radius:3px;background:#f5a524;vertical-align:1px"></span></td></tr>'
        . '<tr><td style="background:#ffffff;border:1px solid #e5e8f1;border-radius:16px;padding:32px 28px;border-left:5px solid #f5a524">'
        . '<h1 style="margin:0 0 12px;font-size:22px;line-height:1.25;color:#10162f">' . e($heading) . '</h1>'
        . '<p style="margin:0;font-size:15px;line-height:1.6">' . nl2br(e($intro)) . '</p>'
        . ($rows ? '<table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:20px;border-top:1px solid #e5e8f1;padding-top:8px;width:100%">' . $rows . '</table>' : '')
        . $button
        . '</td></tr>'
        . '<tr><td style="padding:18px 4px;font-size:12px;line-height:1.6;color:#646b80">'
        . ($footnote !== '' ? e($footnote) . '<br>' : '')
        . 'You are receiving this because you have an account on ' . e(APP_NAME) . '. '
        . '<a href="' . e(BASE_URL) . 'users/profile.php#email-settings" style="color:#2b44e0">Change email settings</a>'
        . '</td></tr></table></td></tr></table></body></html>';
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
