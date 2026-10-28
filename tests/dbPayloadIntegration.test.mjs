import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { buildPayloadFromDbRoom, parseSurveyQuantity } from '../js/3d/roomConfig.js';
import { computeRoomLayout } from '../js/3d/placementEngine.js';

test('Database-to-3D Payload Integration: Annotated strings like "1 (AGRATTO)" and "1 (M-TV ROKU)" are parsed accurately without BR-09 errors', () => {
  const sampleDbRoom = {
    codigo_sala: 1,
    identificacao: 'SALA DE AULA 15',
    codigo_bloco: 1,
    bloco: '2º Pavilhão - 1º Andar',
    tomada: '4',
    ar_condicionado: '1 (AGRATTO)',
    ventilador: '4',
    televisao: '1 (M-TV ROKU)',
    lampadas: '8',
    computador: 'Não identificável',
    impressora: 'Não identificável',
    equipamentos: [
      {
        codigo_equipamento: 8,
        tipo: 'Ar condicionado',
        marca: 'AGRATTO',
        capacidade_btu: 30000,
      },
    ],
    overrides: {},
  };

  const payload = buildPayloadFromDbRoom(sampleDbRoom);
  const result = computeRoomLayout(payload);

  assert.equal(result.errors.length, 0, `Unexpected errors: ${JSON.stringify(result.errors)}`);
  // 4 outlets + 1 AC + 4 fans + 1 TV + 8 lamps = 18 placed devices
  assert.equal(result.placements.length, 18);

  const tv = result.placements.find((p) => p.type === 'television');
  const ac = result.placements.find((p) => p.type === 'air_conditioner');
  assert.ok(tv.labelPt.includes('M-TV ROKU'));
  assert.ok(ac.labelPt.includes('AGRATTO'));
  assert.notEqual(tv.surface, ac.surface, 'TV and AC must never share a wall (BR-03)');
});

test('All 83 Real Rooms in banco_IFBA render 100% of their database table devices with zero errors', () => {
  const sql = `
    SELECT JSON_OBJECT(
      'codigo_sala', s.codigo_sala,
      'identificacao', s.identificacao,
      'codigo_bloco', s.codigo_bloco,
      'tomada', COALESCE(p.tomada, 'Não identificável'),
      'ar_condicionado', COALESCE(p.ar_condicionado, 'Não identificável'),
      'ventilador', COALESCE(p.ventilador, 'Não identificável'),
      'televisao', COALESCE(p.televisao, 'Não identificável'),
      'lampadas', COALESCE(p.lampadas, 'Não identificável'),
      'computador', COALESCE(p.computador, 'Não identificável'),
      'impressora', COALESCE(p.impressora, 'Não identificável'),
      'qtd_eq', (SELECT COUNT(*) FROM alocacao a WHERE a.codigo_sala = s.codigo_sala AND a.data_retirada IS NULL)
    )
    FROM sala s
    LEFT JOIN ponto_energia p USING(codigo_sala)
    ORDER BY s.codigo_sala;
  `;

  const rawOut = execFileSync(
    '/opt/lampp/bin/mysql',
    ['-h', '127.0.0.1', '-u', 'root', '-N', '-B', 'banco_IFBA', '-e', sql],
    { encoding: 'utf8' }
  );

  const lines = rawOut.trim().split('\n').filter(Boolean);
  assert.equal(lines.length, 83, 'Expected all 83 rooms from banco_IFBA');

  for (const line of lines) {
    const row = JSON.parse(line);
    row.equipamentos = Array.from({ length: Number(row.qtd_eq || 0) }, (_, i) => ({
      codigo_equipamento: i + 1,
      tipo: 'Ar condicionado',
      marca: 'IFBA',
    }));

    const payload = buildPayloadFromDbRoom(row);
    const result = computeRoomLayout(payload);

    assert.equal(
      result.errors.length,
      0,
      `Room "${row.identificacao}" (#${row.codigo_sala}) failed with errors: ${JSON.stringify(result.errors)}`
    );

    const expectedTv = parseSurveyQuantity(row.televisao).count;
    const expectedAc = Math.max(parseSurveyQuantity(row.ar_condicionado).count, row.equipamentos.length);
    const expectedOutlet = parseSurveyQuantity(row.tomada).count;
    const expectedPc = parseSurveyQuantity(row.computador).count;
    const expectedPrinter = parseSurveyQuantity(row.impressora).count;
    const expectedLamp = parseSurveyQuantity(row.lampadas).count;
    const expectedFan = parseSurveyQuantity(row.ventilador).count;

    const countOf = (type) => result.placements.filter((p) => p.type === type).length;

    assert.equal(countOf('television'), expectedTv, `${row.identificacao}: TV count mismatch`);
    assert.equal(countOf('air_conditioner'), expectedAc, `${row.identificacao}: AC count mismatch`);
    assert.equal(countOf('outlet'), expectedOutlet, `${row.identificacao}: Outlet count mismatch`);
    assert.equal(countOf('computer'), expectedPc, `${row.identificacao}: Computer count mismatch`);
    assert.equal(countOf('printer'), expectedPrinter, `${row.identificacao}: Printer count mismatch`);
    assert.equal(countOf('lamp'), expectedLamp, `${row.identificacao}: Lamp count mismatch`);
    assert.equal(countOf('fan'), expectedFan, `${row.identificacao}: Fan count mismatch`);
  }
});
