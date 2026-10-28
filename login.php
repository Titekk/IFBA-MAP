<?php
require 'auth.php';
$erro = '';
$criado = isset($_GET['criado']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = $conexao->prepare("SELECT codigo_usuario, nome, senha FROM usuario WHERE email=? AND (situacao IS NULL OR situacao<>'Inativo')");
    $email = trim($_POST['email'] ?? '');
    $st->bind_param('s', $email);
    $st->execute();
    $u = $st->get_result()->fetch_assoc();
    if ($u && password_verify($_POST['senha'] ?? '', $u['senha'])) {
        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id'    => (int)$u['codigo_usuario'],
            'nome'  => $u['nome'],
            'cargo' => cargo_do_usuario($conexao, (int)$u['codigo_usuario'])
        ];
        header('Location: index.php');
        exit;
    }
    $erro = 'E-mail ou senha inválidos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Entrar | IFBA Campus Ilhéus</title>
<link rel="stylesheet" href="style.css?v=5">
</head>
<body class="auth">
<form class="card" method="post" id="fLogin" style="width:400px">
  <div style="margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--line)">
    <h2 style="margin:0;font-size:1.05rem">IFBA Campus Ilhéus</h2>
    <small style="color:var(--mut)">Sistema de Monitoramento Energético e Manutenção</small>
  </div>
  <?php if ($criado): ?><div class="msg">Conta criada com sucesso! Faça login abaixo.</div><?php endif; ?>
  <?php if ($erro): ?><div class="msg err"><?= h($erro) ?></div><?php endif; ?>

  <label>E-mail institucional</label>
  <input type="email" name="email" id="inpEmail" required placeholder="seu.email@ifba.edu.br" value="<?= h($_POST['email'] ?? '') ?>">

  <label>Senha</label>
  <input type="password" name="senha" id="inpSenha" required placeholder="••••••">

  <button class="btn" type="submit">Entrar no Sistema</button>

  <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--line)">
    <small style="color:var(--mut);display:block;margin-bottom:8px">Acesso rápido por cargo (demonstração):</small>
    <div style="display:flex;gap:6px">
      <button type="button" class="btn sec sm" style="flex:1;margin:0" onclick="entrarDemo('admin@ifba.edu.br')">Administrador</button>
      <button type="button" class="btn sec sm" style="flex:1;margin:0" onclick="entrarDemo('tecnico@ifba.edu.br')">Técnico</button>
      <button type="button" class="btn sec sm" style="flex:1;margin:0" onclick="entrarDemo('usuario@ifba.edu.br')">Usuário</button>
    </div>
  </div>

  <p style="text-align:center;color:var(--mut);margin:16px 0 0;font-size:.85rem">
    Não possui conta? <a href="cadastro.php">Criar uma conta</a>
  </p>
</form>
<script>
function entrarDemo(email) {
  document.getElementById('inpEmail').value = email;
  document.getElementById('inpSenha').value = 'admin123';
  document.getElementById('fLogin').submit();
}
</script>
</body>
</html>
