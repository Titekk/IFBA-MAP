/**
 * Pure Spatial Placement & Validation Engine (T-04, T-05, T-06, T-07, T-10).
 * Zero WebGL, Three.js, or DOM dependencies — 100% deterministic and Node.js testable.
 */

import {
  ROOM_DIMENSIONS,
  SPATIAL_RULES,
  WALL_DEFS,
  ARCHITECTURAL_FIXTURES,
  TABLE_LAYOUT,
  DEVICE_CATALOG,
  parseSurveyQuantity,
  normalizeRoomPayload,
} from './roomConfig.js';

/**
 * Computes an Axis-Aligned Bounding Box (AABB) in world coordinates for an object
 * centered at `position` with dimensions `size` and Y-axis rotation `rotationY`.
 */
export function computeWorldAABB(position, size, rotationY = 0) {
  const cos = Math.abs(Math.cos(rotationY));
  const sin = Math.abs(Math.sin(rotationY));
  const halfX = (size.w * cos + size.d * sin) / 2;
  const halfY = size.h / 2;
  const halfZ = (size.w * sin + size.d * cos) / 2;

  return {
    minX: position.x - halfX,
    maxX: position.x + halfX,
    minY: position.y - halfY,
    maxY: position.y + halfY,
    minZ: position.z - halfZ,
    maxZ: position.z + halfZ,
  };
}

/**
 * Checks whether two 3D AABBs intersect (with a tiny epsilon so flush surfaces do not false-trigger).
 */
export function boxesOverlap3D(boxA, boxB, epsilon = 1e-4) {
  return (
    boxA.minX < boxB.maxX - epsilon &&
    boxA.maxX > boxB.minX + epsilon &&
    boxA.minY < boxB.maxY - epsilon &&
    boxA.maxY > boxB.minY + epsilon &&
    boxA.minZ < boxB.maxZ - epsilon &&
    boxA.maxZ > boxB.minZ + epsilon
  );
}

/**
 * Computes world coordinates and rotation for a wall-mounted device so its back face
 * is flush against the wall's inner surface (`distance = d / 2`) and its front face
 * points along the wall's inward normal (BR-02, BR-04).
 */
export function computeWallMountTransform(wallId, offsetX, centerY, size) {
  const wall = WALL_DEFS[wallId];
  if (!wall) {
    throw new Error(`Invalid wall identifier: "${wallId}"`);
  }

  const halfDepth = size.d / 2;
  let x = 0;
  let z = 0;

  if (wall.axis === 'x') {
    x = offsetX;
    z = wall.innerCoord + wall.normal.z * halfDepth;
  } else {
    z = offsetX;
    x = wall.innerCoord + wall.normal.x * halfDepth;
  }

  return {
    position: {
      x: Number(x.toFixed(4)),
      y: Number(centerY.toFixed(4)),
      z: Number(z.toFixed(4)),
    },
    rotation: {
      x: 0,
      y: Number(wall.rotationY.toFixed(6)),
      z: 0,
    },
    normal: { ...wall.normal },
  };
}

/**
 * Validates whether a wall-mounted device stays strictly inside its wall boundaries.
 */
function validateWallBounds(wallId, offsetX, centerY, size) {
  const wall = WALL_DEFS[wallId];
  if (!wall) {
    return `Parede inválida "${wallId}". Use front, back, left ou right.`;
  }
  const halfSpan = wall.length / 2 - SPATIAL_RULES.wallCornerMargin;
  if (Math.abs(offsetX) + size.w / 2 > halfSpan + 1e-4) {
    return `Dispositivo excede os limites horizontais da parede "${wallId}" (offsetX=${offsetX}m, largura=${size.w}m).`;
  }
  if (centerY - size.h / 2 < 0.05 || centerY + size.h / 2 > wall.height - 0.02) {
    return `Dispositivo excede os limites verticais da parede "${wallId}" (altura=${centerY}m, h=${size.h}m).`;
  }
  return null;
}

/**
 * Parses and validates a device quantity according to BR-01, BR-09, and S-10.
 */
function parseDeviceQuantity(entry, errors, warnings) {
  const label = DEVICE_CATALOG[entry.type]?.labelPt || entry.type;

  if (entry.unidentifiable) {
    warnings.push({
      code: 'UNIDENTIFIABLE_COUNT',
      type: entry.type,
      message: `${label}: quantidade marcada como "Não identificável" no levantamento; 0 unidades renderizadas.`,
    });
    return 0;
  }

  const parsed = parseSurveyQuantity(entry.quantity);
  if (parsed.unidentifiable) {
    warnings.push({
      code: 'UNIDENTIFIABLE_COUNT',
      type: entry.type,
      message: `${label}: quantidade marcada como "Não identificável" no levantamento; 0 unidades renderizadas.`,
    });
    return 0;
  }

  if (!parsed.valid) {
    errors.push({
      rule: 'BR-09',
      code: 'S-10',
      type: entry.type,
      message: `Quantidade inválida para ${label} (${JSON.stringify(entry.rawField ?? entry.quantity)}). Informe um número inteiro não-negativo.`,
    });
    return 0;
  }

  return parsed.count;
}

/**
 * Resolves size `{ w, h, d }` for a device instance, applying Administrator overrides if present (BR-07).
 */
function resolveDeviceSize(catalogEntry, override, errors, deviceKey) {
  const base = catalogEntry.defaultSize;
  if (!override) return { ...base };

  const w = override.w !== undefined && override.w !== null ? Number(override.w) : base.w;
  const h = override.h !== undefined && override.h !== null ? Number(override.h) : base.h;
  const d = override.d !== undefined && override.d !== null ? Number(override.d) : base.d;

  if (!Number.isFinite(w) || !Number.isFinite(h) || !Number.isFinite(d) || w <= 0.02 || h <= 0.02 || d <= 0.01) {
    errors.push({
      rule: 'BR-07',
      code: 'INVALID_OVERRIDE_SIZE',
      deviceKey,
      message: `Dimensões de override inválidas para "${deviceKey}" (w=${w}, h=${h}, d=${d}).`,
    });
    return { ...base };
  }

  return { w, h, d };
}

/**
 * Pure placement function: takes a room payload and returns deterministic 3D placements,
 * furniture layout, validation errors, and warnings.
 */
export function computeRoomLayout(rawInput = {}) {
  const payload = normalizeRoomPayload(rawInput);
  const errors = [];
  const warnings = [];
  const placements = [];
  const overrides = payload.overrides || {};

  // 1. Validate device types (S-11) and quantities (BR-01, BR-09, S-10)
  const countsByType = {
    television: 0,
    air_conditioner: 0,
    outlet: 0,
    computer: 0,
    printer: 0,
    lamp: 0,
    fan: 0,
  };
  const metadataByType = {};
  const annotationsByType = {};

  for (const entry of payload.devices) {
    if (!entry || !entry.type) continue;
    const catalog = DEVICE_CATALOG[entry.type];
    if (!catalog) {
      warnings.push({
        code: 'S-11',
        type: entry.type,
        message: `Tipo de dispositivo desconhecido "${entry.type}" ignorado com segurança (S-11).`,
      });
      continue;
    }

    const qty = parseDeviceQuantity(entry, errors, warnings);
    countsByType[entry.type] += qty;
    if (Array.isArray(entry.items) && entry.items.length > 0) {
      metadataByType[entry.type] = entry.items;
    }
    if (entry.annotation) {
      annotationsByType[entry.type] = entry.annotation;
    }
  }

  // If invalid quantities were detected (S-10 / BR-09), return early with errors
  if (errors.length > 0) {
    return {
      room: payload.room,
      tables: TABLE_LAYOUT,
      fixtures: ARCHITECTURAL_FIXTURES,
      placements: [],
      errors,
      warnings,
    };
  }

  // 2. Place TVs and Air Conditioners with exclusive wall rule (BR-02, BR-03 Revised, BR-04, BR-09)
  const tvCount = countsByType.television;
  const acCount = countsByType.air_conditioner;

  if (tvCount > SPATIAL_RULES.maxWallTvAcCombined) {
    errors.push({
      rule: 'BR-09',
      code: 'TV_CAPACITY_EXCEEDED',
      type: 'television',
      message: `Capacidade excedida (BR-03/BR-09): ${tvCount} televisores solicitados, mas a sala possui apenas 4 paredes (máximo 1 TV por parede).`,
    });
  }
  if (acCount > SPATIAL_RULES.maxWallTvAcCombined) {
    errors.push({
      rule: 'BR-09',
      code: 'AC_CAPACITY_EXCEEDED',
      type: 'air_conditioner',
      message: `Capacidade excedida (BR-03/BR-09): ${acCount} aparelhos de ar-condicionado solicitados, mas o limite é 1 por parede.`,
    });
  }
  if (tvCount + acCount > SPATIAL_RULES.maxWallTvAcCombined) {
    errors.push({
      rule: 'BR-03',
      code: 'TV_AC_EXCLUSIVE_WALL_EXCEEDED',
      type: 'television/air_conditioner',
      message: `Conflito de paredes exclusivas (BR-03/BR-09): ${tvCount} TV(s) + ${acCount} Ar-Condicionado(s) = ${tvCount + acCount} unidades. TV e Ar-Condicionado não podem compartilhar a mesma parede (máximo combinado: 4).`,
    });
  }

  if (errors.length === 0) {
    const occupiedTvAcWalls = new Map(); // wallId -> deviceKey

    // Reserve walls explicitly requested by Admin Overrides first (BR-07)
    const wallDevicesQueue = [
      ...Array.from({ length: tvCount }, (_, i) => ({
        type: 'television',
        index: i,
        key: `television:${i}`,
        pref: SPATIAL_RULES.tvWallPreference,
      })),
      ...Array.from({ length: acCount }, (_, i) => ({
        type: 'air_conditioner',
        index: i,
        key: `air_conditioner:${i}`,
        pref: SPATIAL_RULES.acWallPreference,
      })),
    ];

    for (const item of wallDevicesQueue) {
      const ov = overrides[item.key];
      if (ov?.wall) {
        if (!WALL_DEFS[ov.wall]) {
          errors.push({
            rule: 'BR-07',
            code: 'INVALID_OVERRIDE_WALL',
            deviceKey: item.key,
            message: `Parede de override inválida "${ov.wall}" para ${item.key}.`,
          });
        } else if (occupiedTvAcWalls.has(ov.wall)) {
          errors.push({
            rule: 'BR-03',
            code: 'OVERRIDE_WALL_CONFLICT',
            deviceKey: item.key,
            message: `Conflito BR-03 no override de ${item.key}: a parede "${ov.wall}" já está ocupada por ${occupiedTvAcWalls.get(ov.wall)}.`,
          });
        } else {
          occupiedTvAcWalls.set(ov.wall, item.key);
        }
      }
    }

    for (const item of wallDevicesQueue) {
      if (errors.length > 0) break;
      const catalog = DEVICE_CATALOG[item.type];
      const ov = overrides[item.key];
      const size = resolveDeviceSize(catalog, ov, errors, item.key);

      let chosenWall = ov?.wall || null;
      if (!chosenWall) {
        chosenWall = item.pref.find((w) => !occupiedTvAcWalls.has(w)) || null;
        if (chosenWall) {
          occupiedTvAcWalls.set(chosenWall, item.key);
        }
      }

      if (!chosenWall) {
        errors.push({
          rule: 'BR-03',
          code: 'NO_AVAILABLE_WALL',
          deviceKey: item.key,
          message: `Nenhuma parede livre disponível para posicionar ${item.key} sem violar BR-03.`,
        });
        break;
      }

      const offsetX = ov?.offsetX !== undefined && ov?.offsetX !== null ? Number(ov.offsetX) : 0.0;
      const centerY =
        ov?.height !== undefined && ov?.height !== null
          ? Number(ov.height)
          : catalog.defaultHeight;

      const boundErr = validateWallBounds(chosenWall, offsetX, centerY, size);
      if (boundErr) {
        errors.push({
          rule: 'BR-07',
          code: 'WALL_BOUNDS_VIOLATION',
          deviceKey: item.key,
          message: boundErr,
        });
        break;
      }

      const transform = computeWallMountTransform(chosenWall, offsetX, centerY, size);
      const itemMeta = metadataByType[item.type]?.[item.index] || (annotationsByType[item.type] ? { marca: annotationsByType[item.type] } : null);
      const brandSuffix = itemMeta?.marca && itemMeta.marca !== 'Não identificável'
        ? ` (${itemMeta.marca}${itemMeta.capacidade_btu ? ' · ' + itemMeta.capacidade_btu + ' BTUs' : ''})`
        : annotationsByType[item.type]
        ? ` (${annotationsByType[item.type]})`
        : '';

      placements.push({
        key: item.key,
        type: item.type,
        labelPt: `${catalog.labelPt} #${item.index + 1}${brandSuffix}`,
        mountType: 'wall',
        surface: chosenWall,
        offsetX,
        position: transform.position,
        rotation: transform.rotation,
        normal: transform.normal,
        size,
        overridden: Boolean(ov),
        meta: itemMeta,
      });
    }
  }

  // 3. Place Electrical Outlets across walls with >= 1.50 m spacing per rail (BR-05, T-06, BR-09)
  const outletCount = countsByType.outlet;
  if (outletCount > 0 && errors.length === 0) {
    const catalog = DEVICE_CATALOG.outlet;
    const railHeights = SPATIAL_RULES.outletRailHeights;

    // Pre-calculate valid horizontal coordinates per wall spaced >= 1.50 m apart
    // Avoid the left-wall door span (z in [2.15, 3.30])
    const wallHorizontalCoords = {};
    for (const wallId of SPATIAL_RULES.outletWallOrder) {
      const wall = WALL_DEFS[wallId];
      const usableHalf = wall.length / 2 - SPATIAL_RULES.wallCornerMargin - catalog.defaultSize.w / 2;
      const coords = [];
      for (let pos = -usableHalf; pos <= usableHalf + 1e-6; pos += SPATIAL_RULES.minOutletSpacing) {
        const rounded = Number(pos.toFixed(4));
        if (wallId === 'left' && rounded >= 2.15) continue; // Keep clear of door frame
        coords.push(rounded);
      }
      wallHorizontalCoords[wallId] = coords;
    }

    // Build ordered pool of (wallId, railY, offsetX) stations:
    // Fill primary rail (Y = 0.30 m) round-robin across walls first, then secondary rail (Y = 0.55 m)
    const outletStations = [];
    for (const railY of railHeights) {
      const maxSlotsOnAnyWall = Math.max(
        ...Object.values(wallHorizontalCoords).map((arr) => arr.length)
      );
      for (let slotIdx = 0; slotIdx < maxSlotsOnAnyWall; slotIdx++) {
        for (const wallId of SPATIAL_RULES.outletWallOrder) {
          const coords = wallHorizontalCoords[wallId];
          if (slotIdx < coords.length) {
            outletStations.push({
              wallId,
              railY,
              offsetX: coords[slotIdx],
            });
          }
        }
      }
    }

    if (outletCount > outletStations.length) {
      errors.push({
        rule: 'BR-09',
        code: 'OUTLET_CAPACITY_EXCEEDED',
        type: 'outlet',
        message: `Capacidade de tomadas excedida (BR-05/BR-09): ${outletCount} tomadas solicitadas, mas as paredes comportam no máximo ${outletStations.length} tomadas respeitando o espaçamento mínimo de ${SPATIAL_RULES.minOutletSpacing} m.`,
      });
    } else {
      for (let outletIdx = 0; outletIdx < outletCount; outletIdx++) {
        const station = outletStations[outletIdx];
        const key = `outlet:${outletIdx}`;
        const ov = overrides[key];
        const size = resolveDeviceSize(catalog, ov, errors, key);
        const targetWall = ov?.wall || station.wallId;
        const offsetX =
          ov?.offsetX !== undefined && ov?.offsetX !== null
            ? Number(ov.offsetX)
            : station.offsetX;
        const centerY =
          ov?.height !== undefined && ov?.height !== null
            ? Number(ov.height)
            : station.railY;

        const boundErr = validateWallBounds(targetWall, offsetX, centerY, size);
        if (boundErr) {
          errors.push({
            rule: 'BR-07',
            code: 'OUTLET_BOUNDS_VIOLATION',
            deviceKey: key,
            message: boundErr,
          });
          break;
        }

        const transform = computeWallMountTransform(targetWall, offsetX, centerY, size);
        placements.push({
          key,
          type: 'outlet',
          labelPt: `${catalog.labelPt} #${outletIdx + 1}`,
          mountType: 'wall',
          surface: targetWall,
          offsetX,
          position: transform.position,
          rotation: transform.rotation,
          normal: transform.normal,
          size,
          overridden: Boolean(ov),
          meta: null,
        });
      }

      // Verify BR-05 minimum 1.50 m horizontal spacing on the same wall & rail height
      const outletsByWallRail = {};
      for (const p of placements.filter((x) => x.type === 'outlet')) {
        const railKey = `${p.surface}:${p.position.y.toFixed(2)}`;
        outletsByWallRail[railKey] = outletsByWallRail[railKey] || [];
        outletsByWallRail[railKey].push(p);
      }
      for (const [railKey, list] of Object.entries(outletsByWallRail)) {
        list.sort((a, b) => a.offsetX - b.offsetX);
        for (let i = 1; i < list.length; i++) {
          const dist = Math.abs(list[i].offsetX - list[i - 1].offsetX);
          if (dist < SPATIAL_RULES.minOutletSpacing - 1e-4) {
            errors.push({
              rule: 'BR-05',
              code: 'OUTLET_SPACING_VIOLATION',
              deviceKey: list[i].key,
              message: `Violação BR-05 em "${railKey}": tomadas ${list[i - 1].key} e ${list[i].key} estão a ${dist.toFixed(2)} m (mínimo exigido: ${SPATIAL_RULES.minOutletSpacing.toFixed(2)} m).`,
            });
          }
        }
      }
    }
  }

  // 4. Place Tabletop Devices: Computers & Printers (BR-06, T-07, BR-09)
  const computerCount = countsByType.computer;
  const printerCount = countsByType.printer;
  const totalTabletop = computerCount + printerCount;

  if (totalTabletop > 0 && errors.length === 0) {
    // Build flat pool of all table slots (10 tables * 4 slots = 40 slots)
    const allSlots = TABLE_LAYOUT.flatMap((table) =>
      table.slots.map((slot) => ({
        table,
        slot,
        slotKey: `${table.id}:${slot.slotIndex}`,
      }))
    );

    if (totalTabletop > allSlots.length) {
      errors.push({
        rule: 'BR-09',
        code: 'TABLETOP_CAPACITY_EXCEEDED',
        type: 'computer/printer',
        message: `Capacidade de bancadas excedida (BR-06/BR-09): ${computerCount} computador(es) + ${printerCount} impressora(s) = ${totalTabletop} dispositivos, mas as ${TABLE_LAYOUT.length} mesas possuem no máximo ${allSlots.length} posições válidas sem sobreposição.`,
      });
    } else {
      const usedSlotKeys = new Set();

      // Helper to validate tabletop edge margin >= 0.10 m (BR-06)
      const validateTableMargin = (table, localX, localZ, size, deviceKey) => {
        const margin = SPATIAL_RULES.tableEdgeMargin;
        const maxHalfW = table.size.w / 2 - margin;
        const maxHalfD = table.size.d / 2 - margin;
        if (Math.abs(localX) + size.w / 2 > maxHalfW + 1e-4 || Math.abs(localZ) + size.d / 2 > maxHalfD + 1e-4) {
          errors.push({
            rule: 'BR-06',
            code: 'TABLE_MARGIN_VIOLATION',
            deviceKey,
            message: `Violação BR-06 em ${deviceKey} na mesa "${table.id}": o dispositivo não respeita a margem interna mínima de ${margin} m das bordas da mesa.`,
          });
          return false;
        }
        return true;
      };

      // Reserve right-end corner slots (`isRightEnd: true`, slotIndex === 3) for printers (BR-06)
      const printerPreferredSlots = [
        ...allSlots.filter((s) => s.slot.isRightEnd),
        ...allSlots.filter((s) => !s.slot.isRightEnd),
      ];
      // Spread computers cleanly across student tables first (inner slots 1 & 2, then left slot 0, then instructor, then remaining end slots)
      const computerPreferredSlots = [
        ...allSlots.filter((s) => s.table.role === 'student' && (s.slot.slotIndex === 1 || s.slot.slotIndex === 2)),
        ...allSlots.filter((s) => s.table.role === 'student' && s.slot.slotIndex === 0),
        ...allSlots.filter((s) => s.table.role === 'instructor' && s.slot.slotIndex !== 3),
        ...allSlots.filter((s) => s.slot.isRightEnd),
      ];

      // Reserve explicitly overridden table slots first
      const tabletopQueue = [
        ...Array.from({ length: printerCount }, (_, i) => ({
          type: 'printer',
          index: i,
          key: `printer:${i}`,
          pool: printerPreferredSlots,
        })),
        ...Array.from({ length: computerCount }, (_, i) => ({
          type: 'computer',
          index: i,
          key: `computer:${i}`,
          pool: computerPreferredSlots,
        })),
      ];

      for (const item of tabletopQueue) {
        const ov = overrides[item.key];
        if (ov?.tableId !== undefined && ov?.slotIndex !== undefined) {
          const sk = `${ov.tableId}:${Number(ov.slotIndex)}`;
          usedSlotKeys.add(sk);
        }
      }

      for (const item of tabletopQueue) {
        if (errors.length > 0) break;
        const catalog = DEVICE_CATALOG[item.type];
        const ov = overrides[item.key];
        const size = resolveDeviceSize(catalog, ov, errors, item.key);

        let chosen = null;
        if (ov?.tableId) {
          const targetSlotIdx = ov.slotIndex !== undefined ? Number(ov.slotIndex) : 0;
          chosen = allSlots.find(
            (s) => s.table.id === ov.tableId && s.slot.slotIndex === targetSlotIdx
          );
          if (!chosen) {
            errors.push({
              rule: 'BR-07',
              code: 'INVALID_TABLE_OVERRIDE',
              deviceKey: item.key,
              message: `Posição de mesa inválida no override de ${item.key} (tableId="${ov.tableId}", slotIndex=${targetSlotIdx}).`,
            });
            break;
          }
        } else {
          chosen = item.pool.find((s) => !usedSlotKeys.has(s.slotKey)) || null;
          if (chosen) {
            usedSlotKeys.add(chosen.slotKey);
          }
        }

        if (!chosen) {
          errors.push({
            rule: 'BR-09',
            code: 'NO_TABLE_SLOT_AVAILABLE',
            deviceKey: item.key,
            message: `Nenhum slot de mesa disponível para ${item.key}.`,
          });
          break;
        }

        const localX =
          ov?.offsetX !== undefined && ov?.offsetX !== null
            ? Number(ov.offsetX)
            : chosen.slot.localX;
        const localZ = chosen.slot.localZ;

        if (!validateTableMargin(chosen.table, localX, localZ, size, item.key)) {
          break;
        }

        const centerY = Number((chosen.table.topY + size.h / 2).toFixed(4));
        const worldX = Number((chosen.table.position.x + localX).toFixed(4));
        const worldZ = Number((chosen.table.position.z + localZ).toFixed(4));

        placements.push({
          key: item.key,
          type: item.type,
          labelPt: `${catalog.labelPt} #${item.index + 1}`,
          mountType: 'tabletop',
          surface: chosen.table.id,
          tableId: chosen.table.id,
          slotIndex: chosen.slot.slotIndex,
          offsetX: localX,
          position: { x: worldX, y: centerY, z: worldZ },
          rotation: { x: 0, y: chosen.table.rotationY, z: 0 },
          size,
          overridden: Boolean(ov),
          meta: null,
        });
      }
    }
  }

  // 5. Place Ceiling Fixtures: Lamps & Fans (D-06, renders exact database quantities)
  // Lamps sit flush at Y = 2.975 m (Y in [2.95, 3.00]), while Fans are suspended at Y = 2.74 m (Y in [2.62, 2.86]),
  // so their vertical planes are disjoint and never collide in 3D AABB even in 36-lamp classrooms.
  const lampCount = countsByType.lamp;
  if (lampCount > 0 && errors.length === 0) {
    const catalog = DEVICE_CATALOG.lamp;
    const cols = Math.min(6, Math.max(1, Math.ceil(Math.sqrt(lampCount * (ROOM_DIMENSIONS.width / ROOM_DIMENSIONS.depth)))));
    const rows = Math.ceil(lampCount / cols);
    let idx = 0;
    for (let r = 0; r < rows && idx < lampCount; r++) {
      const z = rows === 1 ? 0 : -2.65 + (r * 5.30) / (rows - 1);
      for (let c = 0; c < cols && idx < lampCount; c++) {
        const x = cols === 1 ? 0 : -3.50 + (c * 7.00) / (cols - 1);
        const key = `lamp:${idx}`;
        const size = { ...catalog.defaultSize };
        placements.push({
          key,
          type: 'lamp',
          labelPt: `${catalog.labelPt} #${idx + 1}`,
          mountType: 'ceiling',
          surface: 'ceiling',
          position: {
            x: Number(x.toFixed(4)),
            y: Number(catalog.defaultHeight.toFixed(4)),
            z: Number(z.toFixed(4)),
          },
          rotation: { x: 0, y: 0, z: 0 },
          size,
          overridden: false,
          meta: null,
        });
        idx++;
      }
    }
  }

  const fanCount = countsByType.fan;
  if (fanCount > 0 && errors.length === 0) {
    const catalog = DEVICE_CATALOG.fan;
    const cols = Math.min(3, Math.max(1, Math.ceil(Math.sqrt(fanCount))));
    const rows = Math.ceil(fanCount / cols);
    let idx = 0;
    for (let r = 0; r < rows && idx < fanCount; r++) {
      const z = rows === 1 ? 0 : -1.60 + (r * 3.20) / (rows - 1);
      for (let c = 0; c < cols && idx < fanCount; c++) {
        const x = cols === 1 ? 0 : -2.20 + (c * 4.40) / (cols - 1);
        const key = `fan:${idx}`;
        const size = { ...catalog.defaultSize };
        placements.push({
          key,
          type: 'fan',
          labelPt: `${catalog.labelPt} #${idx + 1}`,
          mountType: 'ceiling',
          surface: 'ceiling',
          position: {
            x: Number(x.toFixed(4)),
            y: Number(catalog.defaultHeight.toFixed(4)),
            z: Number(z.toFixed(4)),
          },
          rotation: { x: 0, y: 0, z: 0 },
          size,
          overridden: false,
          meta: null,
        });
        idx++;
      }
    }
  }

  // 6. Global 3D AABB Non-Collision Check across all placed devices (§3.1)
  if (errors.length === 0) {
    const boxes = placements.map((p) => ({
      placement: p,
      aabb: computeWorldAABB(p.position, p.size, p.rotation.y),
    }));

    for (let i = 0; i < boxes.length; i++) {
      for (let j = i + 1; j < boxes.length; j++) {
        if (boxesOverlap3D(boxes[i].aabb, boxes[j].aabb)) {
          errors.push({
            rule: 'BR-07',
            code: 'AABB_COLLISION',
            deviceKey: `${boxes[i].placement.key} <-> ${boxes[j].placement.key}`,
            message: `Colisão física detectada entre "${boxes[i].placement.key}" e "${boxes[j].placement.key}". Ajuste o posicionamento para evitar sobreposição 3D.`,
          });
        }
      }
    }
  }

  return {
    room: payload.room,
    tables: TABLE_LAYOUT,
    fixtures: ARCHITECTURAL_FIXTURES,
    placements: errors.length > 0 ? [] : placements,
    errors,
    warnings,
  };
}
