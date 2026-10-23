<?php
// CRUD do site (inserir, selecionar, atualizar e remover). Respostas em JSON.
require 'auth.php';
header('Content-Type: application/json; charset=utf-8');

$u = usuario_logado();
if (!$u) { http_response_code(401); exit(json_encode(['erro' => 'Faça login.'])); }

const NI = 'Não identificável';
$acao = $_GET['acao'] ?? $_POST['acao'] ?? '';
$ok = fn($d = []) => exit(json_encode(['ok' => true] + $d, JSON_UNESCAPED_UNICODE));
$falha = function ($msg, $code = 400) { http_response_code($code); exit(json_encode(['erro' => $msg], JSON_UNESCAPED_UNICODE)); };
$somente = function (array $cargos) use ($u, $falha) { if (!in_array($u['cargo'], $cargos, true)) $falha('Sem permissão para esta ação.', 403); };
$txt = fn($k) => ($v = trim($_POST[$k] ?? '')) === '' ? NI : $v;          // campo vazio -> 'Não identificável'
$num = fn($k) => ($v = trim($_POST[$k] ?? '')) === '' ? null : (float)str_replace(',', '.', $v);
$data = fn($k) => ($v = trim($_POST[$k] ?? '')) === '' ? null : $v;

if ($_SERVER['REQUEST_METHOD'] === 'POST') checar_csrf();

try {
switch ($acao) {

// ---------- SELECIONAR ----------
case 'sala':
    $st = $conexao->prepare("SELECT s.*, p.tomada, p.ar_condicionado, p.ventilador, p.televisao, p.lampadas, p.computador, b.nome bloco
                             FROM sala s JOIN bloco b USING(codigo_bloco) LEFT JOIN ponto_energia p USING(codigo_sala) WHERE s.codigo_sala=?");
    $id = (int)$_GET['id']; $st->bind_param('i', $id); $st->execute();
    $sala = $st->get_result()->fetch_assoc() ?: $falha('Sala não encontrada.', 404);
    $st = $conexao->prepare("SELECT e.* FROM alocacao a JOIN equipamento e USING(codigo_equipamento) WHERE a.codigo_sala=? AND a.data_retirada IS NULL");
    $st->bind_param('i', $id); $st->execute();
    $sala['equipamentos'] = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $ok(['sala' => $sala]);

// ---------- ATUALIZAR SALA + PONTOS DE ENERGIA (admin) ----------
case 'atualizar_sala':
    $somente(['Administrador']);
    $id = (int)$_POST['codigo_sala'];
    [$an, $tu, $cp, $ar, $si] = [$txt('andar'), $txt('tipo_uso'), $txt('capacidade_pessoas'), $txt('area'), $txt('situacao')];
    $st = $conexao->prepare("UPDATE sala SET andar=?, tipo_uso=?, capacidade_pessoas=?, area=?, situacao=? WHERE codigo_sala=?");
    $st->bind_param('sssssi', $an, $tu, $cp, $ar, $si, $id); $st->execute();
    [$to, $ac, $ve, $tv, $la, $co] = [$txt('tomada'), $txt('ar_condicionado'), $txt('ventilador'), $txt('televisao'), $txt('lampadas'), $txt('computador')];
    $st = $conexao->prepare("INSERT INTO ponto_energia (codigo_sala, tomada, ar_condicionado, ventilador, televisao, lampadas, computador)
                             VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE tomada=VALUES(tomada), ar_condicionado=VALUES(ar_condicionado),
                             ventilador=VALUES(ventilador), televisao=VALUES(televisao), lampadas=VALUES(lampadas), computador=VALUES(computador)");
    $st->bind_param('issssss', $id, $to, $ac, $ve, $tv, $la, $co); $st->execute();
    $ok();

// ---------- INSERIR / ATUALIZAR EQUIPAMENTO (admin) ----------
case 'salvar_equipamento':
    $somente(['Administrador']);
    $id = (int)($_POST['codigo_equipamento'] ?? 0);
    $f = [$txt('tipo'), $txt('marca'), $txt('modelo'), $txt('numero_serie'), $num('capacidade_btu'), $num('consumo_kwh_mes'),
          $txt('tensao'), $txt('potencia'), $txt('status'), $data('data_aquisicao'), $data('fim_garantia')];
    if ($id) {
        $st = $conexao->prepare("UPDATE equipamento SET tipo=?, marca=?, modelo=?, numero_serie=?, capacidade_btu=?, consumo_kwh_mes=?,
                                 tensao=?, potencia=?, status=?, data_aquisicao=?, fim_garantia=? WHERE codigo_equipamento=?");
        $st->bind_param('ssssddsssssi', ...[...$f, $id]); $st->execute();
    } else {
        $sala = (int)$_POST['codigo_sala'];
        $conexao->begin_transaction();
        $st = $conexao->prepare("INSERT INTO equipamento (tipo, marca, modelo, numero_serie, capacidade_btu, consumo_kwh_mes, tensao, potencia, status, data_aquisicao, fim_garantia)
                                 VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $st->bind_param('ssssddsssss', ...$f); $st->execute();
        $eq = $conexao->insert_id;
        $st = $conexao->prepare("INSERT INTO alocacao (data_instalacao, codigo_sala, codigo_equipamento) VALUES (CURDATE(), ?, ?)");
        $st->bind_param('ii', $sala, $eq); $st->execute();
        $conexao->commit();
    }
    $ok();

// ---------- REMOVER EQUIPAMENTO (admin) ----------
case 'remover_equipamento':
    $somente(['Administrador']);
    $id = (int)$_POST['codigo_equipamento'];
    $st = $conexao->prepare("SELECT COUNT(*) FROM manutencao WHERE codigo_equipamento=?");
    $st->bind_param('i', $id); $st->execute(); $st->bind_result($n); $st->fetch(); $st->close();
    if ($n) {   // preserva o histórico de manutenção: apenas retira da sala
        $st = $conexao->prepare("UPDATE alocacao SET data_retirada=CURDATE() WHERE codigo_equipamento=? AND data_retirada IS NULL");
    } else {
        $st = $conexao->prepare("DELETE FROM equipamento WHERE codigo_equipamento=?");
    }
    $st->bind_param('i', $id); $st->execute();
    $ok();

// ---------- RELATÓRIO DE MANUTENÇÃO ----------
case 'inserir_manutencao':
    $somente(['Técnico', 'Administrador']);
    $tec = (int)($u['cargo'] === 'Técnico' ? $u['id'] : $_POST['codigo_usuario']);
    $dt = $data('data_manutencao') ?? $falha('Informe a data.');
    $desc = $txt('descricao_servico'); $custo = $num('custo'); $status = $txt('status');
    $eq = (int)$_POST['codigo_equipamento']; $tipo = (int)$_POST['codigo_tipo_manutencao'];
    $st = $conexao->prepare("INSERT INTO manutencao (data_manutencao, descricao_servico, custo, status, codigo_equipamento, codigo_tipo_manutencao, codigo_usuario)
                             VALUES (?,?,?,?,?,?,?)");
    $st->bind_param('ssdsiii', $dt, $desc, $custo, $status, $eq, $tipo, $tec); $st->execute();
    $ok(['codigo_manutencao' => $conexao->insert_id]);

case 'status_manutencao':
    $somente(['Técnico', 'Administrador']);
    $id = (int)$_POST['codigo_manutencao']; $s = $txt('status');
    $st = $conexao->prepare("UPDATE manutencao SET status=? WHERE codigo_manutencao=?");
    $st->bind_param('si', $s, $id); $st->execute();
    $ok();

case 'assumir_manutencao':
    $somente(['Técnico']);
    $id = (int)$_POST['codigo_manutencao']; $tec = (int)$u['id'];
    $st = $conexao->prepare("UPDATE manutencao SET codigo_usuario=? WHERE codigo_manutencao=?");
    $st->bind_param('ii', $tec, $id); $st->execute();
    $ok();

case 'remover_manutencao':
    $somente(['Administrador']);
    $id = (int)$_POST['codigo_manutencao'];
    $st = $conexao->prepare("DELETE FROM manutencao WHERE codigo_manutencao=?");
    $st->bind_param('i', $id); $st->execute();
    $ok();

// ---------- CARGOS (admin) ----------
case 'definir_cargo':
    $somente(['Administrador']);
    $id = (int)$_POST['codigo_usuario']; $cargo = $_POST['cargo'] ?? '';
    if ($id === $u['id']) $falha('Você não pode alterar o próprio cargo.');
    $conexao->begin_transaction();
    foreach (['adm', 'tecnico'] as $t) {
        $st = $conexao->prepare("DELETE FROM $t WHERE codigo_usuario=?"); $st->bind_param('i', $id); $st->execute();
    }
    if ($cargo === 'Administrador') {
        $st = $conexao->prepare("INSERT INTO adm VALUES (?)"); $st->bind_param('i', $id); $st->execute();
    } elseif ($cargo === 'Técnico') {
        $mat = $txt('matricula'); $tel = $txt('telefone');
        $st = $conexao->prepare("INSERT INTO tecnico VALUES (?,?,?)"); $st->bind_param('iss', $id, $mat, $tel); $st->execute();
    }
    $conexao->commit();
    $ok();

default:
    $falha('Ação inválida.');
}
} catch (mysqli_sql_exception $e) {
    try { $conexao->rollback(); } catch (Throwable $t) {}
    $falha($e->getCode() == 1451 ? 'Registro em uso por outros dados (ex.: técnico com manutenções).' : 'Erro no banco de dados.');
}
