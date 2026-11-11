<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/ia-newsletter.php';
function check($condition, $label) {
    if (!$condition) { throw new RuntimeException($label); }
    echo "OK: $label\n";
}
$dir = sys_get_temp_dir() . '/ia-newsletter-test-' . bin2hex(random_bytes(8));
mkdir($dir, 0700);
try {
    $secret = ia_secret($dir);
    check(strlen($secret) === 32 && ia_secret($dir) === $secret, 'persistent random secret');
    check((fileperms($dir . '/secret.key') & 0777) === 0600, 'secret permissions');
    $now = time();
    $token = ia_token('teste@example.com', 'Pessoa Teste', $secret, $now);
    $data = ia_validate($token, $secret, $now);
    check($data['e'] === 'teste@example.com' && $data['n'] === 'Pessoa Teste', 'valid token');
    check(ia_validate($token, $secret, $now + 72 * 3600 - 1) !== null, 'valid before expiry');
    check(ia_validate($token, $secret, $now + 72 * 3600) === null, 'expired token');
    check(ia_validate('x' . $token, $secret, $now) === null, 'tampered payload');
    check(ia_validate($token, random_bytes(32), $now) === null, 'wrong signature');
    check(ia_validate($token, $secret, $now - 1) === null, 'future token');
    foreach (['', 'broken', [], str_repeat('a', 9000)] as $bad) {
        check(ia_validate($bad, $secret) === null, 'malformed token rejected');
    }
    check(ia_allow($dir, 'teste@example.com', '127.0.0.1', $now), 'first request allowed');
    check(!ia_allow($dir, 'TESTE@example.com', '127.0.0.2', $now + 599), 'email cooldown across IPs');
    for ($i = 1; $i <= 4; $i++) {
        check(ia_allow($dir, "test$i@example.com", '127.0.0.1', $now), 'IP within quota');
    }
    check(!ia_allow($dir, 'six@example.com', '127.0.0.1', $now), 'sixth IP request blocked');
    check(ia_allow($dir, 'teste@example.com', '127.0.0.2', $now + 600), 'email cooldown ends');
    check(ia_allow($dir, 'six@example.com', '127.0.0.1', $now + 3600), 'IP window ends');
    putenv('IA_NEWSLETTER_TEST_MAIL=' . $dir . '/mail.jsonl');
    ia_confirm_once($dir, $data);
    ia_confirm_once($dir, $data);
    $data['e'] = 'TESTE@example.com';
    ia_confirm_once($dir, $data);
    $lines = file($dir . '/mail.jsonl');
    check(count($lines) === 1, 'confirmation notification idempotent by email');
    $mail = json_decode($lines[0], true);
    check($mail['to'] === 'ia@fbrisola.com.br' && $mail['subject'] === '[Newsletter confirmada] teste@example.com', 'exact notification recipient and subject');
    check(preg_match('/^email: teste@example.com\nnome: Pessoa Teste\nconfirmado_em_utc: [^\n]+\norigem: ia\n$/D', $mail['body']) === 1, 'notification protocol');
    // A known mail rejection must allow a later retry.
    putenv('IA_NEWSLETTER_TEST_MAIL=' . $dir . '/missing/mail.jsonl');
    $data['e'] = 'retry@example.com';
    try { @ia_confirm_once($dir, $data); throw new LogicException('Expected delivery failure'); }
    catch (RuntimeException $error) { check(!is_file($dir . '/confirmed-' . hash('sha256', $data['e'])), 'failed mail can retry'); }
    putenv('IA_NEWSLETTER_TEST_MAIL=' . $dir . '/mail.jsonl');
    ia_confirm_once($dir, $data);
    check(count(file($dir . '/mail.jsonl')) === 2, 'retry delivered once');
} finally {
    putenv('IA_NEWSLETTER_TEST_MAIL');
    foreach (glob($dir . '/*') as $file) { unlink($file); }
    unlink($dir . '/.lock');
    rmdir($dir);
}
