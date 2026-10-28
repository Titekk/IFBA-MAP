# DESIGN.md — IFBA Campus Ilhéus: Monitoramento Energético e Infraestrutura

## 1. Concept Card & Reference Architecture
```
Idea:        Architectural spatial ledger and 3D room studio for IFBA Campus Ilhéus energy & HVAC operations.
Metaphor:    CAD blueprint sheet + 3D spatial inspector (Mappedin indoor map + Spline 3D studio + PUTURE grid discipline).
Signature:   Seamless transition from the 76x133 2D architectural floorplan grid into the 3-pane 3D classroom studio.
Emotion:     Technical authority, spatial clarity, zero visual noise.
Motion verb: Snap like a technical instrument (140ms cubic-bezier(0.16, 1, 0.3, 1) border/fill transitions; zero decorative bounce).
Screenshot:  The 2D campus floorplan with an open room ledger sheet, or the 3-pane Spline-style 3D classroom studio.
```

### Reference Sites Applied
1. **`https://puture.co.kr/en/` (PUTURE)**:
   - Neutral carbon monochrome ground (`#0a0a0b` and `#111113`) with exposed `1px solid #232327` structural grid hairlines instead of floating card soup.
   - High-contrast neo-grotesque typography (`Schibsted Grotesk`) paired with `JetBrains Mono` strictly for metric coordinates, kWh loads, and BTU capacities.
   - Monochrome primary actions (`#f2f2f3` fill with `#0a0a0b` ink) so color is reserved strictly for spatial state (active selection and HVAC status).
2. **`https://www.mappedin.com/` (Mappedin)**:
   - Unified horizontal **Building Block Index Strip** above the map (a single `1px`-divided architectural strip rather than separate floating KPI cards).
   - Clean 2D indoor map viewport where rooms read as architectural floor plates with clear hierarchy between climatized (`#111d2b` / `#2b4c73`), standard (`#141417`), and active (`#0f261b` / `#22c55e`) spaces.
3. **`https://spline.design/` (Spline 3D Editor)**:
   - True **3-Pane Studio Layout** (`260px` Left Outliner & Survey Table | `1fr` Center 3D Viewport | `320px` Right Property & Transform Inspector) on desktop.
   - Compact axis-labelled numeric inputs (`X`, `Y`, `W`, `H`, `D`) and flush property rows.

## 2. Palette Tokens (6 Named Roles)
- **Ground (`--bg`)**: `#0a0a0b` (Neutral Studio Carbon)
- **Panel / Surface (`--bg-elevated` / `--card`)**: `#111113` / `#161619` (Graphite Ledger Surface)
- **Ink (`--txt`)**: `#f2f2f3` (Crisp Architectural White)
- **Muted (`--mut`)**: `#94949e` (Neutral Studio Grey, AA contrast >= 5.2:1 on Ground & Panel)
- **Line (`--line` / `--line-strong`)**: `#232327` / `#36363d` (`1px solid` structural hairlines)
- **Accent (`--acc` / `--ac` / `--sel`)**: `#f2f2f3` (Primary Action Fill), `#38bdf8` (Climatized Room Signal), `#22c55e` (Selected Room Signal)

## 3. Typography & Anti-Slop Rules
- **Sans (`--font-sans`)**: `'Schibsted Grotesk', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif`
- **Mono (`--font-mono`)**: `'JetBrains Mono', ui-monospace, monospace` (`font-variant-numeric: tabular-nums`)
- **Shape Rule**: `2px` maximum radius (`--radius-sm: 2px; --radius-md: 2px; --radius-lg: 2px;`). Zero `999px` pills.
- **Strict Bans Enforced**:
  - Zero CSS gradients (`linear-gradient`, `radial-gradient`, `repeating-linear-gradient`).
  - Zero SVG icons, emoji, or colored dot pseudo-icons (`.dot`, `.bdot`).
  - Zero middle-dot (`·`) or spaced em-dash (`—`) strings in UI text.
  - Zero `text-transform: uppercase` eyebrow or table-header labels; sentence case is used everywhere.
  - Zero nested cards or 3-KPI-card strips; all metrics use flush structural ledger rows (`1px solid var(--line)`).
