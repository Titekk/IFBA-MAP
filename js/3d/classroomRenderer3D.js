/**
 * Three.js Adapter for the 3D Classroom Visualizer (T-02, T-03, T-08, T-09).
 * Consumes the pure PlacementResult produced by `computeRoomLayout()` in `placementEngine.js`.
 */

import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import {
  ROOM_DIMENSIONS,
  WALL_DEFS,
  ARCHITECTURAL_FIXTURES,
  DEVICE_CATALOG,
} from './roomConfig.js';

/**
 * Creates a procedural composite 3D Group for a placed device, scaled to `placement.size` (`w, h, d`).
 * Local origin `(0, 0, 0)` of the returned Group is the geometric center of its bounding box
 * (`[-w/2..+w/2]`, `[-h/2..+h/2]`, `[-d/2..+d/2]`), with `+Z` as its front face.
 */
function buildDeviceMeshGroup(placement, isSelected = false) {
  const { type, size } = placement;
  const { w, h, d } = size;
  const group = new THREE.Group();
  group.name = placement.key;
  group.userData = { placement };

  if (type === 'television') {
    // Outer bezel
    const bezelMat = new THREE.MeshStandardMaterial({
      color: 0x0f172a,
      roughness: 0.35,
      metalness: 0.5,
    });
    const bezel = new THREE.Mesh(new THREE.BoxGeometry(w, h, d * 0.75), bezelMat);
    bezel.position.z = d * 0.125;
    bezel.castShadow = true;
    group.add(bezel);

    // Glowing display panel on +Z face
    const screenMat = new THREE.MeshStandardMaterial({
      color: 0x0284c7,
      emissive: 0x0369a1,
      emissiveIntensity: 0.45,
      roughness: 0.15,
      metalness: 0.1,
    });
    const screen = new THREE.Mesh(
      new THREE.PlaneGeometry(w * 0.93, h * 0.88),
      screenMat
    );
    screen.position.z = d / 2 + 0.001;
    group.add(screen);

    // Wall bracket at back (-Z)
    const bracketMat = new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.6 });
    const bracket = new THREE.Mesh(
      new THREE.BoxGeometry(w * 0.45, h * 0.35, d * 0.25),
      bracketMat
    );
    bracket.position.z = -d * 0.375;
    group.add(bracket);
  } else if (type === 'air_conditioner') {
    // Main white split unit casing
    const bodyMat = new THREE.MeshStandardMaterial({
      color: 0xf8fafc,
      roughness: 0.25,
      metalness: 0.1,
    });
    const body = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), bodyMat);
    body.castShadow = true;
    group.add(body);

    // Lower air outlet louver strip on +Z front face
    const louverMat = new THREE.MeshStandardMaterial({ color: 0x1e293b, roughness: 0.6 });
    const louver = new THREE.Mesh(
      new THREE.BoxGeometry(w * 0.92, h * 0.22, 0.015),
      louverMat
    );
    louver.position.set(0, -h * 0.28, d / 2 + 0.002);
    group.add(louver);

    // Green status LED badge on front right
    const ledMat = new THREE.MeshBasicMaterial({ color: 0x10b981 });
    const led = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.025, 0.01), ledMat);
    led.position.set(w * 0.34, h * 0.12, d / 2 + 0.004);
    group.add(led);
  } else if (type === 'outlet') {
    // Ivory electrical faceplate
    const plateMat = new THREE.MeshStandardMaterial({
      color: 0xfef3c7,
      roughness: 0.4,
    });
    const plate = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), plateMat);
    group.add(plate);

    // High-contrast orange/red socket insert on +Z face
    const sockMat = new THREE.MeshStandardMaterial({ color: 0xea580c, roughness: 0.5 });
    const sock = new THREE.Mesh(
      new THREE.BoxGeometry(w * 0.58, h * 0.58, 0.008),
      sockMat
    );
    sock.position.z = d / 2 + 0.002;
    group.add(sock);
  } else if (type === 'computer') {
    // Monitor panel + stand + keyboard + mini tower within [w, h, d]
    const darkMat = new THREE.MeshStandardMaterial({
      color: 0x1e293b,
      roughness: 0.4,
      metalness: 0.3,
    });
    const screenMat = new THREE.MeshStandardMaterial({
      color: 0x1d4ed8,
      emissive: 0x1e40af,
      emissiveIntensity: 0.35,
      roughness: 0.2,
    });

    // Monitor bezel (upper part)
    const monW = w * 0.72;
    const monH = h * 0.68;
    const monD = 0.03;
    const monitor = new THREE.Mesh(new THREE.BoxGeometry(monW, monH, monD), darkMat);
    monitor.position.set(-w * 0.1, h * 0.12, -d * 0.12);
    monitor.castShadow = true;
    group.add(monitor);

    const display = new THREE.Mesh(
      new THREE.PlaneGeometry(monW * 0.9, monH * 0.86),
      screenMat
    );
    display.position.set(-w * 0.1, h * 0.12, -d * 0.12 + monD / 2 + 0.002);
    group.add(display);

    // Stand neck + base
    const neck = new THREE.Mesh(new THREE.BoxGeometry(0.04, h * 0.3, 0.04), darkMat);
    neck.position.set(-w * 0.1, -h * 0.32, -d * 0.12);
    group.add(neck);

    const base = new THREE.Mesh(new THREE.BoxGeometry(monW * 0.45, 0.02, 0.14), darkMat);
    base.position.set(-w * 0.1, -h / 2 + 0.01, -d * 0.12);
    group.add(base);

    // Keyboard in front (+Z)
    const kb = new THREE.Mesh(
      new THREE.BoxGeometry(monW * 0.85, 0.02, d * 0.32),
      darkMat
    );
    kb.position.set(-w * 0.1, -h / 2 + 0.01, d * 0.22);
    group.add(kb);

    // Compact CPU tower on the right side of the slot
    const cpuMat = new THREE.MeshStandardMaterial({ color: 0x0f172a, roughness: 0.45 });
    const cpu = new THREE.Mesh(
      new THREE.BoxGeometry(w * 0.22, h * 0.82, d * 0.75),
      cpuMat
    );
    cpu.position.set(w * 0.36, -h * 0.09, 0);
    cpu.castShadow = true;
    group.add(cpu);
  } else if (type === 'printer') {
    // Two-tone office laser/multifunction printer
    const bodyMat = new THREE.MeshStandardMaterial({ color: 0xe2e8f0, roughness: 0.35 });
    const topMat = new THREE.MeshStandardMaterial({ color: 0x334155, roughness: 0.45 });
    const paperMat = new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.8 });

    const body = new THREE.Mesh(new THREE.BoxGeometry(w, h * 0.78, d), bodyMat);
    body.position.y = -h * 0.11;
    body.castShadow = true;
    group.add(body);

    const scannerLid = new THREE.Mesh(new THREE.BoxGeometry(w * 0.96, h * 0.22, d * 0.96), topMat);
    scannerLid.position.y = h * 0.39;
    group.add(scannerLid);

    const paperTray = new THREE.Mesh(new THREE.BoxGeometry(w * 0.55, 0.02, d * 0.45), paperMat);
    paperTray.position.set(0, 0.02, d * 0.3);
    group.add(paperTray);
  } else if (type === 'lamp') {
    const housingMat = new THREE.MeshStandardMaterial({ color: 0xcbd5e1, roughness: 0.4 });
    const glowMat = new THREE.MeshStandardMaterial({
      color: 0xfef9c3,
      emissive: 0xfef08a,
      emissiveIntensity: 0.75,
    });
    const housing = new THREE.Mesh(new THREE.BoxGeometry(w, h * 0.6, d), housingMat);
    housing.position.y = h * 0.2;
    group.add(housing);

    const diffuser = new THREE.Mesh(new THREE.BoxGeometry(w * 0.94, h * 0.4, d * 0.82), glowMat);
    diffuser.position.y = -h * 0.2;
    group.add(diffuser);
  } else {
    // Fan or generic recognized fixture
    const metaColor = DEVICE_CATALOG[type]?.colorHex ?? 0x64748b;
    const mat = new THREE.MeshStandardMaterial({ color: metaColor, roughness: 0.4, metalness: 0.4 });
    const rod = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, h * 0.7, 12), mat);
    rod.position.y = h * 0.15;
    group.add(rod);

    const hub = new THREE.Mesh(new THREE.CylinderGeometry(w * 0.18, w * 0.18, h * 0.28, 16), mat);
    hub.position.y = -h * 0.2;
    group.add(hub);

    for (let i = 0; i < 3; i++) {
      const blade = new THREE.Mesh(new THREE.BoxGeometry(w * 0.46, 0.015, 0.09), mat);
      const angle = (i * Math.PI * 2) / 3;
      blade.position.set(Math.cos(angle) * w * 0.24, -h * 0.2, Math.sin(angle) * w * 0.24);
      blade.rotation.y = -angle;
      group.add(blade);
    }
  }

  // Highlight wireframe box when selected or overridden
  if (isSelected || placement.overridden) {
    const edgeGeo = new THREE.EdgesGeometry(new THREE.BoxGeometry(w + 0.02, h + 0.02, d + 0.02));
    const edgeMat = new THREE.LineBasicMaterial({
      color: isSelected ? 0x38bdf8 : 0xf59e0b,
    });
    const outline = new THREE.LineSegments(edgeGeo, edgeMat);
    group.add(outline);
  }

  return group;
}

/**
 * Mounts and manages the interactive Three.js 3D Classroom scene inside `containerEl`.
 */
export function mountClassroom3D(containerEl, { onSelectDevice } = {}) {
  const scene = new THREE.Scene();
  scene.background = new THREE.Color(0x0c0c0e);

  const width = Math.max(containerEl.clientWidth, 400);
  const height = Math.max(containerEl.clientHeight, 360);

  const camera = new THREE.PerspectiveCamera(48, width / height, 0.1, 100);
  camera.position.set(6.8, 5.6, 8.2);

  const renderer = new THREE.WebGLRenderer({ antialias: true });
  renderer.setSize(width, height);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;

  containerEl.innerHTML = '';
  containerEl.appendChild(renderer.domElement);

  const controls = new OrbitControls(camera, renderer.domElement);
  controls.enableDamping = true;
  controls.dampingFactor = 0.07;
  controls.target.set(0, 1.1, 0);
  controls.minDistance = 2.5;
  controls.maxDistance = 22.0;
  controls.maxPolarAngle = Math.PI / 2 - 0.02;

  // Lighting (T-09)
  const ambientLight = new THREE.AmbientLight(0xffffff, 0.72);
  scene.add(ambientLight);

  const dirLight = new THREE.DirectionalLight(0xffffff, 0.95);
  dirLight.position.set(5.5, 8.5, 6.0);
  dirLight.castShadow = true;
  dirLight.shadow.mapSize.width = 1024;
  dirLight.shadow.mapSize.height = 1024;
  scene.add(dirLight);

  const fillLight = new THREE.DirectionalLight(0xd4d4d8, 0.35);
  fillLight.position.set(-6.0, 4.0, -5.0);
  scene.add(fillLight);

  // Static Architectural Shell Group (T-02)
  const shellGroup = new THREE.Group();
  scene.add(shellGroup);

  const { width: W, height: H, depth: D, wallThickness: T } = ROOM_DIMENSIONS;

  // Floor at Y = 0
  const floorGeo = new THREE.PlaneGeometry(W, D);
  const floorMat = new THREE.MeshStandardMaterial({
    color: 0xe2e8f0,
    roughness: 0.75,
    metalness: 0.05,
  });
  const floorMesh = new THREE.Mesh(floorGeo, floorMat);
  floorMesh.rotation.x = -Math.PI / 2;
  floorMesh.receiveShadow = true;
  shellGroup.add(floorMesh);

  // Metric 1.0 m grid overlay on floor
  const gridHelper = new THREE.GridHelper(Math.max(W, D), Math.max(W, D), 0x94a3b8, 0xcbd5e1);
  gridHelper.position.y = 0.003;
  gridHelper.scale.set(W / Math.max(W, D), 1, D / Math.max(W, D));
  shellGroup.add(gridHelper);

  // Walls with dynamic camera-angle transparency so interior is never occluded (T-02)
  const wallMeshes = [];
  const wallConfigs = [
    {
      id: 'front',
      w: W + T * 2,
      h: H,
      d: T,
      pos: [0, H / 2, -D / 2 - T / 2],
      normal: new THREE.Vector3(0, 0, 1),
    },
    {
      id: 'back',
      w: W + T * 2,
      h: H,
      d: T,
      pos: [0, H / 2, D / 2 + T / 2],
      normal: new THREE.Vector3(0, 0, -1),
    },
    {
      id: 'left',
      w: T,
      h: H,
      d: D,
      pos: [-W / 2 - T / 2, H / 2, 0],
      normal: new THREE.Vector3(1, 0, 0),
    },
    {
      id: 'right',
      w: T,
      h: H,
      d: D,
      pos: [W / 2 + T / 2, H / 2, 0],
      normal: new THREE.Vector3(-1, 0, 0),
    },
  ];

  for (const wc of wallConfigs) {
    const mat = new THREE.MeshStandardMaterial({
      color: 0xf8fafc,
      roughness: 0.85,
      transparent: true,
      opacity: 0.92,
    });
    const mesh = new THREE.Mesh(new THREE.BoxGeometry(wc.w, wc.h, wc.d), mat);
    mesh.position.set(...wc.pos);
    mesh.receiveShadow = true;
    mesh.userData = { wallId: wc.id, normal: wc.normal };
    shellGroup.add(mesh);
    wallMeshes.push(mesh);
  }

  // Context Fixture: Whiteboard on front wall
  const wb = ARCHITECTURAL_FIXTURES.whiteboard;
  const wbGroup = new THREE.Group();
  const wbFrame = new THREE.Mesh(
    new THREE.BoxGeometry(wb.size.w + 0.08, wb.size.h + 0.08, wb.size.d),
    new THREE.MeshStandardMaterial({ color: 0x64748b, roughness: 0.4 })
  );
  const wbSurface = new THREE.Mesh(
    new THREE.BoxGeometry(wb.size.w, wb.size.h, wb.size.d + 0.004),
    new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.2 })
  );
  wbGroup.add(wbFrame, wbSurface);
  wbGroup.position.set(wb.position.x, wb.position.y, wb.position.z);
  shellGroup.add(wbGroup);

  // Context Fixture: Door on left wall
  const dr = ARCHITECTURAL_FIXTURES.door;
  const doorMesh = new THREE.Mesh(
    new THREE.BoxGeometry(dr.size.d, dr.size.h, dr.size.w),
    new THREE.MeshStandardMaterial({ color: 0x92400e, roughness: 0.65 })
  );
  doorMesh.position.set(dr.position.x, dr.position.y, dr.position.z);
  shellGroup.add(doorMesh);

  // Dynamic Groups for Tables & Placed Devices
  const furnitureGroup = new THREE.Group();
  const devicesGroup = new THREE.Group();
  scene.add(furnitureGroup);
  scene.add(devicesGroup);

  let currentLayout = null;
  let selectedKey = null;
  const clickableRoots = [];

  function rebuildFurniture(tables = []) {
    furnitureGroup.clear();
    const legMat = new THREE.MeshStandardMaterial({ color: 0x475569, roughness: 0.5, metalness: 0.4 });
    const studentTopMat = new THREE.MeshStandardMaterial({ color: 0xd4a373, roughness: 0.55 });
    const instructorTopMat = new THREE.MeshStandardMaterial({ color: 0xb5835a, roughness: 0.5 });

    for (const t of tables) {
      const tGroup = new THREE.Group();
      const topThickness = 0.04;
      const topMesh = new THREE.Mesh(
        new THREE.BoxGeometry(t.size.w, topThickness, t.size.d),
        t.role === 'instructor' ? instructorTopMat : studentTopMat
      );
      topMesh.position.y = t.topY - topThickness / 2;
      topMesh.castShadow = true;
      topMesh.receiveShadow = true;
      tGroup.add(topMesh);

      // 4 tubular legs
      const legH = t.topY - topThickness;
      const legGeo = new THREE.BoxGeometry(0.04, legH, 0.04);
      const dx = t.size.w / 2 - 0.05;
      const dz = t.size.d / 2 - 0.05;
      for (const [lx, lz] of [
        [-dx, -dz],
        [dx, -dz],
        [-dx, dz],
        [dx, dz],
      ]) {
        const leg = new THREE.Mesh(legGeo, legMat);
        leg.position.set(lx, legH / 2, lz);
        leg.castShadow = true;
        tGroup.add(leg);
      }

      tGroup.position.set(t.position.x, 0, t.position.z);
      furnitureGroup.add(tGroup);
    }
  }

  function rebuildDevices() {
    devicesGroup.clear();
    clickableRoots.length = 0;
    if (!currentLayout) return;

    for (const p of currentLayout.placements) {
      const meshGroup = buildDeviceMeshGroup(p, p.key === selectedKey);
      meshGroup.position.set(p.position.x, p.position.y, p.position.z);
      meshGroup.rotation.set(p.rotation.x, p.rotation.y, p.rotation.z);
      devicesGroup.add(meshGroup);
      clickableRoots.push(meshGroup);
    }
  }

  function renderLayout(layoutResult) {
    currentLayout = layoutResult;
    rebuildFurniture(layoutResult.tables || []);
    rebuildDevices();
  }

  function selectDevice(deviceKey) {
    selectedKey = deviceKey;
    rebuildDevices();
    if (onSelectDevice && currentLayout) {
      const found = currentLayout.placements.find((p) => p.key === deviceKey) || null;
      onSelectDevice(found);
    }
  }

  function setCameraPreset(preset) {
    if (preset === 'front') {
      camera.position.set(0, 1.85, 5.8);
      controls.target.set(0, 1.4, -2.0);
    } else if (preset === 'top') {
      camera.position.set(0, 10.5, 0.01);
      controls.target.set(0, 0, 0);
    } else {
      camera.position.set(6.8, 5.6, 8.2);
      controls.target.set(0, 1.1, 0);
    }
    controls.update();
  }

  // Raycast click to inspect any device in 3D
  const raycaster = new THREE.Raycaster();
  const pointer = new THREE.Vector2();
  let pointerDownPos = { x: 0, y: 0 };

  const onPointerDown = (e) => {
    pointerDownPos = { x: e.clientX, y: e.clientY };
  };

  const onPointerUp = (e) => {
    const dist = Math.hypot(e.clientX - pointerDownPos.x, e.clientY - pointerDownPos.y);
    if (dist > 6) return; // Ignore orbit drag gestures

    const rect = renderer.domElement.getBoundingClientRect();
    pointer.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
    pointer.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;

    raycaster.setFromCamera(pointer, camera);
    const hits = raycaster.intersectObjects(clickableRoots, true);
    if (hits.length > 0) {
      let obj = hits[0].object;
      while (obj && !obj.userData?.placement && obj.parent) {
        obj = obj.parent;
      }
      if (obj?.userData?.placement) {
        selectDevice(obj.userData.placement.key);
        return;
      }
    }
  };

  renderer.domElement.addEventListener('pointerdown', onPointerDown);
  renderer.domElement.addEventListener('pointerup', onPointerUp);

  // Resize handling (T-09)
  const handleResize = () => {
    if (!containerEl.isConnected) return;
    const w = Math.max(containerEl.clientWidth, 300);
    const h = Math.max(containerEl.clientHeight, 300);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
    renderer.setSize(w, h);
  };
  const resizeObserver = new ResizeObserver(handleResize);
  resizeObserver.observe(containerEl);

  // Animation loop with dynamic wall transparency so walls between camera and room center fade out
  let animId = 0;
  const camToCenter = new THREE.Vector3();

  function animate() {
    animId = requestAnimationFrame(animate);
    controls.update();

    camToCenter.set(0, 1.2, 0).sub(camera.position).normalize();
    for (const wm of wallMeshes) {
      // If wall inward normal points roughly in the same direction as camera-to-center ray,
      // the camera is behind that wall looking in -> make wall semi-transparent
      const dot = wm.userData.normal.dot(camToCenter);
      wm.material.opacity = dot > 0.15 ? 0.14 : 0.92;
    }

    renderer.render(scene, camera);
  }
  animate();

  return {
    renderLayout,
    selectDevice,
    setCameraPreset,
    handleResize,
    dispose() {
      cancelAnimationFrame(animId);
      resizeObserver.disconnect();
      renderer.domElement.removeEventListener('pointerdown', onPointerDown);
      renderer.domElement.removeEventListener('pointerup', onPointerUp);
      controls.dispose();
      renderer.dispose();
    },
  };
}
