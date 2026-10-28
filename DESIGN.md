# DESIGN.md — IFBA Campus Ilhéus: Monitoramento Energético e Infraestrutura

## 1. Visual Direction & Domain Exemplars
- **Primary Category**: `data_analytics_apps` / `bi_analytics_dashboard` + `spatial_architectural_cad`
- **Core Aesthetic Philosophy**: Sober, utilitarian engineering interface inspired by technical CAD/BIM software and industrial telemetry systems:
  - **Zero Gradients**: Flat, solid architectural surfaces (`#0b0d10`, `#111419`, `#161a21`) separated exclusively by crisp `1px solid` structural borders (`#262b36`, `#373f4f`).
  - **Sharp Technical Corners (`2px`)**: All cards, tables, inputs, badges, and buttons use a strict `2px` border radius (`--radius-sm: 2px; --radius-md: 2px; --radius-lg: 2px;`). Pill shapes (`999px`) and large rounded cards (`10px–16px`) are strictly prohibited.
  - **Zero AI-Generated Decorative Clutter**: No glowing dots, no glassmorphism (`backdrop-filter`), no decorative SVG icons on buttons/tabs, no rainbow block palettes, and no marketing buzzwords. Terminology is direct, institutional, and technical (`IFBA Campus Ilhéus`, `Monitoramento Energético`, `Visualizar Sala em 3D`, `Perspectiva`, `Vista Frontal`, `Planta Superior`).

## 2. Color & Atmospheric Polarity
- **Canvas Background (`--bg`)**: `#0b0d10` (Flat Technical Carbon)
- **Elevated Surface / Topbar (`--bg-elevated`)**: `#111419` (Sober Slate)
- **Interactive Card / Module (`--card`)**: `#161a21` (Graphite Surface)
- **Hover Surface (`--card-hover`)**: `#1c212a` (Elevated Graphite)
- **Structural Hairline (`--line`)**: `#262b36` (`1px solid`)
- **Active Hairline (`--line-strong`)**: `#373f4f` (`1px solid`)
- **Primary Text (`--txt`)**: `#eceff4`
- **Secondary / Label Text (`--mut`)**: `#8b93a4`
- **Primary Institutional Accent (`--acc` / `--acc-text`)**: `#15803d` / `#4ade80` (IFBA Institutional Green)
- **Technical Selection / HVAC Highlight (`--ac`)**: `#38bdf8`
- **Warning (`--warn`)**: `#d97706`
- **Danger / Error (`--err`)**: `#dc2626`

## 3. Typography & Scannable Hierarchy
- **UI & Headings (`--font-sans`)**: `'Public Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif`
- **Telemetry, Coordinates & Quantities (`--font-mono`)**: `'IBM Plex Mono', ui-monospace, 'Cascadia Code', monospace`
- **Numeric Discipline**: `font-variant-numeric: tabular-nums` enforced on all tables, kWh counters, BTU ratings, and 3D `(X, Y, Z)` metric coordinates.
