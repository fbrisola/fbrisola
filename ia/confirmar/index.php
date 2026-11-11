<?php
require_once dirname(__DIR__, 2) . '/ia-newsletter.php';
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
$confirmed = false;
$pending = false;
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$token = $isPost ? ($_POST['t'] ?? '') : ($_GET['t'] ?? '');
$message = 'Este link é inválido ou expirou. Volte ao IA no Fone para pedir um novo e-mail de confirmação.';
try {
    $dir = ia_data_dir();
    $data = ia_validate($token, ia_secret($dir));
    if ($data !== null) {
        if ($isPost) {
            ia_confirm_once($dir, $data);
            $confirmed = true;
        } else {
            $pending = true;
        }
    } else {
        http_response_code(400);
    }
} catch (Throwable $error) {
    error_log('IA newsletter: subscriber confirmation failed (' . get_class($error) . ')');
    http_response_code(503);
    $message = 'Não foi possível concluir a confirmação agora. Tente abrir este link novamente em instantes.';
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Confirmação | IA no Fone</title><meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=JetBrains+Mono:wght@400;500;700&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"><style>:root {
  --bg:#080807; --surface:#121211; --surface2:#1b1b19; --surface3:#232321;
  --border:#2f2f2b; --border2:#45453f; --accent:#c8f060; --accent2:#f0a040;
  --blue:#3b82f6; --pink:#f04f9a; --green:#22c55e; --danger:#ff6259;
  --text:#efefe7; --muted:#9a9a90; --soft:#c8c8bc; --black:#080807;
}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--bg);color:var(--text);font:17px/1.65 'Instrument Sans',sans-serif}a{color:var(--accent);text-underline-offset:4px}a:hover{color:var(--text)}:focus-visible{outline:3px solid var(--accent);outline-offset:5px}[hidden]{display:none!important}.wrap{max-width:1160px;margin:auto;padding:0 32px}header{border-bottom:1px solid var(--border)}nav,footer{display:flex;justify-content:space-between;align-items:center;gap:24px;padding:24px 0}.brand,.eyebrow,.step,.button{font-family:'JetBrains Mono',monospace}.brand{text-decoration:none;font-size:13px}.eyebrow,.step{color:var(--accent);font-size:12px;letter-spacing:.1em;text-transform:uppercase}.hero{display:grid;grid-template-columns:1.35fr 1fr;gap:64px;align-items:center;padding:86px 0}h1,h2,h3{font-family:'DM Serif Display',serif;font-weight:400;line-height:1.1}h1{font-size:clamp(44px,6vw,76px);letter-spacing:-.03em;margin:20px 0}h2{font-size:clamp(32px,4vw,48px);margin:12px 0 28px}h3{font-size:26px;margin:14px 0}em{color:var(--accent)}p{color:var(--soft)}.intro{font-size:20px}.button{display:inline-flex;justify-content:center;align-items:center;background:var(--accent);color:var(--black);border:0;padding:16px 22px;font-size:13px;font-weight:700;text-decoration:none;cursor:pointer}.button:hover{background:#d7fa7a;color:var(--black)}.button:disabled{opacity:.65;cursor:wait}.actions{display:flex;gap:24px;align-items:center;flex-wrap:wrap;margin-top:28px}.panel,.card{background:var(--surface);border:1px solid var(--border);padding:28px}.panel{border-top:3px solid var(--accent)}.panel ul{padding-left:20px}.panel li{padding:12px 0;border-bottom:1px solid var(--border);color:var(--soft)}.panel li:last-child{border:0}.small{font-size:14px;color:var(--muted)}section{padding:64px 0;border-top:1px solid var(--border)}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}.card p{margin-bottom:0}details{border-bottom:1px solid var(--border);padding:22px 0}summary{cursor:pointer;font-size:19px}details p{max-width:800px}.contact{display:grid;grid-template-columns:1fr 1.15fr;gap:64px}.fields{display:grid;grid-template-columns:1fr 1fr;gap:18px}.field{margin-bottom:18px}label{display:block;font-size:14px;margin-bottom:7px}input,textarea{width:100%;padding:13px;background:var(--bg);border:1px solid var(--border2);color:var(--text);font:inherit;border-radius:0}textarea{resize:vertical}.check{display:flex;gap:12px;align-items:flex-start;font-size:14px}.check input{width:18px;height:18px;flex-shrink:0;margin-top:4px;accent-color:var(--accent)}.trap{position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden}.error{color:#ffaaa4}.success{border-left:3px solid var(--accent);padding:24px;background:var(--surface)}footer{border-top:1px solid var(--border);font-size:13px}.skip{position:absolute;top:-100px}.skip:focus{top:10px;left:10px;background:var(--bg);padding:10px;z-index:2}.prose{max-width:800px;padding:60px 0}.prose h2{font-size:30px;margin-top:40px}@media(max-width:760px){.wrap{padding:0 20px}.hero,.contact{grid-template-columns:1fr;gap:32px}.hero{padding:48px 0}.grid,.fields{grid-template-columns:1fr}section{padding:44px 0}nav,footer{flex-wrap:wrap}.panel,.card{padding:22px}}@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}}
</style></head><body><a class="skip" href="#conteudo">Pular para o conteúdo</a><div class="wrap"><header><nav aria-label="Principal"><a class="brand" href="/">FERNANDO BRISOLA</a><a href="/ia/">IA no Fone →</a></nav></header><main id="conteudo" class="prose"><p class="eyebrow">IA no Fone / Newsletter</p>
<?php if ($confirmed): ?>
<h1>E-mail confirmado!</h1><p>Seu guia está disponível. A newsletter semanal começa em breve.</p><p><a class="button" href="/materiais/guia-chatbot-ou-agente.pdf" download>Baixar o guia (PDF)</a></p>
<?php elseif ($pending): ?>
<h1>Confirme seu e-mail</h1><p>Clique no botão para confirmar seu cadastro e liberar o guia.</p>
<form method="post" action="/ia/confirmar/">
<input type="hidden" name="t" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
<button class="button" type="submit">Confirmar meu e-mail</button>
</form>
<?php else: ?>
<h1>Confirmação não concluída</h1><p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p><p><a class="button" href="/ia/">Voltar ao IA no Fone</a></p>
<?php endif; ?>
</main><footer><span>Fernando Brisola · IA aplicada ao trabalho</span><a href="/privacidade/">Privacidade</a></footer></div></body></html>
