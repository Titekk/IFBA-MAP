<?php
require 'auth.php';
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $cargo = $_POST['cargo'] ?? 'Usuário';
    $mat   = trim($_POST['matricula'] ?? '') ?: 'Não identificável';
    $tel   = trim($_POST['telefone'] ?? '') ?: 'Não identificável';

    if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Preencha nome e e-mail válidos.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter pelo menos 6 caracteres.';
    } elseif ($senha !== ($_POST['senha2'] ?? '')) {
        $erro = 'As senhas não conferem.';
    } else {
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        try {
            $conexao->begin_transaction();
            $st = $conexao->prepare("INSERT INTO usuario (nome, email, senha, situacao) VALUES (?,?,?,'Ativo')");
            $st->bind_param('sss', $nome, $email, $hash);
            $st->execute();
            $uid = (int)$conexao->insert_id;

            if ($cargo === 'Administrador') {
                $st = $conexao->prepare("INSERT INTO adm (codigo_usuario) VALUES (?)");
                $st->bind_param('i', $uid);
                $st->execute();
            } elseif ($cargo === 'Técnico') {
                $st = $conexao->prepare("INSERT INTO tecnico (codigo_usuario, matricula, telefone) VALUES (?,?,?)");
                $st->bind_param('iss', $uid, $mat, $tel);
                $st->execute();
            }
            $conexao->commit();
            header('Location: login.php?criado=1');
            exit;
        } catch (mysqli_sql_exception $e) {
            try { $conexao->rollback(); } catch (Throwable $t) {}
            $erro = 'Este e-mail já está cadastrado.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Criar Conta | IFBA Campus Ilhéus</title>
<link rel="stylesheet" href="style.css?v=7">
<script>(function(){var t=localStorage.getItem("ifba_theme")||"light";document.documentElement.setAttribute("data-theme",t);})();</script>
</head>
<body class="auth">
<form class="card" method="post" style="width:420px">
  <h2 style="margin-top:0">Criar uma Conta</h2>
  <?php if ($erro): ?><div class="msg err"><?= h($erro) ?></div><?php endif; ?>

  <label>Nome completo</label>
  <input name="nome" required value="<?= h($_POST['nome'] ?? '') ?>">

  <label>E-mail</label>
  <input type="email" name="email" required value="<?= h($_POST['email'] ?? '') ?>">

  <label>Cargo no sistema</label>
  <select name="cargo" id="selCargo" onchange="document.getElementById('boxTec').style.display = this.value==='Técnico' ? 'grid' : 'none'">
    <option value="Usuário">Usuário (Visualização de Mapa, Tabelas e Relatórios)</option>
    <option value="Técnico">Técnico (Registro e atualização de Manutenções)</option>
    <option value="Administrador">Administrador (Edição de Salas, Aparelhos e Cargos)</option>
  </select>

  <div id="boxTec" class="grid2" style="display:none">
    <div><label>Matrícula</label><input name="matricula" placeholder="Ex: TEC-001"></div>
    <div><label>Telefone</label><input name="telefone" placeholder="(73) 99999-0000"></div>
  </div>

  <div class="grid2">
    <div><label>Senha</label><input type="password" name="senha" required minlength="6"></div>
    <div><label>Confirmar senha</label><input type="password" name="senha2" required minlength="6"></div>
  </div>

  <button class="btn" type="submit">Cadastrar</button>
  <p style="text-align:center;color:var(--mut);margin:14px 0 0;font-size:.85rem">
    <a href="login.php">Já possuo uma conta</a>
  </p>
</form>
</body>
</html>
