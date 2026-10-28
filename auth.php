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
    $links = ['index.php' => 'Planta e Salas', 'relatorios.php' => 'Manutenções'];
    if ($u['cargo'] === 'Administrador') $links['usuarios.php'] = 'Usuários';
    $scriptTema = '<script>(function(){var t=localStorage.getItem("ifba_theme")||"light";document.documentElement.setAttribute("data-theme",t);window.alternarTema=function(){var n=document.documentElement.getAttribute("data-theme")==="dark"?"light":"dark";document.documentElement.setAttribute("data-theme",n);localStorage.setItem("ifba_theme",n);var b=document.getElementById("btnTema");if(b)b.textContent=n==="dark"?"Tema: Escuro":"Tema: Claro";window.dispatchEvent(new CustomEvent("ifba-theme-change",{detail:n}));};window.addEventListener("DOMContentLoaded",function(){var b=document.getElementById("btnTema");if(b)b.textContent=(document.documentElement.getAttribute("data-theme")==="dark")?"Tema: Escuro":"Tema: Claro";});})();</script>';
    $html = $scriptTema . '<header class="top"><div class="brand">IFBA Campus Ilhéus <span class="brand-sep">/</span> <span>Monitoramento Energético</span></div><nav>';
    foreach ($links as $href => $txt) $html .= '<a href="'.$href.'"'.($ativo === $href ? ' class="on"' : '').'>'.$txt.'</a>';
    return $html.'</nav><div class="me"><button type="button" id="btnTema" class="theme-toggle" onclick="alternarTema()">Tema: Claro</button> <span>'.h($u['nome']).'</span> <em>'.h($u['cargo']).'</em> <a href="logout.php">Sair</a></div></header>';
}
