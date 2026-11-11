# Newsletter IA no Fone

Requires PHP 7.3+ with mbstring (already used by send_mail.php), and Python 3 for the HTTP smoke test.

```sh
find . -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/newsletter.php
python3 tests/newsletter-http.py
```

The HTTP test starts `php -S` on loopback in a temporary document root, submits the form, reads the captured multipart email, follows the confirmation link twice, and checks invalid/expired tokens and abuse limits. All runtime data is removed afterwards. `PHP_BIN` can select the PHP executable.

For a manual local test, copy the site to a temporary `public/` directory and run:

```sh
IA_NEWSLETTER_TEST_MAIL=/tmp/ia-mail.jsonl php -S 127.0.0.1:8080 -t /tmp/ia-manual/public
```

Open `/ia/`, submit, decode the plain-text MIME part in `/tmp/ia-mail.jsonl`, and open its `/ia/confirmar/?t=...` path on localhost. Reload: only one `[Newsletter confirmada]` record should exist. Never run this test against production. The capture switch works only in CLI/CLI-server and cannot be enabled via HTTP input.

The confirmed marker is persisted before calling the mail transport, under an exclusive lock. A known `mail()` rejection removes it for retry. A process crash between reservation and acceptance can leave a marker without a delivered notification; this favors avoiding duplicate notifications. PHP `mail()` acceptance does not guarantee inbox delivery. Runtime data must survive deployment and backups; never delete `secret.key` to rotate deployments (that invalidates pending links).
