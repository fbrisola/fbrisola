<?php
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Honeypot anti-spam
if (!empty($_POST['honeypot'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Spam detectado']);
    exit;
}

$nome      = trim(filter_input(INPUT_POST, 'name',      FILTER_SANITIZE_SPECIAL_CHARS));
$email     = trim(filter_input(INPUT_POST, 'email',     FILTER_SANITIZE_EMAIL));
$empresa   = trim(filter_input(INPUT_POST, 'empresa',   FILTER_SANITIZE_SPECIAL_CHARS));
$wpp       = trim(filter_input(INPUT_POST, 'wpp',       FILTER_SANITIZE_SPECIAL_CHARS));
$interesse = trim(filter_input(INPUT_POST, 'interesse', FILTER_SANITIZE_SPECIAL_CHARS));
$mensagem  = trim(filter_input(INPUT_POST, 'message',   FILTER_SANITIZE_SPECIAL_CHARS));

$erros = [];

if (empty($nome) || mb_strlen($nome) < 3) {
    $erros[] = 'Nome deve ter pelo menos 3 caracteres';
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $erros[] = 'E-mail inválido';
}
if (empty($mensagem) || mb_strlen($mensagem) < 5) {
    $erros[] = 'Mensagem muito curta';
}

if (!empty($erros)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode('. ', $erros)]);
    exit;
}

$destinatario = 'fbrisola@gmail.com';
$assunto      = '=?UTF-8?B?' . base64_encode('Nova mensagem do site — ' . $nome) . '?=';

$interesseLabel = [
    'mydna'    => 'MyDNA — Gestão de RH',
    'logos'    => 'Agência Logos — Marketing Digital com IA',
    'assessia' => 'AssessIA — Avaliações Comportamentais',
    'mentoria' => 'Mentoria IA Aplicada — Próxima Turma',
    'palestra' => 'Palestra ou Treinamento In-company',
    'outro'    => 'Outro assunto',
][$interesse] ?? $interesse;

$corpoEmail = '<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:32px 0">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08)">
        <tr><td style="background:#07080F;padding:32px 40px;text-align:center">
          <p style="font-family:Georgia,serif;font-size:28px;font-weight:700;color:#ffffff;margin:0">F<span style="color:#C4952A">.</span>B<span style="color:#C4952A">.</span>B</p>
          <p style="color:#9AA8BF;font-size:13px;margin:6px 0 0">Nova mensagem recebida pelo site</p>
        </td></tr>
        <tr><td style="padding:36px 40px">
          <table width="100%" cellpadding="0" cellspacing="0">
            <tr><td style="padding-bottom:20px">
              <p style="font-size:11px;font-weight:700;color:#C4952A;text-transform:uppercase;letter-spacing:.1em;margin:0 0 6px">Nome</p>
              <p style="font-size:15px;color:#1a1a2e;margin:0;padding:12px 16px;background:#f8f9fa;border-left:3px solid #C4952A;border-radius:4px">' . htmlspecialchars($nome) . '</p>
            </td></tr>
            <tr><td style="padding-bottom:20px">
              <p style="font-size:11px;font-weight:700;color:#C4952A;text-transform:uppercase;letter-spacing:.1em;margin:0 0 6px">E-mail</p>
              <p style="font-size:15px;color:#1a1a2e;margin:0;padding:12px 16px;background:#f8f9fa;border-left:3px solid #C4952A;border-radius:4px">' . htmlspecialchars($email) . '</p>
            </td></tr>' .
            (!empty($empresa) ? '<tr><td style="padding-bottom:20px">
              <p style="font-size:11px;font-weight:700;color:#C4952A;text-transform:uppercase;letter-spacing:.1em;margin:0 0 6px">Empresa</p>
              <p style="font-size:15px;color:#1a1a2e;margin:0;padding:12px 16px;background:#f8f9fa;border-left:3px solid #C4952A;border-radius:4px">' . htmlspecialchars($empresa) . '</p>
            </td></tr>' : '') .
            (!empty($wpp) ? '<tr><td style="padding-bottom:20px">
              <p style="font-size:11px;font-weight:700;color:#C4952A;text-transform:uppercase;letter-spacing:.1em;margin:0 0 6px">WhatsApp</p>
              <p style="font-size:15px;color:#1a1a2e;margin:0;padding:12px 16px;background:#f8f9fa;border-left:3px solid #C4952A;border-radius:4px">' . htmlspecialchars($wpp) . '</p>
            </td></tr>' : '') .
            (!empty($interesseLabel) ? '<tr><td style="padding-bottom:20px">
              <p style="font-size:11px;font-weight:700;color:#C4952A;text-transform:uppercase;letter-spacing:.1em;margin:0 0 6px">Interesse</p>
              <p style="font-size:15px;color:#1a1a2e;margin:0;padding:12px 16px;background:#f8f9fa;border-left:3px solid #C4952A;border-radius:4px">' . htmlspecialchars($interesseLabel) . '</p>
            </td></tr>' : '') . '
            <tr><td>
              <p style="font-size:11px;font-weight:700;color:#C4952A;text-transform:uppercase;letter-spacing:.1em;margin:0 0 6px">Mensagem</p>
              <p style="font-size:15px;color:#1a1a2e;margin:0;padding:16px;background:#f8f9fa;border-left:3px solid #C4952A;border-radius:4px;line-height:1.7">' . nl2br(htmlspecialchars($mensagem)) . '</p>
            </td></tr>
          </table>
        </td></tr>
        <tr><td style="background:#07080F;padding:20px 40px;text-align:center">
          <p style="color:#4E5D75;font-size:12px;margin:0">Recebido em ' . date('d/m/Y \à\s H:i') . ' · fbrisola.com.br</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body></html>';

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: Site FBrisola <noreply@fbrisola.com.br>\r\n";
$headers .= "Reply-To: =?UTF-8?B?" . base64_encode($nome) . "?= <{$email}>\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

$enviado = mail($destinatario, $assunto, $corpoEmail, $headers);

if ($enviado) {
    echo json_encode(['success' => true, 'message' => 'Mensagem enviada com sucesso!']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao enviar mensagem. Tente novamente ou escreva diretamente para fbrisola@gmail.com']);
}
