/**
 * UI Controller & Modal Orchestrator for the 3D Classroom Visualizer (T-10, T-11).
 * Bridges `index.php` room data & `crud.php` persistence with `placementEngine.js` and `classroomRenderer3D.js`.
 */

import {
  WALL_DEFS,
  TABLE_LAYOUT,
  DEVICE_CATALOG,
  SCENARIO_PRESETS,
  normalizeRoomPayload,
  buildPayloadFromDbRoom,
} from './roomConfig.js';
import { computeRoomLayout } from './placementEngine.js';
import { mountClassroom3D } from './classroomRenderer3D.js';

export { buildPayloadFromDbRoom };

let rendererController = null;
let activeRoomName = null;
let activeRoomDbData = null;
let activePayload = null;
let activeLayout = null;
let activeSelectedPlacement = null;
let currentMode = 'db'; // 'db' or scenario ID 'S-01'..'S-11'

const esc = (v) =>
  String(v ?? '').replace(/[&<>"']/g, (c) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  }[c]));

function renderAlertsBanner(layout) {
  const alertBox = document.getElementById('v3dAlerts');
  if (!alertBox) return;

  const html = [];
  for (const err of layout.errors || []) {
    html.push(
      `<div class="v3d-banner err" role="alert">
        <strong>Erro de Regra (${esc(err.rule)}):</strong> ${esc(err.message)}
      </div>`
    );
  }
  // Only display prominent viewport banners for S-11 (unknown device types);
  // 'Não identificável' fields are shown cleanly in the sidebar summary pills.
  for (const warn of layout.warnings || []) {
    if (warn.code === 'UNIDENTIFIABLE_COUNT') continue;
    html.push(
      `<div class="v3d-banner warn" role="status">
        <strong>Aviso (${esc(warn.code)}):</strong> ${esc(warn.message)}
      </div>`
    );
  }
  alertBox.innerHTML = html.join('');
}

function renderSummaryPills(layout) {
  const pillBox = document.getElementById('v3dSummaryPills');
  if (!pillBox) return;

  const counts = {};
  for (const p of layout.placements || []) {
    counts[p.type] = (counts[p.type] || 0) + 1;
  }

  const rawByType = {};
  for (const d of activePayload?.devices || []) {
    rawByType[d.type] = d.rawField;
  }

  const order = ['television', 'air_conditioner', 'outlet', 'computer', 'printer', 'lamp', 'fan'];
  pillBox.innerHTML = order
    .map((type) => {
      const cat = DEVICE_CATALOG[type];
      const n = counts[type] || 0;
      const rawTxt = rawByType[type] !== undefined ? String(rawByType[type]) : '0';
      const isNi = /não\s*identific/i.test(rawTxt);
      const subTxt = isNi ? 'Tabela: Não ident.' : `Tabela: ${rawTxt}`;
      return `<div class="v3d-pill ${n > 0 ? 'on' : ''}" title="${esc(cat.labelPt)} (${esc(subTxt)})">
        <div>
          <span style="display:block;font-weight:500">${esc(cat.labelPt)}</span>
          <small style="font-size:.66rem;color:var(--mut)">${esc(subTxt)}</small>
        </div>
        <b>${n}</b>
      </div>`;
    })
    .join('');

  // Render clickable device list for quick keyboard/mouse inspection
  const listEl = document.getElementById('v3dDeviceList');
  if (!listEl) return;
  if (!layout.placements.length) {
    listEl.innerHTML = `<div class="v3d-empty">Nenhum dispositivo posicionado nesta cena.</div>`;
    return;
  }

  listEl.innerHTML = layout.placements
    .map((p) => {
      const isSel = activeSelectedPlacement?.key === p.key;
      const surfaceLabel =
        p.mountType === 'wall'
          ? WALL_DEFS[p.surface]?.labelPt || p.surface
          : p.mountType === 'tabletop'
          ? `Mesa ${p.tableId} (Posição ${p.slotIndex + 1})`
          : 'Teto';
      return `<button type="button" class="v3d-dev-item ${isSel ? 'sel' : ''}" data-devkey="${esc(p.key)}">
        <div>
          <strong>${esc(p.labelPt)}</strong>
          <small>${esc(surfaceLabel)}${p.overridden ? ' / Editado' : ''}</small>
        </div>
        <code>${p.position.x.toFixed(2)}, ${p.position.y.toFixed(2)}, ${p.position.z.toFixed(2)}</code>
      </button>`;
    })
    .join('');
}

function renderInspectorPanel() {
  const box = document.getElementById('v3dInspectorBody');
  if (!box) return;

  const p = activeSelectedPlacement;
  if (!p) {
    box.innerHTML = `<div class="v3d-empty">Clique em qualquer equipamento no modelo 3D ou na lista acima para inspecionar suas coordenadas métricas e ajustar o posicionamento (Override).</div>`;
    return;
  }

  const isAdmin = Boolean(window.ADMIN);
  const wallOpts = Object.values(WALL_DEFS)
    .map(
      (w) =>
        `<option value="${w.id}" ${p.surface === w.id ? 'selected' : ''}>${esc(w.labelPt)}</option>`
    )
    .join('');

  const tableOpts = TABLE_LAYOUT.map(
    (t) =>
      `<option value="${t.id}" ${p.tableId === t.id ? 'selected' : ''}>${esc(t.labelPt)}</option>`
  ).join('');

  const slotLabels = ['Esquerda', 'Centro-Esq', 'Centro-Dir', 'Canto Direito'];
  const slotOpts = [0, 1, 2, 3]
    .map(
      (idx) =>
        `<option value="${idx}" ${p.slotIndex === idx ? 'selected' : ''}>Slot #${idx + 1} (${slotLabels[idx]})</option>`
    )
    .join('');

  box.innerHTML = `
    <div class="v3d-telemetry">
      <div><span>Identificador</span><code>${esc(p.key)}</code></div>
      <div><span>Equipamento</span><b>${esc(p.labelPt)}</b></div>
      <div><span>Montagem</span><b>${esc(p.mountType.toUpperCase())}</b></div>
      <div><span>Coordenadas (X, Y, Z)</span><code>${p.position.x.toFixed(2)}m, ${p.position.y.toFixed(2)}m, ${p.position.z.toFixed(2)}m</code></div>
      <div><span>Dimensões (L × A × P)</span><code>${p.size.w.toFixed(2)}m × ${p.size.h.toFixed(2)}m × ${p.size.d.toFixed(2)}m</code></div>
      <div><span>Rotação Y</span><code>${((p.rotation.y * 180) / Math.PI).toFixed(0)}°</code></div>
    </div>

    ${
      isAdmin && (p.mountType === 'wall' || p.mountType === 'tabletop')
        ? `
      <form id="v3dOverrideForm" class="v3d-ov-form">
        <h5>Ajuste Manual do Administrador (BR-07)</h5>
        <input type="hidden" name="device_key" value="${esc(p.key)}">
        <input type="hidden" name="device_type" value="${esc(p.type)}">
        ${
          p.mountType === 'wall'
            ? `
          <div class="grid2">
            <div>
              <label for="ovWall">Parede de Montagem</label>
              <select id="ovWall" name="wall">${wallOpts}</select>
            </div>
            <div>
              <label for="ovOffset">Deslocamento &amp; Altura (m)</label>
              <div class="grid2">
                <div class="spline-axis-field">
                  <span class="spline-axis-tag">X</span>
                  <input id="ovOffset" type="number" step="0.05" name="offset_x" value="${Number(p.offsetX ?? 0).toFixed(2)}" title="Offset Horizontal X (m)">
                </div>
                <div class="spline-axis-field">
                  <span class="spline-axis-tag">Y</span>
                  <input id="ovHeight" type="number" step="0.05" name="height_y" value="${Number(p.position.y).toFixed(2)}" title="Altura do Centro Y (m)">
                </div>
              </div>
            </div>
          </div>`
            : `
          <div class="grid2">
            <div>
              <label for="ovTable">Bancada / Mesa</label>
              <select id="ovTable" name="table_id">${tableOpts}</select>
            </div>
            <div>
              <label for="ovSlot">Posição na Mesa</label>
              <select id="ovSlot" name="slot_index">${slotOpts}</select>
            </div>
          </div>`
        }
        <label>Dimensões Físicas em Metros (Largura × Altura × Profundidade)</label>
        <div class="grid3">
          <div class="spline-axis-field">
            <span class="spline-axis-tag">W</span>
            <input id="ovW" type="number" step="0.02" min="0.05" name="width_w" value="${p.size.w.toFixed(2)}" aria-label="Largura W em metros">
          </div>
          <div class="spline-axis-field">
            <span class="spline-axis-tag">H</span>
            <input id="ovH" type="number" step="0.02" min="0.05" name="height_h" value="${p.size.h.toFixed(2)}" aria-label="Altura H em metros">
          </div>
          <div class="spline-axis-field">
            <span class="spline-axis-tag">D</span>
            <input id="ovD" type="number" step="0.02" min="0.02" name="depth_d" value="${p.size.d.toFixed(2)}" aria-label="Profundidade D em metros">
          </div>
        </div>
        <div class="v3d-ov-actions">
          <button type="submit" class="btn sm">Aplicar &amp; Salvar Override</button>
          <button type="button" class="btn sec sm" id="btnResetSingleOv">Restaurar Padrão</button>
        </div>
      </form>`
        : `<p style="font-size:.76rem;color:var(--mut);margin:8px 0 0">Perfil atual (${esc(window.CARGO)}): visualização e inspeção habilitadas.</p>`
    }
  `;
}

function applyAndRenderCurrentPayload() {
  activeLayout = computeRoomLayout(activePayload);
  if (rendererController) {
    rendererController.renderLayout(activeLayout);
  }

  // Keep selected device in sync
  if (activeSelectedPlacement) {
    activeSelectedPlacement =
      activeLayout.placements.find((p) => p.key === activeSelectedPlacement.key) || null;
  }
  if (!activeSelectedPlacement && activeLayout.placements.length > 0) {
    activeSelectedPlacement = activeLayout.placements[0];
  }

  renderAlertsBanner(activeLayout);
  renderSummaryPills(activeLayout);
  renderInspectorPanel();
}

async function fetchFreshRoomData(codigoSala) {
  const res = await fetch(`crud.php?acao=sala&id=${encodeURIComponent(codigoSala)}`);
  const data = await res.json();
  if (data.erro) throw new Error(data.erro);
  return data.sala;
}

export async function abrirVisualizador3D(nomeSala) {
  const modal = document.getElementById('modal3D');
  const viewport = document.getElementById('v3dViewport');
  if (!modal || !viewport) return;

  activeRoomName = nomeSala;
  const localSala = window.SALAS?.[nomeSala];
  if (!localSala) return;

  modal.classList.add('open');
  const blocoNome = window.BLOCOS?.[localSala.codigo_bloco]?.nome || '';
  document.getElementById('v3dRoomTitle').textContent = blocoNome
    ? `${nomeSala} / ${blocoNome}`
    : nomeSala;

  if (!rendererController) {
    rendererController = mountClassroom3D(viewport, {
      onSelectDevice(placement) {
        activeSelectedPlacement = placement;
        renderSummaryPills(activeLayout);
        renderInspectorPanel();
      },
    });
  } else {
    rendererController.handleResize();
  }

  // Fetch latest room quantities + overrides from MySQL
  try {
    activeRoomDbData = await fetchFreshRoomData(localSala.codigo_sala);
    if (window.SALAS?.[nomeSala]) {
      Object.assign(window.SALAS[nomeSala], activeRoomDbData);
    }
  } catch {
    activeRoomDbData = localSala;
  }

  currentMode = 'db';
  const selectEl = document.getElementById('v3dScenarioSelect');
  if (selectEl) selectEl.value = 'db';

  activePayload = buildPayloadFromDbRoom(
    activeRoomDbData,
    window.BLOCOS?.[localSala.codigo_bloco]?.nome || ''
  );
  activeSelectedPlacement = null;
  applyAndRenderCurrentPayload();
}

export function fecharVisualizador3D() {
  const modal = document.getElementById('modal3D');
  if (modal) modal.classList.remove('open');
}

// Wire global UI events once DOM is ready
window.abrirVisualizador3D = abrirVisualizador3D;
window.fecharVisualizador3D = fecharVisualizador3D;

document.addEventListener('DOMContentLoaded', () => {
  const scenarioSelect = document.getElementById('v3dScenarioSelect');
  if (scenarioSelect) {
    scenarioSelect.addEventListener('change', () => {
      const val = scenarioSelect.value;
      currentMode = val;
      activeSelectedPlacement = null;
      if (val === 'db' && activeRoomDbData) {
        activePayload = buildPayloadFromDbRoom(activeRoomDbData);
      } else if (SCENARIO_PRESETS[val]) {
        activePayload = normalizeRoomPayload(SCENARIO_PRESETS[val]);
      }
      applyAndRenderCurrentPayload();
    });
  }

  document.querySelectorAll('[data-cam-preset]').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-cam-preset]').forEach((b) => b.classList.toggle('on', b === btn));
      if (rendererController) {
        rendererController.setCameraPreset(btn.dataset.camPreset);
      }
    });
  });

  const devList = document.getElementById('v3dDeviceList');
  if (devList) {
    devList.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-devkey]');
      if (!btn || !rendererController) return;
      rendererController.selectDevice(btn.dataset.devkey);
    });
  }

  const inspectorBody = document.getElementById('v3dInspectorBody');
  if (inspectorBody) {
    inspectorBody.addEventListener('submit', async (e) => {
      if (e.target.id !== 'v3dOverrideForm') return;
      e.preventDefault();
      const fd = new FormData(e.target);
      const devKey = fd.get('device_key');

      const newOv = {
        wall: fd.get('wall') || undefined,
        offsetX: fd.get('offset_x') !== null && fd.get('offset_x') !== '' ? Number(fd.get('offset_x')) : undefined,
        height: fd.get('height_y') !== null && fd.get('height_y') !== '' ? Number(fd.get('height_y')) : undefined,
        tableId: fd.get('table_id') || undefined,
        slotIndex: fd.get('slot_index') !== null && fd.get('slot_index') !== '' ? Number(fd.get('slot_index')) : undefined,
        w: Number(fd.get('width_w')),
        h: Number(fd.get('height_h')),
        d: Number(fd.get('depth_d')),
      };

      // Test override in pure engine first before saving to DB
      const candidatePayload = {
        ...activePayload,
        overrides: {
          ...(activePayload.overrides || {}),
          [devKey]: newOv,
        },
      };
      const testResult = computeRoomLayout(candidatePayload);
      activePayload = candidatePayload;
      applyAndRenderCurrentPayload();

      if (testResult.errors.length === 0 && currentMode === 'db' && activeRoomDbData?.codigo_sala) {
        fd.append('codigo_sala', activeRoomDbData.codigo_sala);
        fd.append('acao', 'salvar_override_3d');
        fd.append('csrf', window.CSRF);
        await fetch('crud.php', { method: 'POST', body: fd });
      }
    });

    inspectorBody.addEventListener('click', async (e) => {
      if (e.target.id !== 'btnResetSingleOv' || !activeSelectedPlacement) return;
      const devKey = activeSelectedPlacement.key;
      const nextOverrides = { ...(activePayload.overrides || {}) };
      delete nextOverrides[devKey];
      activePayload = { ...activePayload, overrides: nextOverrides };
      applyAndRenderCurrentPayload();

      if (currentMode === 'db' && activeRoomDbData?.codigo_sala) {
        const fd = new FormData();
        fd.append('acao', 'resetar_override_3d');
        fd.append('codigo_sala', activeRoomDbData.codigo_sala);
        fd.append('device_key', devKey);
        fd.append('csrf', window.CSRF);
        await fetch('crud.php', { method: 'POST', body: fd });
      }
    });
  }
});
