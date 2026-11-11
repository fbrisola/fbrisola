<?php
// Runtime data only: no credentials or subscriber records belong in the repository.
function ia_data_dir()
{
    $root = __DIR__;
    $outside = dirname($root) . '/ia-newsletter-data';
    $dir = is_dir($outside) || is_writable(dirname($root)) ? $outside : $root . '/ia-newsletter-data';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create newsletter storage');
    }
    if ($dir === $root . '/ia-newsletter-data') {
        $deny = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
        if (file_put_contents($dir . '/.htaccess', $deny, LOCK_EX) === false) {
            throw new RuntimeException('Cannot protect newsletter storage');
        }
    }
    return $dir;
}

function ia_write($path, $value)
{
    $tmp = tempnam(dirname($path), '.write-');
    if ($tmp === false) {
        throw new RuntimeException('Cannot create newsletter file');
    }
    try {
        if (!chmod($tmp, 0600) || file_put_contents($tmp, $value) !== strlen($value) || !rename($tmp, $path)) {
            throw new RuntimeException('Cannot persist newsletter data');
        }
    } finally {
        if (is_file($tmp)) { unlink($tmp); }
    }
}

function ia_locked($dir, $callback)
{
    $lock = fopen($dir . '/.lock', 'c');
    if (!$lock) { throw new RuntimeException('Cannot open newsletter lock'); }
    try {
        if (!chmod($dir . '/.lock', 0600) || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('Cannot lock newsletter storage');
        }
        return $callback();
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function ia_secret($dir)
{
    return ia_locked($dir, function () use ($dir) {
        $path = $dir . '/secret.key';
        if (!file_exists($path)) { ia_write($path, random_bytes(32)); }
        $secret = file_get_contents($path);
        if ($secret === false || strlen($secret) !== 32 || !chmod($path, 0600)) {
            throw new RuntimeException('Invalid newsletter secret storage');
        }
        return $secret;
    });
}

function ia_token($email, $name, $secret, $now = null)
{
    $json = json_encode(['e' => $email, 'n' => $name, 'ts' => $now ?? time()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $payload = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    return $payload . '.' . hash_hmac('sha256', $payload, $secret);
}

function ia_validate($token, $secret, $now = null)
{
    if (!is_string($token) || strlen($token) > 8192 || !preg_match('/^([A-Za-z0-9_-]+)\.([a-f0-9]{64})$/D', $token, $parts)) { return null; }
    if (!hash_equals(hash_hmac('sha256', $parts[1], $secret), $parts[2])) { return null; }
    $data = json_decode(base64_decode(strtr($parts[1], '-_', '+/'), true), true);
    $now = $now ?? time();
    if (!is_array($data) || !isset($data['e'], $data['n'], $data['ts']) || !is_string($data['e']) || !filter_var($data['e'], FILTER_VALIDATE_EMAIL)
        || !is_string($data['n']) || !is_int($data['ts']) || $data['ts'] > $now || $now - $data['ts'] >= 72 * 3600) { return null; }
    return $data;
}

function ia_allow($dir, $email, $ip, $now = null)
{
    $now = $now ?? time();
    return ia_locked($dir, function () use ($dir, $email, $ip, $now) {
        foreach (glob($dir . '/rate-*.json') as $old) {
            if (filemtime($old) < $now - 3600) { unlink($old); }
        }
        $rules = [ ['email:' . strtolower($email), 600, 1], ['ip:' . $ip, 3600, 5] ];
        $updates = [];
        foreach ($rules as $rule) {
            $path = $dir . '/rate-' . hash('sha256', $rule[0]) . '.json';
            $times = is_file($path) ? json_decode(file_get_contents($path), true) : [];
            if (!is_array($times)) { throw new RuntimeException('Invalid newsletter rate data'); }
            $times = array_values(array_filter($times, function ($ts) use ($now, $rule) { return $ts > $now - $rule[1]; }));
            if (count($times) >= $rule[2]) { return false; }
            $times[] = $now;
            $updates[$path] = $times;
        }
        foreach ($updates as $path => $times) { ia_write($path, json_encode($times)); }
        return true;
    });
}

function ia_mail($to, $subject, $body, $headers)
{
    // Explicit local-only test switch; never controlled by request parameters.
    $capture = getenv('IA_NEWSLETTER_TEST_MAIL');
    if ($capture && in_array(PHP_SAPI, ['cli', 'cli-server'], true)) {
        return file_put_contents($capture, json_encode(compact('to', 'subject', 'body', 'headers')) . "\n", FILE_APPEND | LOCK_EX) !== false;
    }
    return mail($to, $subject, $body, $headers);
}

function ia_send_confirmation($email, $name, $ip)
{
    $dir = ia_data_dir();
    $secret = ia_secret($dir);
    if (!ia_allow($dir, $email, $ip)) { return; }
    $url = 'https://fbrisola.com.br/ia/confirmar/?t=' . ia_token($email, $name, $secret);
    $text = "Confirme seu e-mail para receber o guia do IA no Fone e a newsletter semanal.\n\nConfirmar meu e-mail: " . $url . "\n\nO link vale por 72 horas. Se você não pediu este cadastro, pode ignorar este e-mail.";
    $html = '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><body><h1>Confirme seu e-mail</h1><p>Confirme para receber o guia do IA no Fone e a newsletter semanal.</p><p><a style="display:inline-block;padding:16px;background:#c8f060;color:#080807" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">Confirmar meu e-mail</a></p><p>O link vale por 72 horas. Se você não pediu este cadastro, pode ignorar este e-mail.</p></body></html>';
    $boundary = 'ia-' . bin2hex(random_bytes(16));
    $headers = "From: IA no Fone <ia@fbrisola.com.br>\r\nReply-To: ia@fbrisola.com.br\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$boundary\"";
    $body = '';
    foreach (['plain' => $text, 'html' => $html] as $type => $content) {
        $body .= "--$boundary\r\nContent-Type: text/$type; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($content), 76, "\r\n");
    }
    $body .= "--$boundary--\r\n";
    if (!ia_mail($email, 'Confirme seu e-mail para receber o guia do IA no Fone', $body, $headers)) {
        throw new RuntimeException('Confirmation mail failed');
    }
}

function ia_confirm_once($dir, $data)
{
    return ia_locked($dir, function () use ($dir, $data) {
        $path = $dir . '/confirmed-' . hash('sha256', strtolower($data['e']));
        if (is_file($path)) { return; }
        $when = gmdate('Y-m-d\TH:i:s\Z');
        $name = preg_replace('/[\r\n]+/', ' ', html_entity_decode($data['n'], ENT_QUOTES, 'UTF-8'));
        $body = "email: {$data['e']}\nnome: $name\nconfirmado_em_utc: $when\norigem: ia\n";
        // Reserve before handing mail to the MTA: a crash must not cause duplicates.
        ia_write($path, $when);
        if (!ia_mail('ia@fbrisola.com.br', '[Newsletter confirmada] ' . $data['e'], $body,
            "From: ia@fbrisola.com.br\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8")) {
            unlink($path); // A known rejection can safely be retried.
            throw new RuntimeException('Confirmed subscriber mail failed');
        }
    });
}
