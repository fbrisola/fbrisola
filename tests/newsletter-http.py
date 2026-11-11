"""Local HTTP smoke test. PHP with mbstring required; no real email is sent."""
import base64
import email
import email.header
import hashlib
import hmac
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

repo = Path(__file__).resolve().parents[1]
php = os.environ.get('PHP_BIN', 'php')
with tempfile.TemporaryDirectory(prefix='ia-http-') as tmp:
    tmp = Path(tmp)
    root = tmp / 'public'
    root.mkdir()
    for name in ['send_mail.php', 'ia-newsletter.php']:
        shutil.copy(repo / name, root / name)
    shutil.copytree(repo / 'ia', root / 'ia')
    capture = tmp / 'mail.jsonl'
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    base = f'http://127.0.0.1:{port}'
    env = dict(os.environ, IA_NEWSLETTER_TEST_MAIL=str(capture))
    with (tmp / 'server.log').open('w') as log:
        server = subprocess.Popen([php, '-S', f'127.0.0.1:{port}', '-t', str(root)], env=env, stdout=log, stderr=log)
        def request(path, fields=None):
            body = urllib.parse.urlencode(fields).encode() if fields is not None else None
            try:
                with urllib.request.urlopen(base + path, data=body) as result:
                    return result.status, result.read().decode()
            except urllib.error.HTTPError as error:
                return error.code, error.read().decode()
        def mails():
            return [json.loads(line) for line in capture.read_text().splitlines()]
        try:
            for _ in range(100):
                try:
                    request('/ia/')
                    break
                except urllib.error.URLError:
                    time.sleep(.05)
            else:
                raise RuntimeError('PHP server did not start')
            fields = dict(name='Pessoa Teste', email='teste@example.com', message='Quero o guia e a newsletter', origem='ia', consent='1')
            status, body = request('/send_mail.php', fields)
            assert status == 200 and json.loads(body)['success'] is True
            messages = mails()
            assert len(messages) == 2
            subject = email.header.decode_header(messages[0]['subject'])[0][0]
            assert subject.decode().startswith('[Lead IA no Fone]')
            assert messages[0]['to'] == 'ia@fbrisola.com.br'
            confirmation = messages[1]
            assert confirmation['to'] == fields['email']
            assert confirmation['subject'] == 'Confirme seu e-mail para receber o guia do IA no Fone'
            mime = email.message_from_string(confirmation['headers'] + '\r\n\r\n' + confirmation['body'])
            parts = mime.get_payload()
            assert [p.get_content_type() for p in parts] == ['text/plain', 'text/html']
            text = parts[0].get_payload(decode=True).decode()
            token = text.split('?t=', 1)[1].split()[0]
            assert 'guia-chatbot-ou-agente.pdf' not in request('/ia/')[1]
            assert 'checkMaterial' not in request('/ia/')[1]
            path = '/ia/confirmar/?t=' + token
            for _ in range(2):
                status, page = request(path)
                assert status == 200 and 'E-mail confirmado!' in page
                assert '/materiais/guia-chatbot-ou-agente.pdf' in page
                assert token not in page
            assert len(mails()) == 3
            assert mails()[2]['subject'] == '[Newsletter confirmada] teste@example.com'
            for invalid in ['x' + token, '']:
                status, page = request('/ia/confirmar/?t=' + invalid)
                assert status == 400 and 'Baixar o guia' not in page
            payload = base64.urlsafe_b64encode(json.dumps(dict(e=fields['email'], n=fields['name'], ts=int(time.time())-72*3600)).encode()).decode().rstrip('=')
            secret = (tmp / 'ia-newsletter-data/secret.key').read_bytes()
            expired = payload + '.' + hmac.new(secret, payload.encode(), hashlib.sha256).hexdigest()
            status, page = request('/ia/confirmar/?t=' + expired)
            assert status == 400 and 'Baixar o guia' not in page
            # Cooldown suppresses only confirmation; the lead continues arriving.
            request('/send_mail.php', fields)
            assert len(mails()) == 4
            for i in range(1, 6):
                request('/send_mail.php', dict(fields, email=f'test{i}@example.com'))
            assert len(mails()) == 13  # five leads, only four confirmations
            count = len(mails())
            assert request('/send_mail.php', dict(fields, honeypot='bot'))[0] == 400
            assert request('/send_mail.php', dict(fields, consent='0'))[0] == 422
            assert len(mails()) == count
            # Storage failure must not break the successfully delivered lead.
            (tmp / 'ia-newsletter-data/secret.key').write_bytes(b'broken')
            status, body = request('/send_mail.php', dict(fields, email='failure@example.com'))
            assert status == 200 and json.loads(body)['success'] is True
            assert len(mails()) == count + 1
            print('OK: HTTP form, MIME, lead preservation, valid/tampered/expired tokens, idempotency, limits, honeypot, consent and failure isolation; no real mail')
        finally:
            server.terminate()
            server.wait(timeout=5)
