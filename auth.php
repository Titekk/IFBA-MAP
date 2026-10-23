<?php
// Sessão e controle de cargos: Administrador, Técnico, Usuário
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/db.php';

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function cargo_do_usuario(mysqli $c, int $id): string {
    $st = $c->prepare("SELECT (SELECT COUNT(*) FROM adm WHERE codigo_usuario=?), (SELECT COUNT(*) FROM tecnico WHERE codigo_usuario=?)");
    $st->bind_param('ii', $id, $id); $st->execute(); $st->bind_result($a, $t); $st->fetch(); $st->close();
    return $a ? 'Administrador' : ($t ? 'Técnico' : 'Usuário');
}

function usuario_logado(): ?array { return $_SESSION['usuario'] ?? null; }

function exigir_login(): array {
    $u = usuario_logado();
    if (!$u) { header('Location: login.php'); exit; }
    return $u;
}

function exigir_cargo(array $cargos): array {
    $u = exigir_login();
    if (!in_array($u['cargo'], $cargos, true)) { http_response_code(403); exit('Acesso negado.'); }
    return $u;
}

function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function checar_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('Token inválido.'); }
}

function navbar(array $u, string $ativo): string {
    $links = ['index.php' => 'Mapa', 'relatorios.php' => 'Relatórios'];
    if ($u['cargo'] === 'Administrador') $links['usuarios.php'] = 'Usuários';
    $html = '<header class="top"><h1>IFBA <span>Campus Ilhéus</span></h1><nav>';
    foreach ($links as $href => $txt) $html .= '<a href="'.$href.'"'.($ativo === $href ? ' class="on"' : '').'>'.$txt.'</a>';
    return $html.'</nav><div class="me">'.h($u['nome']).' <em>'.h($u['cargo']).'</em> <a href="logout.php">Sair</a></div></header>';
}
