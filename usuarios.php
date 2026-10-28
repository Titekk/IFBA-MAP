<?php
require 'auth.php';
$u = exigir_cargo(['Administrador']);
$lista = $conexao->query("SELECT u.codigo_usuario, u.nome, u.email, t.matricula, t.telefone,
    CASE WHEN a.codigo_usuario IS NOT NULL THEN 'Administrador' WHEN t.codigo_usuario IS NOT NULL THEN 'Técnico' ELSE 'Usuário' END cargo
    FROM usuario u LEFT JOIN adm a USING(codigo_usuario) LEFT JOIN tecnico t USING(codigo_usuario) ORDER BY u.nome")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Usuários | IFBA Campus Ilhéus</title><link rel="stylesheet" href="style.css?v=5"></head>
<body>
<?= navbar($u, 'usuarios.php') ?>
<div class="wrap"><div id="msg"></div>
<div class="card"><h2 style="margin-top:0">Usuários e cargos</h2>
<table>
  <tr><th>Nome</th><th>E-mail</th><th>Cargo</th><th>Matrícula (técnico)</th><th>Telefone (técnico)</th><th></th></tr>
  <?php foreach ($lista as $l): $eu = $l['codigo_usuario'] == $u['id']; ?>
  <tr data-id="<?= $l['codigo_usuario'] ?>">
    <td><?= h($l['nome']) ?></td><td><?= h($l['email']) ?></td>
    <td><select name="cargo" <?= $eu ? 'disabled' : '' ?>>
      <?php foreach (['Usuário','Técnico','Administrador'] as $c): ?><option <?= $c === $l['cargo'] ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?>
    </select></td>
    <td><input name="matricula" value="<?= h($l['matricula']) ?>"></td>
    <td><input name="telefone" value="<?= h($l['telefone']) ?>"></td>
    <td><?php if (!$eu): ?><button class="btn sm">Salvar</button><?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
</table></div></div>
<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
document.querySelectorAll('tr[data-id] .btn').forEach(b => b.onclick = async () => {
  const tr = b.closest('tr'), fd = new FormData();
  fd.append('acao', 'definir_cargo'); fd.append('csrf', CSRF); fd.append('codigo_usuario', tr.dataset.id);
  tr.querySelectorAll('select,input').forEach(i => fd.append(i.name, i.value));
  const r = await (await fetch('crud.php', {method:'POST', body:fd})).json();
  document.getElementById('msg').innerHTML = r.erro ? `<div class="msg err">${r.erro}</div>` : '<div class="msg">Cargo atualizado.</div>';
});
</script>
</body></html>
