<?php
require 'auth.php';
$u = exigir_login();
$admin = $u['cargo'] === 'Administrador';
$podeManut = in_array($u['cargo'], ['Técnico', 'Administrador'], true);

// ---- Carrega blocos ----
$blocos = [];
$r = $conexao->query("SELECT * FROM bloco ORDER BY codigo_bloco");
while ($b = $r->fetch_assoc()) {
    $b['salas'] = [];
    $b['consumo'] = 0.0;
    $b['qtd_eq'] = 0;
    $blocos[(int)$b['codigo_bloco']] = $b;
}

// ---- Carrega salas + pontos de energia ----
$salas = [];
$r = $conexao->query("SELECT s.*, p.tomada, p.ar_condicionado, p.ventilador, p.televisao, p.lampadas, p.computador
                      FROM sala s
                      LEFT JOIN ponto_energia p ON p.codigo_sala = s.codigo_sala
                      ORDER BY s.codigo_sala");
while ($s = $r->fetch_assoc()) {
    $s['equipamentos'] = [];
    $s['manutencoes'] = [];
    $s['consumo'] = 0.0;
    $salas[$s['identificacao']] = $s;
}

// ---- Carrega equipamentos alocados ----
$r = $conexao->query("SELECT s.identificacao, e.*
                      FROM alocacao a
                      JOIN sala s ON s.codigo_sala = a.codigo_sala
                      JOIN equipamento e ON e.codigo_equipamento = a.codigo_equipamento
                      WHERE a.data_retirada IS NULL");
while ($e = $r->fetch_assoc()) {
    if (!isset($salas[$e['identificacao']])) continue;
    $salas[$e['identificacao']]['equipamentos'][] = $e;
    $salas[$e['identificacao']]['consumo'] += (float)$e['consumo_kwh_mes'];
}

// ---- Carrega manutenções de cada sala ----
$r = $conexao->query("SELECT m.*, t.descricao tipo_desc, t.categoria, us.nome tecnico_nome,
                             e.marca, e.tipo tipo_eq, s.identificacao sala_nome
                      FROM manutencao m
                      JOIN tipo_manutencao t USING(codigo_tipo_manutencao)
                      JOIN usuario us ON us.codigo_usuario = m.codigo_usuario
                      JOIN equipamento e USING(codigo_equipamento)
                      JOIN alocacao a ON a.codigo_equipamento = e.codigo_equipamento AND a.data_retirada IS NULL
                      JOIN sala s ON s.codigo_sala = a.codigo_sala
                      ORDER BY m.data_manutencao DESC, m.codigo_manutencao DESC");
while ($m = $r->fetch_assoc()) {
    if (isset($salas[$m['sala_nome']])) {
        $salas[$m['sala_nome']]['manutencoes'][] = $m;
    }
}

$consumoTotalCampus = 0.0;
$totalAparelhosCampus = 0;
foreach ($salas as $s) {
    $cb = (int)$s['codigo_bloco'];
    $blocos[$cb]['salas'][] = $s['identificacao'];
    $blocos[$cb]['consumo'] += $s['consumo'];
    $blocos[$cb]['qtd_eq']  += count($s['equipamentos']);
    $consumoTotalCampus     += $s['consumo'];
    $totalAparelhosCampus   += count($s['equipamentos']);
}

$tiposManut = $conexao->query("SELECT * FROM tipo_manutencao ORDER BY descricao")->fetch_all(MYSQLI_ASSOC);
$tecnicos   = $conexao->query("SELECT u.codigo_usuario, u.nome, t.matricula FROM tecnico t JOIN usuario u USING(codigo_usuario) ORDER BY u.nome")->fetch_all(MYSQLI_ASSOC);
$proxManut  = (int)($conexao->query("SELECT COALESCE(MAX(codigo_manutencao),0)+1 p FROM manutencao")->fetch_assoc()['p']);

// =====================================================================
//  GEOMETRIA 2D DA PLANTA (coordenadas exatas da planilha Plan1: 133x76)
//  [r1, c1, r2, c2, identificacao_banco, rotulo_planta, subtexto_opcional]
// =====================================================================
$plantaSalas = [
    // --- 2º PAVILHÃO - 1º ANDAR (Linhas 2..9, Colunas 1..45) ---
    [2,  1,  9,  6, 'SALA DE AULA 22', 'SALA 22', 'ELGIN · 30.000 BTUs'],
    [2,  6,  9, 11, 'SALA DE AULA 21', 'SALA 21', 'ELGIN · 30.000 BTUs'],
    [2, 11,  9, 16, 'SALA DE AULA 20', 'SALA 20', 'GREE · 36.000 BTUs'],
    [2, 16,  9, 21, 'SALA DE AULA 19', 'SALA 19', 'AGRATTO · 30.000 BTUs'],
    [2, 25,  9, 30, 'SALA DE AULA 18', 'SALA 18', 'AGRATTO · 30.000 BTUs'],
    [2, 30,  9, 35, 'SALA DE AULA 17', 'SALA 17', 'AGRATTO · 30.000 BTUs'],
    [2, 35,  9, 40, 'SALA DE AULA 16', 'SALA 16', 'AGRATTO · 30.000 BTUs'],
    [2, 40,  9, 45, 'SALA DE AULA 15', 'SALA 15', 'AGRATTO · 30.000 BTUs'],

    // --- 2º PAVILHÃO - TÉRREO (Linhas 13..20, Colunas 1..45) ---
    [13,  1, 20,  6, 'LAB EDIF',            'LAB. EDIFIC.', '1 AC · 16 Lâmp.'],
    [13,  6, 20, 11, 'LAB ST',              'LAB. SEG. TRAB.', 'GREE · 8 Lâmp.'],
    [13, 11, 20, 16, 'LABOMEC',             'LABOMEC', 'TCL · 8 Lâmp.'],
    [13, 16, 20, 21, 'CIÊNCIAS AMBIENTAIS', 'CIÊNCIAS AMB.', 'GREE · 13 Tom.'],
    [13, 25, 20, 30, 'LAB TOPOGRAFIA',      'LAB. TOPOGRAFIA', 'ELGIN · 8 Lâmp.'],
    [13, 30, 20, 36, 'LAB MATEMÁTICA',      'LAB. MATEMÁTICA', 'ELGIN · 5 Tom.'],
    [13, 36, 20, 45, 'LAB DESENHO',         'LAB. DE DESENHO', ''],

    // --- COMPLEXO ESPORTIVO / GINÁSIO (Linhas 12..51, Colunas 48..76) ---
    [12, 48, 18, 54, 'DOJO',            'DOJO', ''],
    [18, 48, 21, 51, 'BANHEIRO MASC.',  'BANHEIRO M.', ''],
    [18, 51, 21, 54, 'COORD.',          'COORD.', ''],
    [21, 48, 24, 51, 'BANHEIRO FEM.',   'BANHEIRO F.', ''],
    [21, 51, 24, 54, 'TRANSP.',         'TRANSP.', ''],
    [24, 48, 29, 54, 'VESTIÁRIO MASC.', 'VESTIÁRIO MASCULINO', ''],
    [29, 48, 33, 54, 'VESTIÁRIO FEM.',  'VESTIÁRIO FEMININO', ''],
    [33, 48, 42, 54, 'SALA DE DANÇA',   'SALA DE DANÇA', ''],
    [42, 48, 51, 54, 'ACADEMIA',        'ACADEMIA', ''],
    [12, 57, 51, 74, 'GINÁSIO',         'GINÁSIO POLIESPORTIVO', 'Quadra coberta'],
    [52, 57, 58, 66, 'ANFITEATRO',      'ANFITEATRO', ''],
    [52, 66, 58, 74, 'BIBLIOTECA',      'BIBLIOTECA', ''],

    // --- BLOCO CENTRAL: LABS INFORMÁTICA & APOIO (Linhas 25..33, Colunas 1..35) ---
    [25,  1, 33,  8, 'LAB EDIF',              'LAB. EDIFICAÇÕES', 'Bloco Central'],
    [25,  8, 33, 13, 'LAB INFORMÁTICA 1',     'LAB. INFORMÁTICA I', ''],
    [25, 13, 33, 18, 'LAB INFORMÁTICA 2',     'LAB. INFORMÁTICA II', ''],
    [25, 18, 33, 23, 'LAB MANUTENÇÃO',        'LAB. DE MANUT.', ''],
    [25, 23, 33, 28, 'LAB REDES',             'LAB. DE REDES', ''],
    [25, 30, 29, 35, 'ASSISTÊNCIA DE ALUNOS', 'ASSISTÊNCIA DE ALUNOS', ''],
    [29, 30, 33, 35, 'GRÊMIO',                'GRÊMIO', ''],

    // --- BLOCO CENTRAL: SALAS 01 A 10, ALMOXARIFADO E CIÊNCIAS (Linhas 33..65) ---
    [33,  1, 41,  6, 'SALA DE AULA 4',  'SALA DE AULA 04', 'GREE · 36.000 BTUs'],
    [41,  1, 49,  6, 'SALA DE AULA 3',  'SALA DE AULA 03', 'GREE · 36.000 BTUs'],
    [49,  1, 57,  6, 'SALA DE AULA 2',  'SALA DE AULA 02', 'AGRATTO · 30.000 BTUs'],

    [35,  8, 40, 14, 'SALA DE AULA 6',  'SALA DE AULA 06', 'AGRATTO · 30.000 BTUs'],
    [35, 14, 40, 20, 'SALA DE AULA 5',  'SALA DE AULA 05', ''],
    [35, 20, 40, 28, 'SALA DE AULA 7',  'SALA DE AULA 07', 'GREE · 36.000 BTUs'],

    [33, 30, 41, 35, 'SALA DE AULA 8',  'SALA DE AULA 08', 'GREE · 36.000 BTUs'],
    [41, 30, 49, 35, 'SALA DE AULA 9',  'SALA DE AULA 09', 'GREE · 36.000 BTUs'],
    [49, 30, 57, 35, 'SALA DE AULA 10', 'SALA DE AULA 10', 'AGRATTO · 30.000 BTUs'],
    [57, 30, 65, 35, 'ALMOXARIFADO',    'ALMOXARIFADO', ''],

    [58,  8, 65, 14, 'LAB QUÍMICA',     'LAB. QUÍMICA', ''],
    [58, 14, 65, 20, 'LAB FÍSICA',      'LAB. FÍSICA', ''],
    [58, 21, 65, 27, 'LAB BIOLOGIA',    'LAB. BIOLOGIA', ''],

    // --- BLOCO DE SERVIÇOS E CONVIVÊNCIA (Linhas 65..89, Colunas 22..43) ---
    [65, 22, 73, 35, 'REFEITÓRIO',     'REFEITÓRIO', ''],
    [65, 35, 73, 43, 'COZINHA',        'COZINHA', ''],
    [73, 22, 81, 31, 'CANTINA',        'CANTINA', ''],
    [74, 36, 81, 43, 'SERVIÇO MÉDICO', 'SERVIÇO MÉDICO', ''],

    // --- BLOCO ADMINISTRATIVO E ENSINO SUL (Linhas 85..133, Colunas 1..35) ---
    // Coluna Oeste (c 1..6)

    // Coluna Centro-Oeste (c 8..20)

    // Coluna Centro-Leste (c 22..28)

    // Coluna Leste (c 30..35)

    ];

// Elementos estruturais da planta (corredores, rampas, banheiros, arquibancadas, pátios)
$plantaEstruturas = [
    [1,   1,  2, 45, 'header', '2º PAVILHÃO · 1º ANDAR (SALAS 15 A 22)'],
    [2,  21,  9, 25, 'wc',     'BANHEIRO'],
    [9,   1, 10, 45, 'cor',    'CORREDOR — 1º ANDAR'],
    [10, 11, 11, 22, 'rampa',  'RAMPA DE ACESSO'],
    [10, 23, 11, 35, 'rampa',  'RAMPA DE ACESSO'],

    [12,  1, 13, 45, 'header', '2º PAVILHÃO · TÉRREO (LABORATÓRIOS)'],
    [13, 21, 20, 25, 'wc',     'BANHEIRO'],
    [20,  1, 21, 45, 'cor',    'CORREDOR — TÉRREO'],
    [21, 11, 22, 22, 'rampa',  'RAMPA DE ACESSO'],
    [21, 23, 22, 35, 'rampa',  'RAMPA DE ACESSO'],

    [13, 55, 50, 57, 'arq',    'ARQUIBANCADA'],
    [13, 74, 50, 76, 'arq',    'ARQUIBANCADA'],

    [40,  8, 56, 28, 'patio',  'PÁTIO CENTRAL COBERTO'],

    // Banheiros do Bloco Central
    [50,  7, 56, 10, 'wc', 'BANHEIRO FEM.'],
    [50, 26, 56, 29, 'wc', 'BANHEIRO MASC.'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Planta 2D &amp; Monitoramento Energético · IFBA Campus Ilhéus</title>
<link rel="stylesheet" href="style.css">
<style>
  :root {
    --b1:#38bdf8; --b2:#a78bfa; --b3:#22c55e; --b4:#f59e0b;
    --b5:#f472b6; --b6:#2dd4bf; --b7:#fb7185;
  }
  body { overflow-x:hidden; }
  .workspace { display:flex; flex-direction:column; padding:18px 24px 36px; transition:margin-right .3s cubic-bezier(.4,0,.2,1); }
  body.open .workspace { margin-right:480px; }

  /* Barra de Blocos e KPIs do Campus */
  .bloco-strip { display:grid; grid-template-columns:repeat(auto-fit,minmax(175px,1fr)); gap:10px; margin-bottom:14px; }
  .bcard { background:var(--card); border:1px solid var(--line); border-left:4px solid var(--acc); border-radius:10px;
           padding:10px 12px; cursor:pointer; transition:transform .15s, border-color .15s, box-shadow .15s; position:relative; }
  .bcard:hover, .bcard.on { transform:translateY(-2px); box-shadow:0 6px 18px #0006; border-color:#fff4; }
  .bcard h4 { margin:0 0 4px; font-size:.78rem; color:var(--txt); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .bcard .val { font-size:1.05rem; font-weight:700; color:var(--acc); }
  .bcard .meta { font-size:.7rem; color:var(--mut); display:flex; justify-content:space-between; margin-top:2px; }

  /* Barra de ferramentas do mapa */
  .toolbar { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:10px 14px;
             display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between; margin-bottom:14px; }
  .toolbar .grp { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
  .toolbar input[type="search"] { width:240px; padding:6px 10px; font-size:.82rem; }
  .toolbar select { width:auto; padding:6px 10px; font-size:.82rem; }
  .tab-btn { background:#0b1220; color:var(--mut); border:1px solid var(--line); border-radius:8px; padding:6px 12px;
             font-size:.8rem; font-weight:600; cursor:pointer; }
  .tab-btn.on { background:var(--acc); color:#04210f; border-color:var(--acc); }
  .legend-bar { display:flex; gap:14px; align-items:center; font-size:.76rem; color:var(--mut); flex-wrap:wrap; }
  .legend-bar span { display:inline-flex; align-items:center; gap:5px; }
  .dot { width:10px; height:10px; border-radius:3px; display:inline-block; }

  /* Viewport da Planta 2D */
  .planta-viewport { background:#070d19; border:1px solid var(--line); border-radius:16px; padding:20px;
                     overflow:auto; position:relative; box-shadow:inset 0 0 40px #000a;
                     background-image:radial-gradient(#1e293b 1px, transparent 1px); background-size:18px 18px; }
  .planta-grid {
    display:grid;
    grid-template-columns:repeat(76, minmax(15px, 1fr));
    grid-auto-rows:13px;
    gap:2px;
    min-width:1180px;
    transform-origin:top left;
    transition:transform .2s ease;
  }

  /* Elementos estruturais (Corredores, Rampas, Arquibancadas, Pátios) */
  .est { border-radius:4px; display:flex; align-items:center; justify-content:center; text-align:center;
         font-size:.62rem; font-weight:600; letter-spacing:.05em; color:#64748b; user-select:none; padding:2px; }
  .est.header { background:linear-gradient(90deg,#1e293b,#0f172a); color:#cbd5e1; border-left:3px solid var(--ac);
                justify-content:flex-start; padding-left:10px; font-size:.7rem; }
  .est.cor { background:#0f172a99; border:1px dashed #334155; color:#64748b; }
  .est.rampa { background:repeating-linear-gradient(45deg,#0f172a,#0f172a 5px,#1e293b 5px,#1e293b 10px);
               border:1px solid #475569; color:#94a3b8; }
  .est.wc { background:#0f172a; border:1px solid #334155; color:#64748b; writing-mode:vertical-rl; text-orientation:mixed; }
  .est.arq { background:repeating-linear-gradient(0deg,#0f172a,#0f172a 4px,#1e293b 4px,#1e293b 8px);
             border:1px solid #475569; color:#94a3b8; writing-mode:vertical-rl; font-size:.65rem; }
  .est.patio { background:#0b132466; border:1px dashed #1e293b; color:#475569; font-size:.72rem; }

  /* Salas clicáveis na Planta 2D */
  .p-sala {
    background:#111c30;
    border:1.5px solid #334155;
    border-radius:6px;
    padding:3px 4px;
    cursor:pointer;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;
    text-align:center;
    position:relative;
    overflow:hidden;
    transition:transform .14s, border-color .14s, background .14s, box-shadow .14s, opacity .2s;
  }
  .p-sala::before {
    content:''; position:absolute; top:0; left:0; right:0; height:3px;
    background:var(--bcor, #64748b); opacity:.85;
  }
  .p-sala:hover {
    transform:scale(1.03);
    z-index:5;
    border-color:#fff;
    background:#1e293b;
    box-shadow:0 8px 24px #000b;
  }
  .p-sala.ac {
    background:linear-gradient(180deg,#0f2942 0%,#0f1c30 100%);
    border-color:#0284c7;
  }
  .p-sala.sel {
    background:linear-gradient(180deg,#14532d 0%,#064e3b 100%) !important;
    border:2px solid var(--acc) !important;
    box-shadow:0 0 0 3px #22c55e44, 0 8px 24px #000c;
    z-index:6;
  }
  .p-sala.dim { opacity:.18; pointer-events:none; }
  .p-sala .nm { font-size:.63rem; font-weight:700; line-height:1.1; color:#f1f5f9; }
  .p-sala .sub { font-size:.55rem; color:#94a3b8; line-height:1.05; margin-top:2px; }
  .p-sala .kwh {
    margin-top:3px;
    font-size:.56rem;
    font-weight:700;
    color:#38bdf8;
    background:#0284c726;
    border:1px solid #38bdf855;
    padding:1px 4px;
    border-radius:4px;
  }
  /* Estilo especial da Quadra / Ginásio */
  .p-sala[data-sala="GINÁSIO"] {
    background:
      radial-gradient(circle at center, transparent 28px, #33415555 29px, #33415555 31px, transparent 32px),
      linear-gradient(0deg, transparent 49.5%, #33415555 49.5%, #33415555 50.5%, transparent 50.5%),
      linear-gradient(180deg,#0f2438,#0b1727);
  }

  /* Painel Direito (Tabelas e Relatórios da Sala / Bloco) */
  aside#painel {
    position:fixed; top:0; right:-500px; width:480px; max-width:95vw; height:100vh;
    background:var(--card); border-left:1px solid var(--line); z-index:30;
    padding:20px 22px 40px; overflow-y:auto; transition:right .3s cubic-bezier(.4,0,.2,1);
    box-shadow:-14px 0 36px #0009;
  }
  body.open aside#painel { right:0; }
  aside .close-btn { float:right; background:#0b1220; border:1px solid var(--line); color:var(--txt);
                     width:32px; height:32px; border-radius:8px; font-size:1.1rem; cursor:pointer; }
  aside .close-btn:hover { border-color:var(--err); color:var(--err); }
  .kpi { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin:12px 0 16px; }
  .kpi div { background:#0b1220; border:1px solid var(--line); border-radius:10px; padding:9px 10px; }
  .kpi b { display:block; font-size:1.05rem; color:var(--acc); }
  .kpi span { color:var(--mut); font-size:.7rem; }
  .sec-title { font-size:.82rem; text-transform:uppercase; letter-spacing:.06em; color:var(--ac);
               margin:18px 0 8px; display:flex; justify-content:space-between; align-items:center; }

  /* Visão de Todas as Tabelas das Salas */
  #visaoTabelas { display:none; }
  .all-tables-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); gap:16px; }
  .room-table-card { background:var(--card); border:1px solid var(--line); border-radius:12px; padding:14px; }
  .room-table-card h4 { margin:0 0 4px; display:flex; justify-content:space-between; align-items:center; }
</style>
</head>
<body>
<?= navbar($u, 'index.php') ?>

<div class="workspace">
  <!-- Gastos Energéticos por Bloco e Total do Campus -->
  <div class="bloco-strip">
    <div class="bcard on" data-bfiltro="0" onclick="filtrarBloco(0)" style="border-left-color:var(--acc)">
      <h4>Campus Ilhéus (Total)</h4>
      <div class="val"><?= number_format($consumoTotalCampus, 1, ',', '.') ?> kWh/mês</div>
      <div class="meta"><span><?= count($salas) ?> salas</span><span><?= $totalAparelhosCampus ?> aparelhos</span></div>
    </div>
    <?php
    $coresBloco = [1=>'var(--b1)',2=>'var(--b2)',3=>'var(--b3)',4=>'var(--b4)',5=>'var(--b5)',6=>'var(--b6)',7=>'var(--b7)'];
    foreach ($blocos as $cb => $b): ?>
    <div class="bcard" data-bfiltro="<?= $cb ?>" onclick="clicarBloco(<?= $cb ?>)" style="border-left-color:<?= $coresBloco[$cb] ?>">
      <h4 title="<?= h($b['nome']) ?>"><?= h($b['nome']) ?></h4>
      <div class="val"><?= number_format($b['consumo'], 1, ',', '.') ?> kWh/mês</div>
      <div class="meta"><span><?= count($b['salas']) ?> salas</span><span><?= $b['qtd_eq'] ?> ar-cond.</span></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Barra de controles e busca -->
  <div class="toolbar">
    <div class="grp">
      <button class="tab-btn on" id="btnTabMapa" onclick="alternarAba('mapa')">Planta 2D Interativa</button>
      <button class="tab-btn" id="btnTabTabelas" onclick="alternarAba('tabelas')">Tabelas de Todas as Salas (<?= count($salas) ?>)</button>
      <input type="search" id="buscaSala" placeholder="Buscar sala ou setor (ex: Sala 20, CGTI, Lab)..." oninput="aplicarFiltros()">
      <select id="filtroClima" onchange="aplicarFiltros()">
        <option value="todas">Todas as salas (<?= count($salas) ?>)</option>
        <option value="ac">Apenas climatizadas (<?= $totalAparelhosCampus ?>)</option>
        <option value="sem_ac">Sem ar-condicionado identificado</option>
      </select>
    </div>
    <div class="grp">
      <div class="legend-bar">
        <span><i class="dot" style="background:#0284c7;border:1px solid #38bdf8"></i>Com Ar-Condicionado</span>
        <span><i class="dot" style="background:#111c30;border:1px solid #475569"></i>Sem AC identificado</span>
        <span><i class="dot" style="background:#14532d;border:1px solid #22c55e"></i>Selecionada</span>
      </div>
      <button class="btn sec sm" onclick="ajustarZoom(-0.1)">−</button>
      <button class="btn sec sm" id="lblZoom" onclick="resetZoom()">100%</button>
      <button class="btn sec sm" onclick="ajustarZoom(0.1)">+</button>
    </div>
  </div>

  <!-- ABA 1: PLANTA 2D FIEL À PLANILHA -->
  <div id="visaoMapa" class="planta-viewport">
    <div class="planta-grid" id="plantaGrid">
      <?php foreach ($plantaEstruturas as [$r1, $c1, $r2, $c2, $tipo, $texto]): ?>
        <div class="est <?= h($tipo) ?>" style="grid-area:<?= $r1 ?>/<?= $c1 ?>/<?= $r2 ?>/<?= $c2 ?>">
          <?= h($texto) ?>
        </div>
      <?php endforeach; ?>

      <?php foreach ($plantaSalas as [$r1, $c1, $r2, $c2, $idBanco, $rotulo, $sub]):
            if (!isset($salas[$idBanco])) continue;
            $s = $salas[$idBanco];
            $cb = (int)$s['codigo_bloco'];
            $temAc = count($s['equipamentos']) > 0;
      ?>
        <div class="p-sala <?= $temAc ? 'ac' : '' ?>"
             data-sala="<?= h($idBanco) ?>"
             data-bloco="<?= $cb ?>"
             data-ac="<?= $temAc ? '1' : '0' ?>"
             style="grid-area:<?= $r1 ?>/<?= $c1 ?>/<?= $r2 ?>/<?= $c2 ?>; --bcor:<?= $coresBloco[$cb] ?? '#64748b' ?>"
             title="<?= h($idBanco . ' · ' . $blocos[$cb]['nome'] . ($s['consumo'] ? ' · ' . number_format($s['consumo'],1,',','.') . ' kWh/mês' : '')) ?>">
          <span class="nm"><?= h($rotulo) ?></span>
          <?php if ($sub): ?><span class="sub"><?= h($sub) ?></span><?php endif; ?>
          <?php if ($s['consumo'] > 0): ?>
            <span class="kwh"><?= number_format($s['consumo'], 1, ',', '.') ?> kWh</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ABA 2: TABELAS DE CADA SALA DA INSTITUIÇÃO -->
  <div id="visaoTabelas">
    <div id="containerTodasTabelas" class="all-tables-grid"></div>
  </div>
</div>

<!-- PAINEL LATERAL DIREITO (Tabelas de Pontos de Energia, Aparelhos, Gastos e Relatórios da Sala) -->
<aside id="painel">
  <button class="close-btn" onclick="fecharPainel()" title="Fechar (Esc)">×</button>
  <div id="conteudoPainel"></div>
</aside>

<script>
const SALAS        = <?= json_encode($salas, JSON_UNESCAPED_UNICODE) ?>;
const BLOCOS       = <?= json_encode($blocos, JSON_UNESCAPED_UNICODE) ?>;
const TIPOS_MANUT  = <?= json_encode($tiposManut, JSON_UNESCAPED_UNICODE) ?>;
const TECNICOS     = <?= json_encode($tecnicos, JSON_UNESCAPED_UNICODE) ?>;
const CARGO        = <?= json_encode($u['cargo'], JSON_UNESCAPED_UNICODE) ?>;
const USUARIO_NOME = <?= json_encode($u['nome'], JSON_UNESCAPED_UNICODE) ?>;
const ADMIN        = <?= json_encode($admin) ?>;
const PODE_MANUT   = <?= json_encode($podeManut) ?>;
const PROX_MANUT   = <?= json_encode($proxManut) ?>;
const CSRF         = <?= json_encode(csrf_token()) ?>;
const NI           = 'Não identificável';

const esc = v => String(v ?? NI).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const cel = v => {
  const val = (v === null || v === undefined || String(v).trim() === '') ? NI : String(v);
  return `<td class="${val === NI ? 'ni' : ''}">${esc(val)}</td>`;
};
const kwh = n => Number(n || 0).toLocaleString('pt-BR', {minimumFractionDigits:1, maximumFractionDigits:1});

const PONTOS_CAMPOS = [
  ['Tomadas', 'tomada'],
  ['Ar Condicionado (Pontos)', 'ar_condicionado'],
  ['Ventiladores', 'ventilador'],
  ['Televisões', 'televisao'],
  ['Lâmpadas', 'lampadas'],
  ['Computadores', 'computador']
];

// =====================================================================
//  FUNÇÃO QUE GERA A TABELA DE UMA SALA (Pontos de Energia + Aparelhos)
// =====================================================================
function gerarTabelaSala(nomeSala, modoCompacto = false) {
  const s = SALAS[nomeSala];
  if (!s) return '';
  const b = BLOCOS[s.codigo_bloco];

  const linhasPontos = PONTOS_CAMPOS.map(([rotulo, chave]) =>
    `<tr><td>${rotulo}</td>${cel(s[chave])}</tr>`
  ).join('');

  const linhasEq = s.equipamentos.length
    ? s.equipamentos.map(e => `
      <tr>
        <td><b>#${e.codigo_equipamento}</b></td>
        ${cel(e.tipo)}
        ${cel(e.marca)}
        ${cel(e.capacidade_btu ? Number(e.capacidade_btu).toLocaleString('pt-BR') + ' BTUs' : NI)}
        <td><b style="color:var(--ac)">${kwh(e.consumo_kwh_mes)} kWh/mês</b></td>
      </tr>`).join('')
    : `<tr><td colspan="5" class="ni">${NI} (nenhum aparelho cadastrado na planta para esta sala)</td></tr>`;

  if (modoCompacto) {
    return `
      <div class="room-table-card" data-card-sala="${esc(nomeSala)}" data-bloco="${s.codigo_bloco}" data-ac="${s.equipamentos.length ? '1' : '0'}">
        <h4>
          <span>${esc(nomeSala)}</span>
          <button class="btn sm" onclick="abrirSala('${esc(nomeSala)}')">Abrir / ${ADMIN ? 'Editar' : 'Detalhes'}</button>
        </h4>
        <div style="color:var(--mut);font-size:.76rem;margin-bottom:8px">${esc(b.nome)} · ${esc(s.andar)} · <b>${kwh(s.consumo)} kWh/mês</b></div>
        <table>
          <tr><th>Ponto de Energia</th><th>Quantidade</th></tr>
          ${linhasPontos}
        </table>
      </div>`;
  }

  const linhasManut = s.manutencoes.length
    ? s.manutencoes.map(m => `
      <tr>
        <td><b>#${m.codigo_manutencao}</b></td>
        <td>${esc(m.data_manutencao.split('-').reverse().join('/'))}</td>
        <td>#${m.codigo_equipamento} ${esc(m.marca)}</td>
        <td>${esc(m.descricao_servico)}</td>
        <td>R$ ${Number(m.custo || 0).toLocaleString('pt-BR', {minimumFractionDigits:2})}</td>
        <td>${esc(m.tecnico_nome)}</td>
        <td><span class="badge ${esc(m.status)}">${esc(m.status)}</span></td>
      </tr>`).join('')
    : `<tr><td colspan="7" class="ni">Nenhuma manutenção registrada para esta sala.</td></tr>`;

  return `
    <div class="sec-title"><span>1. Tabela de Pontos de Energia da Sala</span></div>
    <table>
      <tr><th>Ponto / Equipamento da Tabela</th><th>Quantidade na Sala</th></tr>
      ${linhasPontos}
      <tr><td>Capacidade (Pessoas)</td>${cel(s.capacidade_pessoas)}</tr>
      <tr><td>Área (m²)</td>${cel(s.area)}</tr>
      <tr><td>Situação da Sala</td>${cel(s.situacao)}</tr>
    </table>

    <div class="sec-title"><span>2. Aparelhos Climatizadores &amp; Gasto Energético</span></div>
    <table>
      <tr><th>Cód.</th><th>Aparelho</th><th>Marca</th><th>Capacidade</th><th>Gasto Mensal</th></tr>
      ${linhasEq}
    </table>

    <div class="sec-title">
      <span>3. Relatórios de Manutenção da Sala (${s.manutencoes.length})</span>
      <a href="relatorios.php${s.equipamentos[0] ? '?equipamento='+s.equipamentos[0].codigo_equipamento : ''}" style="font-size:.75rem">Ver página completa →</a>
    </div>
    <div style="overflow-x:auto">
      <table>
        <tr><th>Cód.</th><th>Data</th><th>Aparelho</th><th>Serviço</th><th>Custo</th><th>Técnico</th><th>Status</th></tr>
        ${linhasManut}
      </table>
    </div>
  `;
}

// =====================================================================
//  ABRIR SALA NO PAINEL À DIREITA AO CLICAR NA PLANTA 2D
// =====================================================================
function abrirSala(nome) {
  const s = SALAS[nome];
  if (!s) return;
  const b = BLOCOS[s.codigo_bloco];

  document.querySelectorAll('.p-sala').forEach(el => el.classList.toggle('sel', el.dataset.sala === nome));

  document.getElementById('conteudoPainel').innerHTML = `
    <h3 style="margin:0 0 4px;font-size:1.15rem">${esc(nome)}</h3>
    <div style="color:var(--mut);font-size:.82rem;margin-bottom:10px">
      <a href="#" onclick="clicarBloco(${s.codigo_bloco});return false">${esc(b.nome)}</a> · ${esc(s.andar)} · ${esc(s.tipo_uso)}
    </div>

    <div class="kpi">
      <div><b>${kwh(s.consumo)} kWh</b><span>Gasto da Sala/mês</span></div>
      <div><b>${kwh(b.consumo)} kWh</b><span>Gasto do Bloco/mês</span></div>
      <div><b>${s.equipamentos.length}</b><span>Aparelhos Ativos</span></div>
    </div>

    ${gerarTabelaSala(nome, false)}
    ${PODE_MANUT && s.equipamentos.length ? formManutencaoSala(s, b) : ''}
    ${ADMIN ? formsAdmin(s) : ''}
  `;
  document.body.classList.add('open');
}

// =====================================================================
//  FORMULÁRIO DE RELATÓRIO DE MANUTENÇÃO DIRETO NO PAINEL DA SALA
// =====================================================================
function formManutencaoSala(s, b) {
  const optsEq = s.equipamentos.map(e =>
    `<option value="${e.codigo_equipamento}">#${e.codigo_equipamento} · ${esc(e.tipo)} ${esc(e.marca)} (${Number(e.capacidade_btu||0).toLocaleString('pt-BR')} BTUs)</option>`
  ).join('');
  const optsTipo = TIPOS_MANUT.map(t =>
    `<option value="${t.codigo_tipo_manutencao}">${esc(t.descricao)} (${esc(t.categoria)})</option>`
  ).join('');
  const campoTec = CARGO === 'Técnico'
    ? `<input value="${esc(USUARIO_NOME)} (Técnico)" readonly>`
    : `<select name="codigo_usuario" required>${TECNICOS.map(t => `<option value="${t.codigo_usuario}">${esc(t.nome)} (${esc(t.matricula)})</option>`).join('')}</select>`;

  return `
    <div class="sec-title"><span>Registrar Manutenção nesta Sala</span></div>
    <form class="fManutSala" style="background:#0b1220;padding:12px;border-radius:10px;border:1px solid var(--line)">
      <div class="grid2">
        <div><label>Cód. Manutenção</label><input value="#${PROX_MANUT}" readonly></div>
        <div><label>Cód. Equipamento</label><select name="codigo_equipamento" required>${optsEq}</select></div>
        <div><label>Sala do Equipamento</label><input value="${esc(s.identificacao)}" readonly></div>
        <div><label>Bloco do Equipamento</label><input value="${esc(b.nome)}" readonly></div>
        <div><label>Data da Manutenção</label><input type="date" name="data_manutencao" required value="<?= date('Y-m-d') ?>"></div>
        <div><label>Tipo de Serviço</label><select name="codigo_tipo_manutencao" required>${optsTipo}</select></div>
        <div><label>Custo (R$)</label><input name="custo" placeholder="0,00"></div>
        <div><label>Status</label><select name="status"><option>Pendente</option><option>Em andamento</option><option>Concluída</option></select></div>
      </div>
      <label>Técnico Responsável</label>${campoTec}
      <label>Descrição do Serviço</label>
      <textarea name="descricao_servico" rows="2" required placeholder="Descreva a manutenção realizada no aparelho..."></textarea>
      <p style="margin:10px 0 0"><button class="btn sm" type="submit">Salvar Relatório de Manutenção</button></p>
    </form>`;
}

// =====================================================================
//  EDIÇÃO DA TABELA E EQUIPAMENTOS PELO ADMINISTRADOR
// =====================================================================
const inp = (n, v, lbl, t='text') =>
  `<div><label>${lbl}</label><input type="${t}" name="${n}" value="${v === NI || v == null ? '' : esc(v)}" placeholder="${NI}"></div>`;

function formsAdmin(s) {
  const eqs = s.equipamentos.map(e => `
    <form class="fEq" style="background:#0b1220;padding:10px;border-radius:10px;border:1px solid var(--line);margin-bottom:10px">
      <input type="hidden" name="codigo_equipamento" value="${e.codigo_equipamento}">
      <div style="display:flex;justify-content:space-between;align-items:center">
        <b>Equipamento #${e.codigo_equipamento}</b>
        <button type="button" class="btn del sm" data-rm="${e.codigo_equipamento}">Excluir</button>
      </div>
      <div class="grid2">
        ${inp('tipo', e.tipo, 'Tipo')}
        ${inp('marca', e.marca, 'Marca')}
        ${inp('modelo', e.modelo, 'Modelo')}
        ${inp('numero_serie', e.numero_serie, 'Nº Série')}
        ${inp('capacidade_btu', e.capacidade_btu, 'Capacidade (BTUs)', 'number')}
        ${inp('consumo_kwh_mes', e.consumo_kwh_mes, 'Consumo (kWh/mês)')}
        ${inp('tensao', e.tensao, 'Tensão')}
        ${inp('potencia', e.potencia, 'Potência')}
        ${inp('status', e.status, 'Status')}
      </div>
      <p style="margin:10px 0 0"><button class="btn sm">Atualizar Equipamento</button></p>
    </form>`).join('');

  return `
    <div class="sec-title"><span>Administração · Alterar Dados da Sala</span></div>
    <form id="fSala" style="background:#0b1220;padding:12px;border-radius:10px;border:1px solid var(--line)">
      <input type="hidden" name="codigo_sala" value="${s.codigo_sala}">
      <div class="grid2">
        ${PONTOS_CAMPOS.map(([l, k]) => inp(k, s[k], l)).join('')}
        ${inp('andar', s.andar, 'Andar')}
        ${inp('tipo_uso', s.tipo_uso, 'Tipo de Uso')}
        ${inp('capacidade_pessoas', s.capacidade_pessoas, 'Capacidade (Pessoas)')}
        ${inp('area', s.area, 'Área (m²)')}
        ${inp('situacao', s.situacao, 'Situação')}
      </div>
      <p style="margin:10px 0 0"><button class="btn sm">Salvar Alterações da Tabela</button></p>
    </form>

    <div class="sec-title"><span>Administração · Gerenciar Aparelhos da Sala</span></div>
    ${eqs}
    <form class="fEq" style="background:#0b1220;padding:12px;border-radius:10px;border:1px dashed var(--line)">
      <input type="hidden" name="codigo_sala" value="${s.codigo_sala}">
      <b>+ Adicionar Novo Aparelho na Sala</b>
      <div class="grid2">
        ${inp('tipo', 'Ar condicionado', 'Tipo')}
        ${inp('marca', '', 'Marca')}
        ${inp('capacidade_btu', '', 'BTUs', 'number')}
        ${inp('consumo_kwh_mes', '', 'Consumo (kWh/mês)')}
      </div>
      <p style="margin:10px 0 0"><button class="btn sm">Inserir Aparelho</button></p>
    </form>
    <p style="color:var(--mut);font-size:.76rem">Nota: Campos deixados em branco são gravados como "${NI}".</p>
  `;
}

// =====================================================================
//  CLICAR EM UM BLOCO: FILTRA E MOSTRA O GASTO ENERGÉTICO DO BLOCO
// =====================================================================
let blocoFiltroAtual = 0;

function filtrarBloco(id) {
  blocoFiltroAtual = id;
  document.querySelectorAll('.bcard').forEach(c => c.classList.toggle('on', Number(c.dataset.bfiltro) === id));
  aplicarFiltros();
}

function clicarBloco(id) {
  filtrarBloco(id);
  const b = BLOCOS[id];
  if (!b) return;
  const linhas = b.salas.map(n => {
    const s = SALAS[n];
    return `<tr style="cursor:pointer" onclick="abrirSala('${esc(n)}')">
      <td><b>${esc(n)}</b></td>
      <td>${esc(s.andar)}</td>
      <td>${s.equipamentos.length}</td>
      <td><b style="color:var(--ac)">${kwh(s.consumo)} kWh</b></td>
    </tr>`;
  }).join('');

  document.getElementById('conteudoPainel').innerHTML = `
    <h3 style="margin:0 0 4px">${esc(b.nome)}</h3>
    <div style="color:var(--mut);font-size:.83rem;margin-bottom:12px">${esc(b.descricao)}</div>
    <div class="kpi">
      <div><b>${kwh(b.consumo)} kWh</b><span>Gasto Mensal do Bloco</span></div>
      <div><b>${b.salas.length}</b><span>Salas no Bloco</span></div>
      <div><b>${b.qtd_eq}</b><span>Aparelhos Ativos</span></div>
    </div>
    <div class="sec-title"><span>Salas e Gastos Energéticos do Bloco</span></div>
    <table>
      <tr><th>Sala</th><th>Andar</th><th>Aparelhos</th><th>Gasto Mensal</th></tr>
      ${linhas}
    </table>
    <p><button class="btn sec sm" onclick="filtrarBloco(0)">Mostrar Todos os Blocos no Mapa</button></p>
  `;
  document.body.classList.add('open');
}

function fecharPainel() {
  document.body.classList.remove('open');
  document.querySelectorAll('.p-sala.sel').forEach(e => e.classList.remove('sel'));
}

// =====================================================================
//  BUSCA, FILTROS, ABAS E ZOOM NA PLANTA 2D
// =====================================================================
function aplicarFiltros() {
  const q = document.getElementById('buscaSala').value.trim().toLowerCase();
  const clima = document.getElementById('filtroClima').value;

  document.querySelectorAll('.p-sala, [data-card-sala]').forEach(el => {
    const nome = (el.dataset.sala || el.dataset.cardSala || '').toLowerCase();
    const bl   = Number(el.dataset.bloco);
    const ac   = el.dataset.ac === '1';
    const okBloco = !blocoFiltroAtual || bl === blocoFiltroAtual;
    const okBusca = !q || nome.includes(q);
    const okClima = clima === 'todas' || (clima === 'ac' && ac) || (clima === 'sem_ac' && !ac);
    el.classList.toggle('dim', !(okBloco && okBusca && okClima));
    if (el.dataset.cardSala) el.style.display = (okBloco && okBusca && okClima) ? '' : 'none';
  });
}

let zoomAtual = 1;
function ajustarZoom(delta) {
  zoomAtual = Math.min(1.6, Math.max(0.6, +(zoomAtual + delta).toFixed(2)));
  document.getElementById('plantaGrid').style.transform = `scale(${zoomAtual})`;
  document.getElementById('lblZoom').textContent = Math.round(zoomAtual * 100) + '%';
}
function resetZoom() { zoomAtual = 1; ajustarZoom(0); }

let tabelasRenderizadas = false;
function alternarAba(aba) {
  const isMapa = aba === 'mapa';
  document.getElementById('visaoMapa').style.display    = isMapa ? 'block' : 'none';
  document.getElementById('visaoTabelas').style.display = isMapa ? 'none'  : 'block';
  document.getElementById('btnTabMapa').classList.toggle('on', isMapa);
  document.getElementById('btnTabTabelas').classList.toggle('on', !isMapa);
  if (!isMapa && !tabelasRenderizadas) {
    document.getElementById('containerTodasTabelas').innerHTML =
      Object.keys(SALAS).map(nome => gerarTabelaSala(nome, true)).join('');
    tabelasRenderizadas = true;
    aplicarFiltros();
  }
}

// =====================================================================
//  EVENTOS DE SUBMISSÃO CRUD (Admin e Técnico)
// =====================================================================
async function api(acao, fd) {
  fd.append('acao', acao);
  fd.append('csrf', CSRF);
  const r = await (await fetch('crud.php', {method:'POST', body:fd})).json();
  if (r.erro) { alert(r.erro); throw r; }
  return r;
}

const recarregarSala = nome => { sessionStorage.setItem('salaAberta', nome); location.reload(); };

document.getElementById('conteudoPainel').addEventListener('submit', async e => {
  e.preventDefault();
  const nome = document.querySelector('.p-sala.sel')?.dataset.sala;
  if (e.target.id === 'fSala') {
    await api('atualizar_sala', new FormData(e.target));
  } else if (e.target.classList.contains('fManutSala')) {
    await api('inserir_manutencao', new FormData(e.target));
  } else {
    await api('salvar_equipamento', new FormData(e.target));
  }
  recarregarSala(nome);
});

document.getElementById('conteudoPainel').addEventListener('click', async e => {
  const id = e.target.dataset.rm;
  if (!id || !confirm('Remover este aparelho da sala?')) return;
  const fd = new FormData();
  fd.append('codigo_equipamento', id);
  await api('remover_equipamento', fd);
  recarregarSala(document.querySelector('.p-sala.sel')?.dataset.sala);
});

document.querySelectorAll('.p-sala').forEach(el => el.onclick = () => abrirSala(el.dataset.sala));
document.addEventListener('keydown', e => e.key === 'Escape' && fecharPainel());

const paramsUrl = new URLSearchParams(location.search);
const salaInicial = paramsUrl.get('sala') || sessionStorage.getItem('salaAberta');
if (salaInicial && SALAS[salaInicial]) {
  sessionStorage.removeItem('salaAberta');
  abrirSala(salaInicial);
}
</script>
</body>
</html>
