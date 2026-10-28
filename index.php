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
$r = $conexao->query("SELECT s.*, p.tomada, p.ar_condicionado, p.ventilador, p.televisao, p.lampadas, p.computador,
                             COALESCE(p.impressora, 'Não identificável') AS impressora
                      FROM sala s
                      LEFT JOIN ponto_energia p ON p.codigo_sala = s.codigo_sala
                      ORDER BY s.codigo_sala");
while ($s = $r->fetch_assoc()) {
    $s['equipamentos'] = [];
    $s['manutencoes'] = [];
    $s['overrides'] = [];
    $s['consumo'] = 0.0;
    $salas[$s['identificacao']] = $s;
}

// ---- Carrega overrides 3D das salas (se tabela existir) ----
$rOv = @$conexao->query("SELECT o.*, s.identificacao FROM device_override o JOIN sala s USING(codigo_sala)");
if ($rOv) {
    while ($ov = $rOv->fetch_assoc()) {
        if (!isset($salas[$ov['identificacao']])) continue;
        $salas[$ov['identificacao']]['overrides'][$ov['device_key']] = [
            'wall' => $ov['wall'] ?: null,
            'offsetX' => $ov['offset_x'] !== null ? (float)$ov['offset_x'] : null,
            'height' => $ov['height_y'] !== null ? (float)$ov['height_y'] : null,
            'tableId' => $ov['table_id'] ?: null,
            'slotIndex' => $ov['slot_index'] !== null ? (int)$ov['slot_index'] : null,
            'w' => $ov['width_w'] !== null ? (float)$ov['width_w'] : null,
            'h' => $ov['height_h'] !== null ? (float)$ov['height_h'] : null,
            'd' => $ov['depth_d'] !== null ? (float)$ov['depth_d'] : null,
        ];
    }
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
    [2,  1,  9,  6, 'SALA DE AULA 22', 'SALA 22', 'ELGIN 30.000 BTUs'],
    [2,  6,  9, 11, 'SALA DE AULA 21', 'SALA 21', 'ELGIN 30.000 BTUs'],
    [2, 11,  9, 16, 'SALA DE AULA 20', 'SALA 20', 'GREE 36.000 BTUs'],
    [2, 16,  9, 21, 'SALA DE AULA 19', 'SALA 19', 'AGRATTO 30.000 BTUs'],
    [2, 25,  9, 30, 'SALA DE AULA 18', 'SALA 18', 'AGRATTO 30.000 BTUs'],
    [2, 30,  9, 35, 'SALA DE AULA 17', 'SALA 17', 'AGRATTO 30.000 BTUs'],
    [2, 35,  9, 40, 'SALA DE AULA 16', 'SALA 16', 'AGRATTO 30.000 BTUs'],
    [2, 40,  9, 45, 'SALA DE AULA 15', 'SALA 15', 'AGRATTO 30.000 BTUs'],

    // --- 2º PAVILHÃO - TÉRREO (Linhas 13..20, Colunas 1..45) ---
    [13,  1, 20,  6, 'LAB EDIF',            'LAB. EDIFIC.', '1 AC / 16 Lâmp.'],
    [13,  6, 20, 11, 'LAB ST',              'LAB. SEG. TRAB.', 'GREE / 8 Lâmp.'],
    [13, 11, 20, 16, 'LABOMEC',             'LABOMEC', 'TCL / 8 Lâmp.'],
    [13, 16, 20, 21, 'CIÊNCIAS AMBIENTAIS', 'CIÊNCIAS AMB.', 'GREE / 13 Tom.'],
    [13, 25, 20, 30, 'LAB TOPOGRAFIA',      'LAB. TOPOGRAFIA', 'ELGIN / 8 Lâmp.'],
    [13, 30, 20, 36, 'LAB MATEMÁTICA',      'LAB. MATEMÁTICA', 'ELGIN / 5 Tom.'],
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

    // --- BLOCO CENTRAL: LABS INFORMÁTICA & APOIO (Linhas 25..33, Colunas 1..35) ---
    [25,  1, 33,  8, 'LAB EDIF',              'LAB. EDIFICAÇÕES', 'Bloco Central'],
    [25,  8, 33, 13, 'LAB INFORMÁTICA 1',     'LAB. INFORMÁTICA I', ''],
    [25, 13, 33, 18, 'LAB INFORMÁTICA 2',     'LAB. INFORMÁTICA II', ''],
    [25, 18, 33, 23, 'LAB MANUTENÇÃO',        'LAB. DE MANUT.', ''],
    [25, 23, 33, 28, 'LAB REDES',             'LAB. DE REDES', ''],
    [25, 30, 29, 35, 'ASSISTÊNCIA DE ALUNOS', 'ASSISTÊNCIA DE ALUNOS', ''],
    [29, 30, 33, 35, 'GRÊMIO',                'GRÊMIO', ''],

    // --- BLOCO CENTRAL: SALAS 01 A 10, ALMOXARIFADO E CIÊNCIAS (Linhas 33..65) ---
    [33,  1, 41,  6, 'SALA DE AULA 4',  'SALA DE AULA 04', 'GREE 36.000 BTUs'],
    [41,  1, 49,  6, 'SALA DE AULA 3',  'SALA DE AULA 03', 'GREE 36.000 BTUs'],
    [49,  1, 57,  6, 'SALA DE AULA 2',  'SALA DE AULA 02', 'AGRATTO 30.000 BTUs'],
    [57,  1, 65,  6, 'SALA DE AULA 1',  'SALA DE AULA 01', 'AGRATTO 30.000 BTUs'],

    [35,  8, 40, 14, 'SALA DE AULA 6',  'SALA DE AULA 06', 'AGRATTO 30.000 BTUs'],
    [35, 14, 40, 20, 'SALA DE AULA 5',  'SALA DE AULA 05', ''],
    [35, 20, 40, 28, 'SALA DE AULA 7',  'SALA DE AULA 07', 'GREE 36.000 BTUs'],

    [33, 30, 41, 35, 'SALA DE AULA 8',  'SALA DE AULA 08', 'GREE 36.000 BTUs'],
    [41, 30, 49, 35, 'SALA DE AULA 9',  'SALA DE AULA 09', 'GREE 36.000 BTUs'],
    [49, 30, 57, 35, 'SALA DE AULA 10', 'SALA DE AULA 10', 'AGRATTO 30.000 BTUs'],
    [57, 30, 65, 35, 'ALMOXARIFADO',    'ALMOXARIFADO', ''],

    [58,  8, 65, 14, 'LAB QUÍMICA',     'LAB. QUÍMICA', ''],
    [58, 14, 65, 20, 'LAB FÍSICA',      'LAB. FÍSICA', ''],
    [58, 21, 65, 27, 'LAB BIOLOGIA',    'LAB. BIOLOGIA', ''],
    [58, 27, 65, 30, 'SALA ADM',        'SALA ADM', ''],

    // --- BLOCO DE SERVIÇOS E CONVIVÊNCIA (Linhas 65..89, Colunas 22..43) ---
    [65, 22, 73, 35, 'REFEITÓRIO',     'REFEITÓRIO', ''],
    [65, 35, 73, 43, 'COZINHA',        'COZINHA', ''],
    [73, 22, 81, 31, 'CANTINA',        'CANTINA', ''],
    [74, 31, 81, 36, 'DEPÓSITO',       'DEPÓSITO', ''],
    [74, 36, 81, 43, 'SERVIÇO MÉDICO', 'SERVIÇO MÉDICO', ''],
    [81, 22, 89, 35, 'ÁREA DE SERVIÇO','ÁREA DE SERVIÇO', ''],
    [81, 35, 89, 43, 'PARTE EXTERNA',  'PARTE EXTERNA', '2 Tom. / 12 Lâmp.'],

    // --- BLOCO ADMINISTRATIVO E ENSINO SUL (Linhas 85..133, Colunas 1..35) ---
    // Coluna Oeste (c 1..6)
    [85,  1, 88,  6, 'COTEP 1',             'COTEP 1', ''],
    [88,  1, 91,  6, 'COTEP 2',             'COTEP 2', ''],
    [91,  1, 95,  6, 'LAB INFORMÁTICA 4',   'LAB. INFOR. 4', ''],
    [95,  1, 99,  6, 'SALA DE AULA 11',     'SALA 11', 'GREE 18.000 BTUs'],
    [99,  1,109,  6, 'SALA DE AULA 12',     'SALA DE AULA 12', 'AGRATTO 30.000 BTUs'],
    [109, 1,114,  6, 'SALA DE AULA 13',     'SALA 13', 'GREE 36.000 BTUs'],
    [114, 1,120,  6, 'COPA',                'COPA', ''],
    [120, 1,125,  6, 'DIRETORIA ACADÊMICA', 'DIRETORIA ACADÊMICA', ''],
    [125, 1,128,  6, 'GABINETE',            'GABINETE', ''],
    [128, 1,133,  6, 'DG',                  'DG (DIREÇÃO GERAL)', ''],

    // Coluna Centro-Oeste (c 8..20)
    [91,  8, 99, 16, 'SALA PROF.',        'SALA PROFESSORES', ''],
    [91, 16, 99, 20, 'NEABI',             'NEABI', ''],
    [99,  8,109, 14, 'SALA COORDENADORES','SALA COORDENADORES', ''],
    [113, 8,118, 14, 'COTEP',             'COTEP (STE-11 / ITE-21)', ''],
    [118, 8,121, 14, 'PROTOCOLO',         'PROTOCOLO', ''],
    [124, 8,133, 14, 'CORES',             'CORES', ''],

    // Coluna Centro-Leste (c 22..28)
    [99, 22,112, 28, 'SALA DE AULA 14',   'SALA 14', 'GREE 36.000 BTUs'],
    [112,22,117, 28, 'HOTEL DE PROJETOS', 'HOTEL DE PROJETOS', ''],

    // Coluna Leste (c 30..35)
    [89, 30, 94, 35, 'ARQUIVO MORTO',  'ARQUIVO MORTO', ''],
    [94, 30, 97, 35, 'CGTI 1',         'CGTI 1', ''],
    [97, 30,100, 35, 'CGTI 2',         'CGTI 2', ''],
    [100,30,103, 35, 'CGTI 3',         'CGTI 3', ''],
    [103,30,107, 35, 'DAP',            'DAP', ''],
    [107,30,110, 35, 'GGP',            'GGP', ''],
    [110,30,113, 35, 'COORD. ESTÁGIO', 'COORD. ESTÁGIO', ''],
    [113,30,117, 35, 'DOF',            'DOF', ''],
    [117,30,120, 35, 'AUDIOVISUAL',    'AUDIOVISUAL', ''],

    // --- AUDITÓRIO, ANFITEATRO E BIBLIOTECA (Linhas 120..133, Colunas 22..35) ---
    [120,22,124, 35, 'ANFITEATRO', 'ANFITEATRO', ''],
    [124,22,130, 35, 'AUDITÓRIO',  'AUDITÓRIO', ''],
    [130,22,133, 35, 'BIBLIOTECA', 'BIBLIOTECA', ''],
];

// Elementos estruturais da planta (corredores, rampas, banheiros, arquibancadas, pátios)
$plantaEstruturas = [
    [1,   1,  2, 45, 'header', '2º PAVILHÃO / 1º ANDAR (SALAS 15 A 22)'],
    [2,  21,  9, 25, 'wc',     'BANHEIRO'],
    [9,   1, 10, 45, 'cor',    'CORREDOR 1º ANDAR'],
    [10, 11, 11, 22, 'rampa',  'RAMPA DE ACESSO'],
    [10, 23, 11, 35, 'rampa',  'RAMPA DE ACESSO'],

    [12,  1, 13, 45, 'header', '2º PAVILHÃO / TÉRREO (LABORATÓRIOS)'],
    [13, 21, 20, 25, 'wc',     'BANHEIRO'],
    [20,  1, 21, 45, 'cor',    'CORREDOR TÉRREO'],
    [21, 11, 22, 22, 'rampa',  'RAMPA DE ACESSO'],
    [21, 23, 22, 35, 'rampa',  'RAMPA DE ACESSO'],

    [13, 55, 50, 57, 'arq',    'ARQUIBANCADA'],
    [13, 74, 50, 76, 'arq',    'ARQUIBANCADA'],

    [40,  8, 56, 28, 'patio',  'PÁTIO CENTRAL COBERTO'],
    [65,  1, 85,  6, 'cor',    'GALERIA / CORREDOR OESTE'],
    [65,  8, 89, 20, 'patio',  'ÁREA DE CONVIVÊNCIA / JARDIM INTERNO'],
    [91, 22, 99, 28, 'wc',     'SANITÁRIOS / APOIO'],
    [109, 8,113, 14, 'rampa',  'ESCADA / HALL'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Planta 2D e Monitoramento Energético | IFBA Campus Ilhéus</title>
<link rel="stylesheet" href="style.css?v=6">
<script type="importmap">
{
  "imports": {
    "three": "./js/vendor/three.module.js",
    "three/addons/": "./js/vendor/addons/"
  }
}
</script>
<style>
  body { overflow-x:hidden; }
  .workspace { display:flex; flex-direction:column; padding:16px 20px 32px; transition:margin-right 180ms var(--ease-snap); }
  body.open .workspace { margin-right:480px; }

  /* Mappedin + Vercel/TE Unified Building Block Ledger Strip */
  .bloco-strip {
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));
    background:var(--bg-elevated);
    border:1px solid var(--line);
    border-radius:var(--radius-sm);
    margin-bottom:12px;
  }
  .bcard {
    background:transparent;
    border-right:1px solid var(--line);
    border-bottom:1px solid var(--line);
    padding:10px 12px;
    cursor:pointer;
    position:relative;
    transition:background 140ms var(--ease-snap);
  }
  .bcard:hover { background:var(--card); }
  .bcard.on {
    background:var(--card);
    box-shadow:inset 0 2px 0 var(--inv-bg);
  }
  .bcard h4 { margin:0 0 4px; font-size:.75rem; font-weight:500; color:var(--mut); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .bcard.on h4 { color:var(--txt); font-weight:600; }
  .bcard .val { font-family:var(--font-mono); font-variant-numeric:tabular-nums; font-size:.95rem; font-weight:600; color:var(--txt); letter-spacing:-0.02em; }
  .bcard .val small { font-size:.68rem; font-weight:400; color:var(--mut); }
  .bcard .meta { font-size:.68rem; color:var(--mut); display:flex; justify-content:space-between; margin-top:4px; font-variant-numeric:tabular-nums; }

  /* Barra de ferramentas do mapa */
  .toolbar { background:var(--bg-elevated); border:1px solid var(--line); border-radius:var(--radius-sm); padding:7px 10px;
             display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; margin-bottom:12px; }
  .toolbar .grp { display:flex; gap:6px; align-items:center; flex-wrap:wrap; }
  .toolbar input[type="search"] { width:250px; padding:6px 10px; font-size:.78rem; background:var(--bg-elevated); }
  .toolbar select { width:auto; padding:6px 10px; font-size:.78rem; background:var(--bg-elevated); }
  .tab-btn { background:transparent; color:var(--mut); border:1px solid transparent; border-radius:var(--radius-sm); padding:5px 11px;
             font-size:.78rem; font-weight:500; cursor:pointer; transition:color 140ms var(--ease-snap), background 140ms var(--ease-snap); }
  .tab-btn:hover { color:var(--txt); background:var(--card); }
  .tab-btn.on { background:var(--inv-bg); color:var(--inv-txt); border-color:var(--inv-bg); font-weight:600; }
  .legend-bar { display:flex; gap:8px; align-items:center; font-size:.72rem; color:var(--mut); flex-wrap:wrap; margin-right:4px; }
  .legend-tag { padding:2px 7px; border-radius:var(--radius-sm); font-size:.7rem; border:1px solid var(--line); background:var(--room-bg); color:var(--txt); }
  .legend-tag.ac { background:var(--room-ac-bg); border-color:var(--room-ac-border); color:var(--txt); }
  .legend-tag.sel { background:var(--inv-bg); border-color:var(--inv-bg); color:var(--inv-txt); }

  /* Viewport da Planta 2D (Swiss White/Black Architectural Blueprint Stage) */
  .planta-viewport { background:var(--map-bg); border:1px solid var(--line); border-radius:var(--radius-sm); padding:18px;
                     overflow:auto; position:relative; }
  .planta-grid {
    display:grid;
    grid-template-columns:repeat(76, minmax(15px, 1fr));
    grid-auto-rows:13px;
    gap:2px;
    min-width:1180px;
    transform-origin:top left;
    transition:transform 160ms var(--ease-snap);
  }

  /* Elementos estruturais (Corredores, Rampas, Arquibancadas, Pátios) */
  .est { border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; text-align:center;
         font-size:.61rem; font-weight:500; color:var(--mut); user-select:none; padding:2px; }
  .est.header { background:var(--inv-bg); color:var(--inv-txt); border:1px solid var(--inv-bg);
                justify-content:flex-start; padding-left:10px; font-size:.68rem; font-weight:600; letter-spacing:-0.01em; }
  .est.cor { background:var(--est-cor-bg); border:1px dashed var(--line); color:var(--mut); }
  .est.rampa { background:var(--est-rampa-bg); border:1px solid var(--line); color:var(--txt); }
  .est.wc { background:var(--est-cor-bg); border:1px solid var(--line); color:var(--mut); writing-mode:vertical-rl; text-orientation:mixed; }
  .est.arq { background:var(--est-rampa-bg); border:1px solid var(--line); color:var(--txt); writing-mode:vertical-rl; font-size:.63rem; }
  .est.patio { background:var(--bg); border:1px dashed var(--line); color:var(--mut); font-size:.7rem; }

  /* Salas clicáveis na Planta 2D */
  .p-sala {
    background:var(--room-bg);
    border:1px solid var(--line);
    border-radius:var(--radius-sm);
    padding:3px 4px;
    cursor:pointer;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;
    text-align:center;
    position:relative;
    overflow:hidden;
    transition:background 120ms var(--ease-snap), border-color 120ms var(--ease-snap), opacity 140ms var(--ease-snap);
  }
  .p-sala:hover {
    z-index:5;
    border-color:var(--line-strong);
    background:var(--card-hover);
  }
  .p-sala.ac {
    background:var(--room-ac-bg);
    border-color:var(--room-ac-border);
  }
  .p-sala.sel {
    background:var(--inv-bg) !important;
    border:1px solid var(--inv-bg) !important;
    z-index:6;
  }
  .p-sala.dim { opacity:.22; pointer-events:none; }
  .p-sala .nm { font-size:.62rem; font-weight:600; line-height:1.1; color:var(--txt); letter-spacing:-0.01em; }
  .p-sala .sub { font-size:.54rem; color:var(--mut); line-height:1.05; margin-top:2px; }
  .p-sala.sel .nm,
  .p-sala.sel .sub { color:var(--inv-txt); }
  .p-sala .kwh {
    margin-top:3px;
    font-family:var(--font-mono);
    font-variant-numeric:tabular-nums;
    font-size:.54rem;
    font-weight:500;
    color:var(--room-kwh-txt);
    background:var(--room-kwh-bg);
    border:1px solid var(--room-kwh-bg);
    padding:1px 4px;
    border-radius:var(--radius-sm);
  }
  .p-sala.sel .kwh {
    color:var(--txt);
    background:var(--bg-elevated);
    border-color:var(--bg-elevated);
  }
  .p-sala[data-sala="GINÁSIO"] {
    background:var(--room-bg);
    border-color:var(--line-strong);
  }

  /* Painel Direito (PUTURE / Spline Inspector Sheet) */
  aside#painel {
    position:fixed; top:0; right:-500px; width:480px; max-width:95vw; height:100vh;
    background:var(--bg-elevated); border-left:1px solid var(--line); z-index:30;
    padding:20px 22px 40px; overflow-y:auto; transition:right 180ms var(--ease-snap);
  }
  body.open aside#painel { right:0; }
  aside .close-btn { float:right; background:var(--card); border:1px solid var(--line-strong); color:var(--txt);
                     padding:4px 10px; font-size:.75rem; border-radius:var(--radius-sm); cursor:pointer; }
  aside .close-btn:hover { background:var(--card-hover); border-color:var(--mut); }

  /* Flush Property Ledger (Replaces 3-KPI-card cliché) */
  .prop-ledger {
    border-top:1px solid var(--line);
    margin:12px 0 14px;
  }
  .prop-row {
    display:flex;
    justify-content:space-between;
    align-items:baseline;
    padding:7px 2px;
    border-bottom:1px solid var(--line);
    font-size:.78rem;
  }
  .prop-row span { color:var(--mut); }
  .prop-row b { font-family:var(--font-mono); font-variant-numeric:tabular-nums; font-weight:600; color:var(--txt); }

  .sec-title { font-size:.82rem; font-weight:600; letter-spacing:-0.015em; color:var(--txt);
               border-bottom:1px solid var(--line); padding-bottom:6px;
               margin:22px 0 10px; display:flex; justify-content:space-between; align-items:baseline; }

  .panel-form {
    padding:8px 0 14px;
    border-bottom:1px solid var(--line);
    margin-bottom:12px;
  }

  /* Visão de Todas as Tabelas das Salas */
  #visaoTabelas { display:none; }
  .all-tables-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(350px,1fr)); gap:12px; }
  .room-table-card { background:var(--bg-elevated); border:1px solid var(--line); border-radius:var(--radius-sm); padding:14px; }
  .room-table-card h4 { margin:0 0 4px; font-size:.86rem; display:flex; justify-content:space-between; align-items:center; }
</style>
</head>
<body>
<?= navbar($u, 'index.php') ?>

<div class="workspace">
  <!-- Índice Arquitetônico de Blocos e Carga Energética -->
  <div class="bloco-strip">
    <div class="bcard on" data-bfiltro="0" onclick="filtrarBloco(0)">
      <h4>Campus Ilhéus (Total)</h4>
      <div class="val"><?= number_format($consumoTotalCampus, 1, ',', '.') ?> <small>kWh/mês</small></div>
      <div class="meta"><span><?= count($salas) ?> salas</span><span><?= $totalAparelhosCampus ?> aparelhos</span></div>
    </div>
    <?php foreach ($blocos as $cb => $b): ?>
    <div class="bcard" data-bfiltro="<?= $cb ?>" onclick="clicarBloco(<?= $cb ?>)">
      <h4 title="<?= h($b['nome']) ?>"><?= h($b['nome']) ?></h4>
      <div class="val"><?= number_format($b['consumo'], 1, ',', '.') ?> <small>kWh/mês</small></div>
      <div class="meta"><span><?= count($b['salas']) ?> salas</span><span><?= $b['qtd_eq'] ?> ar-cond.</span></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Barra de controles e busca -->
  <div class="toolbar">
    <div class="grp">
      <button class="tab-btn on" id="btnTabMapa" onclick="alternarAba('mapa')">Planta 2D</button>
      <button class="tab-btn" id="btnTabTabelas" onclick="alternarAba('tabelas')">Inventário de Salas (<?= count($salas) ?>)</button>
      <input type="search" id="buscaSala" placeholder="Filtrar sala ou setor (ex: Sala 20, CGTI, Lab)..." oninput="aplicarFiltros()">
      <select id="filtroClima" onchange="aplicarFiltros()">
        <option value="todas">Todas as salas (<?= count($salas) ?>)</option>
        <option value="ac">Climatizadas (<?= $totalAparelhosCampus ?>)</option>
        <option value="sem_ac">Sem ar-condicionado identificado</option>
      </select>
    </div>
    <div class="grp">
      <div class="legend-bar">
        <span class="legend-tag ac">Climatizada</span>
        <span class="legend-tag">Padrão</span>
        <span class="legend-tag sel">Selecionada</span>
      </div>
      <button class="btn sec sm" onclick="ajustarZoom(-0.1)" aria-label="Diminuir zoom" style="font-family:var(--font-mono)">-</button>
      <button class="btn sec sm" id="lblZoom" onclick="resetZoom()" style="font-family:var(--font-mono);min-width:52px">100%</button>
      <button class="btn sec sm" onclick="ajustarZoom(0.1)" aria-label="Aumentar zoom" style="font-family:var(--font-mono)">+</button>
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
             style="grid-area:<?= $r1 ?>/<?= $c1 ?>/<?= $r2 ?>/<?= $c2 ?>"
             title="<?= h($idBanco . ' / ' . $blocos[$cb]['nome'] . ($s['consumo'] ? ' / ' . number_format($s['consumo'],1,',','.') . ' kWh/mês' : '')) ?>">
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
  <button class="close-btn" onclick="fecharPainel()" title="Fechar (Esc)" aria-label="Fechar painel">Fechar</button>
  <div id="conteudoPainel"></div>
</aside>

<!-- MODAL DO VISUALIZADOR 3D DE SALA DE AULA (Spline 3-Pane Studio Layout) -->
<div id="modal3D" class="modal-3d" role="dialog" aria-modal="true" aria-labelledby="v3dRoomTitle">
  <div class="v3d-topbar">
    <div class="v3d-topbar-left">
      <h3 id="v3dRoomTitle">Sala de Aula</h3>
      <span class="v3d-badge">9,0m × 3,0m × 7,0m</span>
    </div>
    <div class="v3d-topbar-controls">
      <label for="v3dScenarioSelect" style="margin:0;font-size:.76rem;color:var(--mut)">Fonte de Dados:</label>
      <select id="v3dScenarioSelect" aria-label="Selecionar fonte de dados ou cenário de verificação">
        <option value="db">Dados Reais da Sala (Banco MySQL)</option>
        <option value="S-01">S-01: Sala Vazia (0 dispositivos)</option>
        <option value="S-02">S-02: 1 TV (Parede Frontal)</option>
        <option value="S-03">S-03: 4 TVs (1 em cada parede)</option>
        <option value="S-04">S-04: 5 TVs (Excesso rejeitado BR-03/BR-09)</option>
        <option value="S-05">S-05: 2 Ar-Condicionados (Paredes Laterais)</option>
        <option value="S-06">S-06: 6 Tomadas (Espaçamento &gt;= 1,50m)</option>
        <option value="S-07">S-07: 10 Computadores nas Bancadas</option>
        <option value="S-08">S-08: 10 Computadores + 2 Impressoras</option>
        <option value="S-09">S-09: Override do Administrador (Posição + Tamanho)</option>
        <option value="S-10">S-10: Quantidade Inválida / Negativa (Erro BR-09)</option>
        <option value="S-11">S-11: Tipo Desconhecido (Ignorado com Aviso)</option>
      </select>
      <button type="button" class="btn sec sm" onclick="fecharVisualizador3D()">Fechar (Esc)</button>
    </div>
  </div>
  <div class="v3d-body">
    <!-- Coluna Esquerda (Spline Outliner: Inventário + Objetos na Cena) -->
    <div class="v3d-outliner">
      <div>
        <div class="v3d-section-head">Inventário da Sala <span>Tabela BD / 3D</span></div>
        <div id="v3dSummaryPills" class="v3d-pills"></div>
      </div>
      <div>
        <div class="v3d-section-head">Objetos na Cena <span>X, Y, Z (m)</span></div>
        <div id="v3dDeviceList" class="v3d-dev-list"></div>
      </div>
    </div>

    <!-- Coluna Central (Palco 3D Interativo) -->
    <div class="v3d-stage-wrap">
      <div id="v3dAlerts" class="v3d-alerts"></div>
      <div id="v3dViewport" class="v3d-viewport"></div>

      <!-- Barra de Modos de Câmera 3D -->
      <div class="v3d-bottom-dock" role="toolbar" aria-label="Modos de câmera 3D">
        <button type="button" class="v3d-dock-btn on" data-cam-preset="default">Perspectiva</button>
        <button type="button" class="v3d-dock-btn" data-cam-preset="front">Vista Frontal</button>
        <button type="button" class="v3d-dock-btn" data-cam-preset="top">Planta Superior</button>
      </div>
    </div>

    <!-- Coluna Direita (Spline Property & Transform Inspector) -->
    <div class="v3d-sidebar">
      <div>
        <div class="v3d-section-head">Inspetor de Propriedades <span>BR-07</span></div>
        <div id="v3dInspectorBody"></div>
      </div>
    </div>
  </div>
</div>

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

window.SALAS = SALAS;
window.BLOCOS = BLOCOS;
window.ADMIN = ADMIN;
window.CARGO = CARGO;
window.CSRF = CSRF;

const PONTOS_CAMPOS = [
  ['Tomadas', 'tomada'],
  ['Ar Condicionado (Pontos)', 'ar_condicionado'],
  ['Ventiladores', 'ventilador'],
  ['Televisões', 'televisao'],
  ['Lâmpadas', 'lampadas'],
  ['Computadores', 'computador'],
  ['Impressoras', 'impressora']
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
          <span style="display:flex;gap:6px">
            <button class="btn sec sm" onclick="abrirVisualizador3D('${esc(nomeSala)}')">Modelo 3D</button>
            <button class="btn sm" onclick="abrirSala('${esc(nomeSala)}')">Abrir / ${ADMIN ? 'Editar' : 'Detalhes'}</button>
          </span>
        </h4>
        <div style="color:var(--mut);font-size:.76rem;margin-bottom:8px">${esc(b.nome)} / ${esc(s.andar)} / <b>${kwh(s.consumo)} kWh/mês</b></div>
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
    <div class="sec-title"><span>Pontos de Energia e Infraestrutura</span></div>
    <table>
      <tr><th>Ponto / Equipamento da Tabela</th><th>Quantidade na Sala</th></tr>
      ${linhasPontos}
      <tr><td>Capacidade (Pessoas)</td>${cel(s.capacidade_pessoas)}</tr>
      <tr><td>Área (m²)</td>${cel(s.area)}</tr>
      <tr><td>Situação da Sala</td>${cel(s.situacao)}</tr>
    </table>

    <div class="sec-title"><span>Climatizadores Alocados e Consumo</span></div>
    <table>
      <tr><th>Cód.</th><th>Aparelho</th><th>Marca</th><th>Capacidade</th><th>Gasto Mensal</th></tr>
      ${linhasEq}
    </table>

    <div class="sec-title">
      <span>Histórico de Manutenções (${s.manutencoes.length})</span>
      <a href="relatorios.php${s.equipamentos[0] ? '?equipamento='+s.equipamentos[0].codigo_equipamento : ''}" style="font-size:.75rem;font-weight:500">Abrir registros</a>
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
    <h3 style="margin:0 0 4px;font-size:1.12rem;letter-spacing:-0.02em">${esc(nome)}</h3>
    <div style="color:var(--mut);font-size:.8rem;margin-bottom:10px">
      <a href="#" onclick="clicarBloco(${s.codigo_bloco});return false">${esc(b.nome)}</a> / ${esc(s.andar)} / ${esc(s.tipo_uso)}
    </div>

    <div class="prop-ledger">
      <div class="prop-row"><span>Consumo mensal da sala</span><b>${kwh(s.consumo)} kWh</b></div>
      <div class="prop-row"><span>Consumo mensal do bloco</span><b>${kwh(b.consumo)} kWh</b></div>
      <div class="prop-row"><span>Climatizadores alocados</span><b>${s.equipamentos.length}</b></div>
    </div>

    <button type="button" class="btn-3d-cta" onclick="abrirVisualizador3D('${esc(nome)}')">
      <span>Visualizar Sala em 3D</span>
      <small>9,0m × 3,0m × 7,0m</small>
    </button>

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
    `<option value="${e.codigo_equipamento}">#${e.codigo_equipamento} ${esc(e.tipo)} ${esc(e.marca)} (${Number(e.capacidade_btu||0).toLocaleString('pt-BR')} BTUs)</option>`
  ).join('');
  const optsTipo = TIPOS_MANUT.map(t =>
    `<option value="${t.codigo_tipo_manutencao}">${esc(t.descricao)} (${esc(t.categoria)})</option>`
  ).join('');
  const campoTec = CARGO === 'Técnico'
    ? `<input value="${esc(USUARIO_NOME)} (Técnico)" readonly>`
    : `<select name="codigo_usuario" required>${TECNICOS.map(t => `<option value="${t.codigo_usuario}">${esc(t.nome)} (${esc(t.matricula)})</option>`).join('')}</select>`;

  return `
    <div class="sec-title"><span>Registrar Manutenção nesta Sala</span></div>
    <form class="fManutSala panel-form">
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
    <form class="fEq panel-form">
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
    <div class="sec-title"><span>Alterar Dados da Sala</span><span>Administração</span></div>
    <form id="fSala" class="panel-form">
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

    <div class="sec-title"><span>Gerenciar Aparelhos da Sala</span><span>Administração</span></div>
    ${eqs}
    <form class="fEq panel-form">
      <input type="hidden" name="codigo_sala" value="${s.codigo_sala}">
      <b>Adicionar Novo Aparelho na Sala</b>
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
    <div class="prop-ledger">
      <div class="prop-row"><span>Consumo mensal do bloco</span><b>${kwh(b.consumo)} kWh</b></div>
      <div class="prop-row"><span>Salas no bloco</span><b>${b.salas.length}</b></div>
      <div class="prop-row"><span>Climatizadores ativos</span><b>${b.qtd_eq}</b></div>
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
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    if (document.getElementById('modal3D')?.classList.contains('open')) {
      window.fecharVisualizador3D?.();
    } else {
      fecharPainel();
    }
  }
});

const paramsUrl = new URLSearchParams(location.search);
const salaInicial = paramsUrl.get('sala') || sessionStorage.getItem('salaAberta');
if (salaInicial && SALAS[salaInicial]) {
  sessionStorage.removeItem('salaAberta');
  abrirSala(salaInicial);
}
</script>
<script type="module" src="js/3d/classroomApp.js?v=6"></script>
</body>
</html>
