<?php
require 'auth.php';
$u = exigir_login();
$podeEscrever = in_array($u['cargo'], ['Técnico', 'Administrador'], true);

$proxIdRow = $conexao->query("SELECT COALESCE(MAX(codigo_manutencao), 0) + 1 AS prox FROM manutencao")->fetch_assoc();
$proxCodigoManutencao = (int)$proxIdRow['prox'];

$rel = $conexao->query("SELECT m.*, t.descricao tipo, t.categoria, us.nome tecnico, e.marca, e.tipo tipo_eq, e.capacidade_btu,
        s.identificacao sala, b.nome bloco
    FROM manutencao m
    JOIN tipo_manutencao t USING(codigo_tipo_manutencao)
    JOIN usuario us ON us.codigo_usuario = m.codigo_usuario
    JOIN equipamento e USING(codigo_equipamento)
    LEFT JOIN alocacao a ON a.codigo_alocacao = (SELECT MAX(codigo_alocacao) FROM alocacao WHERE codigo_equipamento = e.codigo_equipamento)
    LEFT JOIN sala s ON s.codigo_sala = a.codigo_sala
    LEFT JOIN bloco b ON b.codigo_bloco = s.codigo_bloco
    ORDER BY m.data_manutencao DESC, m.codigo_manutencao DESC")->fetch_all(MYSQLI_ASSOC);

$equip = $conexao->query("SELECT e.codigo_equipamento, e.tipo, e.marca, e.capacidade_btu, s.identificacao sala, b.nome bloco
    FROM equipamento e
    JOIN alocacao a ON a.codigo_equipamento = e.codigo_equipamento AND a.data_retirada IS NULL
    JOIN sala s USING(codigo_sala)
    JOIN bloco b USING(codigo_bloco)
    ORDER BY e.codigo_equipamento")->fetch_all(MYSQLI_ASSOC);

$tipos = $conexao->query("SELECT * FROM tipo_manutencao ORDER BY descricao")->fetch_all(MYSQLI_ASSOC);
$tecnicos = $conexao->query("SELECT u.codigo_usuario, u.nome, t.matricula FROM tecnico t JOIN usuario u USING(codigo_usuario) ORDER BY u.nome")->fetch_all(MYSQLI_ASSOC);

$eqSelecionado = (int)($_GET['equipamento'] ?? ($equip[0]['codigo_equipamento'] ?? 0));
$fmt = fn($v) => $v === null ? 'Não identificável' : 'R$ ' . number_format((float)$v, 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Relatórios de Manutenção · IFBA Campus Ilhéus</title>
<link rel="stylesheet" href="style.css?v=4">
</head>
<body>
<?= navbar($u, 'relatorios.php') ?>
<div class="wrap">
<div id="msg"></div>

<?php if ($podeEscrever): ?>
<form class="card" id="formRel">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
    <h2 style="margin:0;font-size:1rem">Formulário de Relatório de Manutenção</h2>
    <span class="badge">Cargo: <?= h($u['cargo']) ?></span>
  </div>
  <div class="grid2" style="grid-template-columns:repeat(auto-fit,minmax(210px,1fr))">
    <div>
      <label>Código da Manutenção</label>
      <input type="number" name="codigo_manutencao_ Exib" value="<?= $proxCodigoManutencao ?>" readonly title="Gerado automaticamente pelo sistema" style="opacity:.8">
    </div>
    <div>
      <label>Código do Equipamento</label>
      <select name="codigo_equipamento" id="selEquip" required onchange="atualizarSalaBloco()">
        <?php foreach ($equip as $e): ?>
          <option value="<?= $e['codigo_equipamento'] ?>"
                  data-sala="<?= h($e['sala']) ?>"
                  data-bloco="<?= h($e['bloco']) ?>"
                  <?= $e['codigo_equipamento'] == $eqSelecionado ? 'selected' : '' ?>>
            #<?= $e['codigo_equipamento'] ?> · <?= h($e['tipo'].' '.$e['marca']) ?> (<?= number_format((int)$e['capacidade_btu'], 0, ',', '.') ?> BTUs)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Sala do Equipamento</label>
      <input type="text" id="inpSalaEq" readonly>
    </div>
    <div>
      <label>Bloco do Equipamento</label>
      <input type="text" id="inpBlocoEq" readonly>
    </div>
    <div>
      <label>Data da Manutenção</label>
      <input type="date" name="data_manutencao" required value="<?= date('Y-m-d') ?>">
    </div>
    <div>
      <label>Tipo de Manutenção</label>
      <select name="codigo_tipo_manutencao" required>
        <?php foreach ($tipos as $t): ?>
          <option value="<?= $t['codigo_tipo_manutencao'] ?>"><?= h($t['descricao'].' ('.$t['categoria'].')') ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label>Custo do Serviço (R$)</label>
      <input name="custo" inputmode="decimal" placeholder="Ex: 150,00">
    </div>
    <div>
      <label>Status da Manutenção</label>
      <select name="status">
        <option value="Pendente">Pendente</option>
        <option value="Em andamento">Em andamento</option>
        <option value="Concluída">Concluída</option>
      </select>
    </div>
    <div>
      <label>Técnico Responsável</label>
      <?php if ($u['cargo'] === 'Técnico'): ?>
        <input type="text" value="<?= h($u['nome']) ?> (Você)" readonly>
      <?php else: ?>
        <select name="codigo_usuario" required>
          <?php foreach ($tecnicos as $t): ?>
            <option value="<?= $t['codigo_usuario'] ?>"><?= h($t['nome'].' · '.$t['matricula']) ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
    </div>
  </div>
  <label>Descrição detalhada do serviço realizado</label>
  <textarea name="descricao_servico" rows="3" required placeholder="Descreva o diagnóstico, peças trocadas ou procedimentos efetuados no aparelho..."></textarea>
  <p style="margin-bottom:0"><button class="btn" type="submit">Registrar Relatório de Manutenção</button></p>
</form>
<?php endif; ?>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:12px">
    <h2 style="margin:0">Histórico e Relatórios de Manutenção (<?= count($rel) ?>)</h2>
    <input id="filtro" placeholder="Filtrar por código, sala, bloco, equipamento, técnico ou status..." style="max-width:380px">
  </div>
  <div style="overflow-x:auto">
  <table id="tab">
    <tr>
      <th>Cód. Manut.</th>
      <th>Data</th>
      <th>Cód. Equip. / Aparelho</th>
      <th>Sala</th>
      <th>Bloco</th>
      <th>Tipo</th>
      <th>Descrição do Serviço</th>
      <th>Custo</th>
      <th>Técnico Responsável</th>
      <th>Status</th>
      <?php if ($podeEscrever): ?><th>Ações</th><?php endif; ?>
    </tr>
    <?php foreach ($rel as $r): ?>
    <tr>
      <td><b>#<?= $r['codigo_manutencao'] ?></b></td>
      <td><?= date('d/m/Y', strtotime($r['data_manutencao'])) ?></td>
      <td><b>#<?= $r['codigo_equipamento'] ?></b> · <?= h($r['tipo_eq'].' '.$r['marca']) ?></td>
      <td><a href="index.php?sala=<?= urlencode($r['sala'] ?? '') ?>"><?= h($r['sala'] ?? 'Não identificável') ?></a></td>
      <td><?= h($r['bloco'] ?? 'Não identificável') ?></td>
      <td><?= h($r['tipo']) ?></td>
      <td><?= nl2br(h($r['descricao_servico'])) ?></td>
      <td><?= $fmt($r['custo']) ?></td>
      <td>
        <?= h($r['tecnico']) ?>
        <?php if ($u['cargo'] === 'Técnico' && (int)$r['codigo_usuario'] !== (int)$u['id']): ?>
          <br><button type="button" class="btn sec sm" style="margin-top:4px" data-assumir="<?= $r['codigo_manutencao'] ?>">Assinar meu nome</button>
        <?php endif; ?>
      </td>
      <td>
        <?php if ($podeEscrever): ?>
          <select class="st" data-id="<?= $r['codigo_manutencao'] ?>" style="min-width:130px">
            <?php foreach (['Pendente','Em andamento','Concluída'] as $s): ?>
              <option <?= $s === $r['status'] ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <span class="badge <?= h($r['status']) ?>"><?= h($r['status']) ?></span>
        <?php endif; ?>
      </td>
      <?php if ($podeEscrever): ?>
      <td>
        <?php if ($u['cargo'] === 'Administrador'): ?>
          <button class="btn del sm" data-del="<?= $r['codigo_manutencao'] ?>">Excluir</button>
        <?php else: ?>
          <span style="color:var(--mut);font-size:.75rem">Técnico</span>
        <?php endif; ?>
      </td>
      <?php endif; ?>
    </tr>
    <?php endforeach; ?>
    <?php if (!$rel): ?><tr><td colspan="11" class="ni">Nenhum relatório registrado.</td></tr><?php endif; ?>
  </table>
  </div>
</div>
</div>
<script>
const CSRF = <?= json_encode(csrf_token()) ?>;
function atualizarSalaBloco() {
  const sel = document.getElementById('selEquip');
  if (!sel || !sel.selectedOptions[0]) return;
  const opt = sel.selectedOptions[0];
  document.getElementById('inpSalaEq').value  = opt.dataset.sala || 'Não identificável';
  document.getElementById('inpBlocoEq').value = opt.dataset.bloco || 'Não identificável';
}
atualizarSalaBloco();

async function api(acao, dados) {
  const fd = dados instanceof FormData ? dados : new FormData();
  if (!(dados instanceof FormData)) for (const k in dados) fd.append(k, dados[k]);
  fd.append('acao', acao); fd.append('csrf', CSRF);
  const r = await (await fetch('crud.php', {method:'POST', body:fd})).json();
  if (r.erro) { document.getElementById('msg').innerHTML = `<div class="msg err">${r.erro}</div>`; throw r; }
  return r;
}
document.getElementById('formRel')?.addEventListener('submit', async e => {
  e.preventDefault(); await api('inserir_manutencao', new FormData(e.target)); location.reload();
});
document.querySelectorAll('.st').forEach(s => s.onchange = async () => {
  await api('status_manutencao', {codigo_manutencao:s.dataset.id, status:s.value});
  document.getElementById('msg').innerHTML = '<div class="msg">Status atualizado com sucesso.</div>';
});
document.querySelectorAll('[data-assumir]').forEach(b => b.onclick = async () => {
  await api('assumir_manutencao', {codigo_manutencao:b.dataset.assumir}); location.reload();
});
document.querySelectorAll('[data-del]').forEach(b => b.onclick = async () => {
  if (confirm('Excluir este relatório de manutenção?')) {
    await api('remover_manutencao', {codigo_manutencao:b.dataset.del}); b.closest('tr').remove();
  }
});
document.getElementById('filtro').oninput = e => {
  const q = e.target.value.toLowerCase();
  document.querySelectorAll('#tab tr:not(:first-child)').forEach(tr => tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none');
};
</script>
</body>
</html>
