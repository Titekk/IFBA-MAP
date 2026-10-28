import test from 'node:test';
import assert from 'node:assert/strict';
import {
  ROOM_DIMENSIONS,
  SPATIAL_RULES,
  WALL_DEFS,
  TABLE_LAYOUT,
  DEVICE_CATALOG,
  SCENARIO_PRESETS,
  normalizeRoomPayload,
} from '../js/3d/roomConfig.js';
import {
  computeRoomLayout,
  computeWorldAABB,
  boxesOverlap3D,
} from '../js/3d/placementEngine.js';

/**
 * Helper that asserts all §3.1 Geometric Invariants on any valid placement result.
 */
function assertGeometricInvariants(result) {
  assert.equal(result.errors.length, 0, `Expected 0 errors, got: ${JSON.stringify(result.errors)}`);

  const tvAcWalls = new Set();
  const aabbs = [];

  for (const p of result.placements) {
    const aabb = computeWorldAABB(p.position, p.size, p.rotation.y);
    aabbs.push({ key: p.key, aabb });

    if (p.mountType === 'wall') {
      const wall = WALL_DEFS[p.surface];
      assert.ok(wall, `Wall-mounted device ${p.key} must reference a valid wall`);

      // §3.1 Wall flush: distance from wall inner surface == D_dev / 2 (tolerance <= 0.01 m)
      const coordOnFixedAxis = p.position[wall.fixedAxis];
      const distFromWall = Math.abs(coordOnFixedAxis - wall.innerCoord);
      const expectedDist = p.size.d / 2;
      assert.ok(
        Math.abs(distFromWall - expectedDist) <= SPATIAL_RULES.wallFlushTolerance,
        `${p.key} flush distance ${distFromWall}m differs from d/2 (${expectedDist}m) by more than 0.01m`
      );

      // §3.1 Orientation: device rotation matches wall inward normal rotation
      assert.ok(
        Math.abs(p.rotation.y - wall.rotationY) < 1e-4,
        `${p.key} rotationY ${p.rotation.y} must match wall inward normal rotationY ${wall.rotationY}`
      );

      // BR-03 (Revised): At most 1 TV OR 1 AC per wall (never sharing a wall)
      if (p.type === 'television' || p.type === 'air_conditioner') {
        assert.ok(
          !tvAcWalls.has(p.surface),
          `BR-03 violation: Wall "${p.surface}" is shared by multiple TV/AC units`
        );
        tvAcWalls.add(p.surface);
      }
    }

    if (p.mountType === 'tabletop') {
      const table = result.tables.find((t) => t.id === p.tableId);
      assert.ok(table, `Tabletop device ${p.key} must reference an existing table`);

      // §3.1 Tabletop resting height: Y_base == table.height (tolerance <= 0.005 m)
      const yBase = p.position.y - p.size.h / 2;
      assert.ok(
        Math.abs(yBase - table.topY) <= 0.005,
        `${p.key} Y_base (${yBase}) must equal table.topY (${table.topY})`
      );

      // §3.1 Tabletop margin >= 0.10 m inside table footprint
      const margin = SPATIAL_RULES.tableEdgeMargin - 1e-4;
      const minTableX = table.position.x - table.size.w / 2 + margin;
      const maxTableX = table.position.x + table.size.w / 2 - margin;
      const minTableZ = table.position.z - table.size.d / 2 + margin;
      const maxTableZ = table.position.z + table.size.d / 2 - margin;

      assert.ok(aabb.minX >= minTableX && aabb.maxX <= maxTableX, `${p.key} violates X table margin`);
      assert.ok(aabb.minZ >= minTableZ && aabb.maxZ <= maxTableZ, `${p.key} violates Z table margin`);
    }
  }

  // §3.1 Non-collision: No two placed devices intersect in 3D AABB
  for (let i = 0; i < aabbs.length; i++) {
    for (let j = i + 1; j < aabbs.length; j++) {
      assert.equal(
        boxesOverlap3D(aabbs[i].aabb, aabbs[j].aabb),
        false,
        `3D AABB collision between ${aabbs[i].key} and ${aabbs[j].key}`
      );
    }
  }
}

test('T-01 & T-03: Centralized configuration exposes 9x3x7 room and U.S. lecture-style tables with >= 0.90m aisles', () => {
  assert.equal(ROOM_DIMENSIONS.width, 9.0);
  assert.equal(ROOM_DIMENSIONS.height, 3.0);
  assert.equal(ROOM_DIMENSIONS.depth, 7.0);

  for (const wallKey of ['front', 'back', 'left', 'right']) {
    assert.ok(WALL_DEFS[wallKey], `Missing wall definition: ${wallKey}`);
  }

  assert.equal(TABLE_LAYOUT.length, 10, 'Expected 1 instructor table + 9 student tables');
  const instructor = TABLE_LAYOUT.find((t) => t.role === 'instructor');
  assert.ok(instructor, 'Instructor table must exist');
  assert.ok(instructor.position.z < -1.5, 'Instructor table must be near front wall');

  const studentTables = TABLE_LAYOUT.filter((t) => t.role === 'student');
  assert.equal(studentTables.length, 9);

  // Verify circulation aisle clearance >= 0.90 m between distinct rows and distinct columns
  const rowZs = [...new Set(studentTables.map((t) => t.position.z))].sort((a, b) => a - b);
  const colXs = [...new Set(studentTables.map((t) => t.position.x))].sort((a, b) => a - b);
  const sampleStudent = studentTables[0];

  for (let i = 1; i < rowZs.length; i++) {
    const clearZ = rowZs[i] - rowZs[i - 1] - sampleStudent.size.d;
    assert.ok(clearZ >= 0.90 - 1e-6, `Row aisle clearance ${clearZ}m must be >= 0.90m`);
  }
  for (let i = 1; i < colXs.length; i++) {
    const clearX = colXs[i] - colXs[i - 1] - sampleStudent.size.w;
    assert.ok(clearX >= 0.90 - 1e-6, `Column aisle clearance ${clearX}m must be >= 0.90m`);
  }
});

test('S-01: Empty room renders only classroom shell and furniture with zero device placements', () => {
  const result = computeRoomLayout(SCENARIO_PRESETS['S-01']);
  assertGeometricInvariants(result);
  assert.equal(result.placements.length, 0);
  assert.equal(result.tables.length, 10);
});

test('S-02: Single TV is centered on the front wall facing inward (+Z) and flush with inner surface', () => {
  const result = computeRoomLayout(SCENARIO_PRESETS['S-02']);
  assertGeometricInvariants(result);
  assert.equal(result.placements.length, 1);

  const tv = result.placements[0];
  assert.equal(tv.type, 'television');
  assert.equal(tv.surface, 'front');
  assert.equal(tv.position.x, 0);
  assert.equal(tv.rotation.y, 0);
  assert.ok(Math.abs(tv.position.z - (-3.5 + tv.size.d / 2)) <= 0.01);
});

test('S-03: Four TVs are placed 1 on each of the 4 walls facing inward', () => {
  const result = computeRoomLayout(SCENARIO_PRESETS['S-03']);
  assertGeometricInvariants(result);
  assert.equal(result.placements.length, 4);

  const walls = result.placements.map((p) => p.surface);
  assert.deepEqual(new Set(walls), new Set(['front', 'back', 'left', 'right']));
});

test('S-04: Five TVs (or TV + AC > 4) are rejected with a visible BR-03 / BR-09 capacity error', () => {
  const result5Tv = computeRoomLayout(SCENARIO_PRESETS['S-04']);
  assert.ok(result5Tv.errors.length > 0, '5 TVs must be rejected');
  assert.ok(result5Tv.errors.some((e) => e.rule === 'BR-09' || e.rule === 'BR-03'));
  assert.equal(result5Tv.placements.length, 0);

  // Also verify exclusive wall constraint when 3 TVs + 2 ACs = 5 > 4 walls
  const resultMixedExcess = computeRoomLayout({
    room: { id: 'TEST-MIXED-5' },
    devices: [
      { type: 'television', quantity: 3 },
      { type: 'air_conditioner', quantity: 2 },
    ],
  });
  assert.ok(resultMixedExcess.errors.some((e) => e.rule === 'BR-03'));
  assert.equal(resultMixedExcess.placements.length, 0);
});

test('S-05: Two Air Conditioners are mounted high on opposite lateral walls (left & right), never sharing a wall with TV', () => {
  const result = computeRoomLayout(SCENARIO_PRESETS['S-05']);
  assertGeometricInvariants(result);
  assert.equal(result.placements.length, 2);

  const walls = result.placements.map((p) => p.surface);
  assert.deepEqual(walls, ['left', 'right']);
  for (const ac of result.placements) {
    assert.ok(ac.position.y >= 2.2, 'AC must be mounted high on wall');
  }

  // Combined 1 TV + 2 ACs: TV on front, ACs on left & right -> 3 distinct walls
  const combined = computeRoomLayout({
    room: { id: 'TEST-TV-AC' },
    devices: [
      { type: 'television', quantity: 1 },
      { type: 'air_conditioner', quantity: 2 },
    ],
  });
  assertGeometricInvariants(combined);
  assert.deepEqual(
    combined.placements.map((p) => `${p.type}@${p.surface}`),
    ['television@front', 'air_conditioner@left', 'air_conditioner@right']
  );
});

test('S-06: Six Outlets are distributed at low height (0.30m) across walls with >= 1.50m spacing on the same wall', () => {
  const result = computeRoomLayout(SCENARIO_PRESETS['S-06']);
  assertGeometricInvariants(result);
  assert.equal(result.placements.length, 6);

  const byWall = {};
  for (const outlet of result.placements) {
    assert.equal(outlet.position.y, 0.30);
    byWall[outlet.surface] = byWall[outlet.surface] || [];
    byWall[outlet.surface].push(outlet);
  }

  for (const list of Object.values(byWall)) {
    list.sort((a, b) => a.offsetX - b.offsetX);
    for (let i = 1; i < list.length; i++) {
      const dist = Math.abs(list[i].offsetX - list[i - 1].offsetX);
      assert.ok(dist >= 1.50 - 1e-4, `Outlet spacing ${dist}m must be >= 1.50m`);
    }
  }
});

test('S-07: Ten Computers are placed on table top surfaces with >= 0.10m edge margins and zero overlap', () => {
  const result = computeRoomLayout(SCENARIO_PRESETS['S-07']);
  assertGeometricInvariants(result);
  assert.equal(result.placements.length, 10);
  assert.ok(result.placements.every((p) => p.type === 'computer'));
});

test('S-08: Ten Computers + Two Printers place computers in main slots and printers at corner/end slots without overlap', () => {
  const result = computeRoomLayout(SCENARIO_PRESETS['S-08']);
  assertGeometricInvariants(result);
  assert.equal(result.placements.length, 12);

  const printers = result.placements.filter((p) => p.type === 'printer');
  assert.equal(printers.length, 2);
  for (const pr of printers) {
    assert.equal(pr.slotIndex, 3, 'Printers must be placed at corner/end slot (slotIndex 3)');
  }

  // Verify excess tabletop devices (> 40 slots) are rejected with BR-09 error
  const excessResult = computeRoomLayout({
    room: { id: 'TEST-EXCESS-PC' },
    devices: [
      { type: 'computer', quantity: 38 },
      { type: 'printer', quantity: 5 },
    ],
  });
  assert.ok(excessResult.errors.some((e) => e.rule === 'BR-09'));
});

test('S-09: Administrator override customizes device position and size and rejects out-of-bounds overrides', () => {
  const result = computeRoomLayout(SCENARIO_PRESETS['S-09']);
  assertGeometricInvariants(result);

  const tv = result.placements.find((p) => p.key === 'television:0');
  assert.ok(tv);
  assert.equal(tv.overridden, true);
  assert.equal(tv.surface, 'back');
  assert.equal(tv.offsetX, 1.25);
  assert.equal(tv.position.y, 1.85);
  assert.deepEqual(tv.size, { w: 1.50, h: 0.85, d: 0.10 });

  // Out-of-bounds override is rejected with BR-07 error
  const invalidOverride = computeRoomLayout({
    room: { id: 'TEST-BAD-OV' },
    devices: [{ type: 'television', quantity: 1 }],
    overrides: {
      'television:0': { wall: 'front', offsetX: 8.5, height: 1.8 },
    },
  });
  assert.ok(invalidOverride.errors.some((e) => e.rule === 'BR-07'));
});

test('S-10: Negative, NaN, or non-numeric quantities are rejected with BR-09 error; "Não identificável" maps to 0 with warning', () => {
  const negResult = computeRoomLayout(SCENARIO_PRESETS['S-10']);
  assert.ok(negResult.errors.length > 0, 'Negative quantity must produce a BR-09 validation error');
  assert.ok(negResult.errors.some((e) => e.rule === 'BR-09'));

  const nanResult = computeRoomLayout({
    room: { id: 'TEST-NAN' },
    devices: [{ type: 'computer', quantity: 'abc' }],
  });
  assert.ok(nanResult.errors.some((e) => e.rule === 'BR-09'));

  const unidentifiablePayload = normalizeRoomPayload({
    room: { id: 'TEST-UNID' },
    devices: [{ type: 'fan', quantity: 'Não identificável' }],
  });
  const unidResult = computeRoomLayout(unidentifiablePayload);
  assert.equal(unidResult.errors.length, 0);
  assert.equal(unidResult.placements.length, 0);
  assert.ok(unidResult.warnings.length > 0, 'Expected warning for Não identificável');
});

test('S-11: Unknown device type is ignored with a warning without crashing', () => {
  const result = computeRoomLayout(SCENARIO_PRESETS['S-11']);
  assertGeometricInvariants(result);
  assert.equal(result.placements.length, 5); // 1 TV + 4 outlets
  assert.ok(result.warnings.some((w) => w.code === 'S-11'));
});
