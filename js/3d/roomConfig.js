/**
 * Centralized Configuration & Domain Catalog for the 3D Classroom Visualizer (T-01, T-03, BR-08).
 * Pure ES Module — Zero WebGL, Three.js, or DOM dependencies.
 */

export const ROOM_DIMENSIONS = Object.freeze({
  width: 9.0,   // X axis: [-4.50, +4.50] meters
  height: 3.0,  // Y axis: [ 0.00, +3.00] meters (Y-up, floor at Y=0)
  depth: 7.0,   // Z axis: [-3.50, +3.50] meters
  wallThickness: 0.12,
});

export const SPATIAL_RULES = Object.freeze({
  minAisleClearance: 0.90,      // BR-08 / T-03: >= 0.90 m circulation aisle
  minOutletSpacing: 1.50,       // BR-05 / T-06: >= 1.50 m center-to-center on same wall rail
  outletRailHeights: Object.freeze([0.30, 0.55]), // Low baseboard rail (0.30m) + secondary low conduit rail (0.55m) for high-density labs
  wallCornerMargin: 0.25,       // Safe margin from wall corners for wall-mounted devices
  tableEdgeMargin: 0.10,        // BR-06 / T-07: >= 0.10 m inward margin from table edges
  wallFlushTolerance: 0.01,     // §3.1: max 0.01 m tolerance from inner wall plane
  maxWallTvAcCombined: 4,       // BR-03 (Revised): TV and AC cannot share a wall; max 4 walls
  tvWallPreference: Object.freeze(['front', 'back', 'left', 'right']),
  acWallPreference: Object.freeze(['left', 'right', 'back', 'front']),
  outletWallOrder: Object.freeze(['front', 'left', 'right', 'back']),
});

/**
 * Wall definitions with inward normal, rotationY (in radians) for devices facing inward,
 * and tangent axis used for horizontal offsetX along the wall.
 */
export const WALL_DEFS = Object.freeze({
  front: Object.freeze({
    id: 'front',
    labelPt: 'Parede Frontal (Quadro)',
    length: ROOM_DIMENSIONS.width,
    height: ROOM_DIMENSIONS.height,
    axis: 'x',
    fixedAxis: 'z',
    innerCoord: -ROOM_DIMENSIONS.depth / 2, // Z = -3.50
    normal: Object.freeze({ x: 0, y: 0, z: 1 }),
    rotationY: 0,
    tangentSign: 1,
  }),
  back: Object.freeze({
    id: 'back',
    labelPt: 'Parede Traseira (Fundo)',
    length: ROOM_DIMENSIONS.width,
    height: ROOM_DIMENSIONS.height,
    axis: 'x',
    fixedAxis: 'z',
    innerCoord: ROOM_DIMENSIONS.depth / 2,  // Z = +3.50
    normal: Object.freeze({ x: 0, y: 0, z: -1 }),
    rotationY: Math.PI,
    tangentSign: -1,
  }),
  left: Object.freeze({
    id: 'left',
    labelPt: 'Parede Esquerda (Porta)',
    length: ROOM_DIMENSIONS.depth,
    height: ROOM_DIMENSIONS.height,
    axis: 'z',
    fixedAxis: 'x',
    innerCoord: -ROOM_DIMENSIONS.width / 2, // X = -4.50
    normal: Object.freeze({ x: 1, y: 0, z: 0 }),
    rotationY: Math.PI / 2,
    tangentSign: 1,
  }),
  right: Object.freeze({
    id: 'right',
    labelPt: 'Parede Direita (Janelas)',
    length: ROOM_DIMENSIONS.depth,
    height: ROOM_DIMENSIONS.height,
    axis: 'z',
    fixedAxis: 'x',
    innerCoord: ROOM_DIMENSIONS.width / 2,  // X = +4.50
    normal: Object.freeze({ x: -1, y: 0, z: 0 }),
    rotationY: -Math.PI / 2,
    tangentSign: -1,
  }),
});

/**
 * Fixed architectural context elements (Whiteboard on front wall, Door on left wall).
 */
export const ARCHITECTURAL_FIXTURES = Object.freeze({
  whiteboard: Object.freeze({
    id: 'fixture:whiteboard',
    labelPt: 'Quadro Branco',
    wall: 'front',
    position: Object.freeze({ x: 0.0, y: 1.12, z: -ROOM_DIMENSIONS.depth / 2 + 0.01 }),
    size: Object.freeze({ w: 2.80, h: 0.90, d: 0.02 }),
  }),
  door: Object.freeze({
    id: 'fixture:door',
    labelPt: 'Porta de Acesso',
    wall: 'left',
    position: Object.freeze({ x: -ROOM_DIMENSIONS.width / 2 + 0.02, y: 1.05, z: 2.75 }),
    size: Object.freeze({ w: 0.90, h: 2.10, d: 0.04 }),
  }),
});

/**
 * Helper to build deterministic tabletop slots for a given table (`w = 1.70 m, d = 0.65..0.70 m`).
 * Each table supports 4 non-overlapping slots (`[-0.57, -0.19, +0.19, +0.57]`):
 * - Margin from table edge (`w/2 = 0.85 m`) with `0.35 m` wide device: `0.85 - (0.57 + 0.175) = 0.105 m >= 0.10 m` (BR-06).
 * - Center-to-center distance between adjacent slots: `0.38 m > 0.35 m` (`0.03 m` gap, zero AABB overlap).
 * - Slot 3 (`+0.57 m`) and Slot 2 (`+0.19 m`) include corner/end designations for printers.
 */
function createTableDef({ id, labelPt, role, x, z, w, h, d, rotationY }) {
  const slotOffsets = [-0.57, -0.19, 0.19, 0.57];
  const slots = slotOffsets.map((localX, slotIndex) => ({
    slotIndex,
    isCornerEnd: slotIndex === 3 || slotIndex === 0,
    isRightEnd: slotIndex === 3,
    localX,
    localZ: 0.0,
    worldPosition: Object.freeze({
      x: Number((x + localX).toFixed(4)),
      y: h,
      z: Number(z.toFixed(4)),
    }),
  }));

  return Object.freeze({
    id,
    labelPt,
    role,
    position: Object.freeze({ x, y: h / 2, z }),
    topY: h,
    size: Object.freeze({ w, h, d }),
    rotationY,
    slots: Object.freeze(slots),
  });
}

/**
 * U.S. University Lecture-Style Furniture Layout (BR-08, T-03):
 * - 1 Instructor table at front-left (X = -2.20 m, Z = -2.25 m) facing students (+Z, rotY = PI)
 * - 9 Student tables in a 3 rows × 3 columns grid facing front wall (-Z, rotY = 0)
 *   * Rows at Z = -0.70 m, +0.90 m, +2.50 m (pitch = 1.60 m, depth = 0.65 m -> aisle = 0.95 m >= 0.90 m)
 *   * Columns at X = -2.65 m, 0.00 m, +2.65 m (pitch = 2.65 m, width = 1.70 m -> aisle = 0.95 m >= 0.90 m)
 *   * Total capacity: 10 tables × 4 slots = 40 tabletop slots (accommodates IFBA's 31-computer labs!).
 */
export const TABLE_LAYOUT = Object.freeze([
  createTableDef({
    id: 'instructor',
    labelPt: 'Mesa do Professor',
    role: 'instructor',
    x: -2.20,
    z: -2.25,
    w: 1.70,
    h: 0.75,
    d: 0.70,
    rotationY: Math.PI,
  }),
  ...[
    { row: 1, z: -0.70 },
    { row: 2, z: 0.90 },
    { row: 3, z: 2.50 },
  ].flatMap(({ row, z }) =>
    [
      { col: 1, x: -2.65 },
      { col: 2, x: 0.00 },
      { col: 3, x: 2.65 },
    ].map(({ col, x }) =>
      createTableDef({
        id: `student-r${row}-c${col}`,
        labelPt: `Bancada Aluno F${row}-C${col}`,
        role: 'student',
        x,
        z,
        w: 1.70,
        h: 0.75,
        d: 0.65,
        rotationY: 0,
      })
    )
  ),
]);

/**
 * Device Catalog (§4 + D-06) with default bounding box dimensions in meters and mounting rules.
 */
export const DEVICE_CATALOG = Object.freeze({
  television: Object.freeze({
    type: 'television',
    labelPt: 'Televisor (TV)',
    mountType: 'wall',
    defaultSize: Object.freeze({ w: 1.20, h: 0.70, d: 0.08 }),
    defaultHeight: 2.00, // Above whiteboard top (1.57 m) so front wall TV never overlaps whiteboard
    colorHex: 0x1e293b,
    accentHex: 0x38bdf8,
  }),
  air_conditioner: Object.freeze({
    type: 'air_conditioner',
    labelPt: 'Ar-Condicionado (Split)',
    mountType: 'wall',
    defaultSize: Object.freeze({ w: 0.95, h: 0.32, d: 0.24 }),
    defaultHeight: 2.45, // Mounted high on wall (BR-02 / §4)
    colorHex: 0xffffff,
    accentHex: 0x10b981,
  }),
  outlet: Object.freeze({
    type: 'outlet',
    labelPt: 'Tomada Elétrica',
    mountType: 'wall',
    defaultSize: Object.freeze({ w: 0.12, h: 0.12, d: 0.04 }),
    defaultHeight: 0.30, // Mounted low near baseboard (BR-05 / §4)
    colorHex: 0xfef3c7,
    accentHex: 0xea580c,
  }),
  computer: Object.freeze({
    type: 'computer',
    labelPt: 'Computador (Desktop)',
    mountType: 'tabletop',
    defaultSize: Object.freeze({ w: 0.35, h: 0.40, d: 0.34 }),
    defaultHeight: 0.75, // Rests on table top (BR-06)
    colorHex: 0x1e293b,
    accentHex: 0x3b82f6,
  }),
  printer: Object.freeze({
    type: 'printer',
    labelPt: 'Impressora Multifuncional',
    mountType: 'tabletop',
    defaultSize: Object.freeze({ w: 0.35, h: 0.28, d: 0.36 }),
    defaultHeight: 0.75, // Rests on table corner/end slot (BR-06)
    colorHex: 0xe2e8f0,
    accentHex: 0x334155,
  }),
  lamp: Object.freeze({
    type: 'lamp',
    labelPt: 'Luminária de Teto (LED)',
    mountType: 'ceiling',
    defaultSize: Object.freeze({ w: 0.85, h: 0.05, d: 0.18 }),
    defaultHeight: ROOM_DIMENSIONS.height - 0.025, // Flush ceiling plane Y in [2.95, 3.00]
    colorHex: 0xf8fafc,
    accentHex: 0xfef08a,
  }),
  fan: Object.freeze({
    type: 'fan',
    labelPt: 'Ventilador',
    mountType: 'ceiling',
    defaultSize: Object.freeze({ w: 0.65, h: 0.24, d: 0.65 }),
    defaultHeight: ROOM_DIMENSIONS.height - 0.26, // Suspended plane Y in [2.62, 2.86], below lamps
    colorHex: 0x64748b,
    accentHex: 0x94a3b8,
  }),
});

/**
 * Official Verification Scenarios S-01 through S-11 (§6.2 of prompt_refined_en.md).
 */
export const SCENARIO_PRESETS = Object.freeze({
  'S-01': {
    id: 'S-01',
    titlePt: 'S-01: Sala Vazia (0 dispositivos)',
    room: { id: 'S-01', name: 'Cenário S-01 — Sala Vazia' },
    devices: [
      { type: 'television', quantity: 0 },
      { type: 'air_conditioner', quantity: 0 },
      { type: 'outlet', quantity: 0 },
      { type: 'computer', quantity: 0 },
      { type: 'printer', quantity: 0 },
    ],
    overrides: {},
  },
  'S-02': {
    id: 'S-02',
    titlePt: 'S-02: 1 TV (Parede Frontal)',
    room: { id: 'S-02', name: 'Cenário S-02 — 1 Televisor' },
    devices: [{ type: 'television', quantity: 1 }],
    overrides: {},
  },
  'S-03': {
    id: 'S-03',
    titlePt: 'S-03: 4 TVs (1 em cada parede)',
    room: { id: 'S-03', name: 'Cenário S-03 — 4 Televisores' },
    devices: [{ type: 'television', quantity: 4 }],
    overrides: {},
  },
  'S-04': {
    id: 'S-04',
    titlePt: 'S-04: 5 TVs (Excesso rejeitado BR-03/BR-09)',
    room: { id: 'S-04', name: 'Cenário S-04 — 5 TVs (Violação Capacidade)' },
    devices: [{ type: 'television', quantity: 5 }],
    overrides: {},
  },
  'S-05': {
    id: 'S-05',
    titlePt: 'S-05: 2 Ar-Condicionados (Paredes Laterais)',
    room: { id: 'S-05', name: 'Cenário S-05 — 2 Ar-Condicionados' },
    devices: [{ type: 'air_conditioner', quantity: 2 }],
    overrides: {},
  },
  'S-06': {
    id: 'S-06',
    titlePt: 'S-06: 6 Tomadas (Espaçamento >= 1.50 m)',
    room: { id: 'S-06', name: 'Cenário S-06 — 6 Tomadas' },
    devices: [{ type: 'outlet', quantity: 6 }],
    overrides: {},
  },
  'S-07': {
    id: 'S-07',
    titlePt: 'S-07: 10 Computadores nas Bancadas',
    room: { id: 'S-07', name: 'Cenário S-07 — 10 Computadores' },
    devices: [{ type: 'computer', quantity: 10 }],
    overrides: {},
  },
  'S-08': {
    id: 'S-08',
    titlePt: 'S-08: 10 Computadores + 2 Impressoras',
    room: { id: 'S-08', name: 'Cenário S-08 — 10 PCs + 2 Impressoras' },
    devices: [
      { type: 'computer', quantity: 10 },
      { type: 'printer', quantity: 2 },
    ],
    overrides: {},
  },
  'S-09': {
    id: 'S-09',
    titlePt: 'S-09: Override do Administrador (Posição + Tamanho)',
    room: { id: 'S-09', name: 'Cenário S-09 — Override Manual' },
    devices: [
      { type: 'television', quantity: 1 },
      { type: 'air_conditioner', quantity: 1 },
      { type: 'computer', quantity: 2 },
    ],
    overrides: {
      'television:0': {
        wall: 'back',
        offsetX: 1.25,
        height: 1.85,
        w: 1.50,
        h: 0.85,
        d: 0.10,
      },
    },
  },
  'S-10': {
    id: 'S-10',
    titlePt: 'S-10: Quantidade Inválida / Negativa (Erro BR-09)',
    room: { id: 'S-10', name: 'Cenário S-10 — Quantidade Inválida (-2)' },
    devices: [{ type: 'television', quantity: -2 }],
    overrides: {},
  },
  'S-11': {
    id: 'S-11',
    titlePt: 'S-11: Tipo Desconhecido (Ignorado com Aviso)',
    room: { id: 'S-11', name: 'Cenário S-11 — Tipo Desconhecido' },
    devices: [
      { type: 'quantum_projector', quantity: 2 },
      { type: 'television', quantity: 1 },
      { type: 'outlet', quantity: 4 },
    ],
    overrides: {},
  },
});

/**
 * Parses a database quantity string or number.
 * Supports:
 * - Non-negative integers: `0`, `1`, `"4"`, `"31"`
 * - Annotated survey strings from `ponto_energia`: `"1 (AGRATTO)"`, `"1 (GREE)"`, `"1 (ELGIN)"`, `"1 (TCL)"`, `"1 (M-TV ROKU)"`
 * - Unidentifiable markers: `"Não identificável"`, `""`, `null`, `undefined`
 * - Rejects negative numbers (`-2`), decimals (`1.5`), or arbitrary non-numeric strings (`"abc"`).
 */
export function parseSurveyQuantity(rawVal) {
  if (rawVal === undefined || rawVal === null || rawVal === '') {
    return { valid: true, count: 0, unidentifiable: false, annotation: null };
  }
  if (typeof rawVal === 'number') {
    if (Number.isFinite(rawVal) && Number.isInteger(rawVal) && rawVal >= 0) {
      return { valid: true, count: rawVal, unidentifiable: false, annotation: null };
    }
    return { valid: false, count: 0, unidentifiable: false, annotation: null };
  }

  const str = String(rawVal).trim();
  if (str === '') {
    return { valid: true, count: 0, unidentifiable: false, annotation: null };
  }
  if (/não\s*identific/i.test(str) || /^n\/?a$/i.test(str)) {
    return { valid: true, count: 0, unidentifiable: true, annotation: null };
  }

  // Exact integer string: "0", "1", "18", "33"
  if (/^\d+$/.test(str)) {
    return { valid: true, count: Number(str), unidentifiable: false, annotation: null };
  }

  // Annotated survey count: "1 (AGRATTO)", "1 (GREE)", "1 (M-TV ROKU)"
  const annotatedMatch = str.match(/^(\d+)\s*\(([^)]+)\)\s*$/);
  if (annotatedMatch) {
    return {
      valid: true,
      count: Number(annotatedMatch[1]),
      unidentifiable: false,
      annotation: annotatedMatch[2].trim(),
    };
  }

  return { valid: false, count: 0, unidentifiable: false, annotation: null };
}

/**
 * Normalizes raw database or API room payload into a canonical structure for computeRoomLayout.
 */
export function normalizeRoomPayload(raw = {}) {
  const room = {
    id: String(raw?.room?.id ?? raw?.id ?? 'ROOM-01'),
    dbId: raw?.room?.dbId ?? raw?.dbId ?? null,
    name: String(raw?.room?.name ?? raw?.name ?? 'Sala de Aula'),
    block: String(raw?.room?.block ?? raw?.block ?? ''),
    width: ROOM_DIMENSIONS.width,
    height: ROOM_DIMENSIONS.height,
    depth: ROOM_DIMENSIONS.depth,
  };

  const rawDevices = Array.isArray(raw?.devices) ? raw.devices : [];
  const devices = rawDevices.map((entry) => {
    const type = String(entry?.type ?? '').trim();
    const rawVal = entry?.rawField !== undefined ? entry.rawField : entry?.quantity;
    const parsed = parseSurveyQuantity(entry?.quantity !== undefined ? entry.quantity : rawVal);

    if (parsed.unidentifiable || entry?.unidentifiable) {
      return {
        type,
        quantity: 0,
        rawField: rawVal ?? 'Não identificável',
        unidentifiable: true,
        annotation: null,
        items: Array.isArray(entry?.items) ? entry.items : [],
      };
    }

    if (parsed.valid) {
      return {
        type,
        quantity: parsed.count,
        rawField: rawVal ?? String(parsed.count),
        unidentifiable: false,
        annotation: parsed.annotation || entry?.annotation || null,
        items: Array.isArray(entry?.items) ? entry.items : [],
      };
    }

    // Preserve invalid raw quantity so computeRoomLayout reports BR-09 / S-10 error
    return {
      type,
      quantity: entry?.quantity ?? rawVal,
      rawField: rawVal,
      unidentifiable: false,
      annotation: null,
      items: Array.isArray(entry?.items) ? entry.items : [],
    };
  });

  const overrides =
    raw?.overrides && typeof raw.overrides === 'object' ? { ...raw.overrides } : {};

  return { room, devices, overrides };
}

/**
 * Classifies an equipment record from the `equipamento` table into one of the 3D catalog types.
 */
function classifyEquipmentRecord(eq = {}) {
  const t = String(eq?.tipo ?? '').toLowerCase();
  if (t.includes('ar') || t.includes('climat') || t.includes('split')) return 'air_conditioner';
  if (t.includes('tv') || t.includes('televis')) return 'television';
  if (t.includes('comp') || t.includes('pc') || t.includes('desktop')) return 'computer';
  if (t.includes('impres')) return 'printer';
  if (t.includes('vent')) return 'fan';
  if (t.includes('lamp') || t.includes('lâmp') || t.includes('lumin')) return 'lamp';
  if (t.includes('tomada')) return 'outlet';
  return 'air_conditioner';
}

/**
 * Converts a database room record from `crud.php?acao=sala` (or SALAS dictionary)
 * into a canonical `RoomScenePayload` for `computeRoomLayout()`.
 * Reconciles `ponto_energia` counts (including annotated strings like `"1 (AGRATTO)"`)
 * with active allocated equipment from `alocacao` / `equipamento`.
 */
export function buildPayloadFromDbRoom(sala = {}, blocoNome = '') {
  const eqList = Array.isArray(sala.equipamentos) ? sala.equipamentos : [];
  const eqByType = {
    television: [],
    air_conditioner: [],
    outlet: [],
    computer: [],
    printer: [],
    lamp: [],
    fan: [],
  };

  for (const eq of eqList) {
    const devType = classifyEquipmentRecord(eq);
    if (eqByType[devType]) {
      eqByType[devType].push(eq);
    }
  }

  // Helper that merges ponto_energia field with allocated equipamento records
  const resolveRoomDeviceEntry = (type, rawFieldVal) => {
    const parsed = parseSurveyQuantity(rawFieldVal);
    const allocatedItems = eqByType[type] || [];

    // If ponto_energia has a valid count or allocated equipment exists, take the max
    // so rooms like SALA DE AULA 7 & 11 (where ponto_energia is 'Não identificável'
    // but alocacao has 1 active GREE AC) still render their allocated equipment!
    if (parsed.valid) {
      const effectiveCount = Math.max(parsed.count, allocatedItems.length);
      const isUnid = parsed.unidentifiable && effectiveCount === 0;
      return {
        type,
        rawField: rawFieldVal ?? (effectiveCount > 0 ? String(effectiveCount) : 'Não identificável'),
        quantity: isUnid ? 'Não identificável' : effectiveCount,
        unidentifiable: isUnid,
        annotation: parsed.annotation,
        items: allocatedItems,
      };
    }

    return {
      type,
      rawField: rawFieldVal,
      quantity: rawFieldVal,
      unidentifiable: false,
      items: allocatedItems,
    };
  };

  return normalizeRoomPayload({
    room: {
      id: sala.identificacao,
      dbId: Number(sala.codigo_sala),
      name: sala.identificacao,
      block: blocoNome || sala.bloco || '',
    },
    devices: [
      resolveRoomDeviceEntry('television', sala.televisao),
      resolveRoomDeviceEntry('air_conditioner', sala.ar_condicionado),
      resolveRoomDeviceEntry('outlet', sala.tomada),
      resolveRoomDeviceEntry('computer', sala.computador),
      resolveRoomDeviceEntry('printer', sala.impressora ?? '0'),
      resolveRoomDeviceEntry('lamp', sala.lampadas),
      resolveRoomDeviceEntry('fan', sala.ventilador),
    ],
    overrides: sala.overrides || {},
  });
}
