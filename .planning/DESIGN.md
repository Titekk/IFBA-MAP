# DESIGN.md — IFBA Campus Ilhéus: Monitoramento Energético e Infraestrutura

## 1. Concept Card & Reference Architecture
```
Idea:        Swiss White/Black architectural blueprint ledger and 3D room studio for IFBA Campus Ilhéus.
Metaphor:    Physical architectural drafting sheet + precision hardware instrument (Teenage Engineering + Vercel Design + Mappedin + Spline).
Signature:   High-contrast White/Black floorplan where pavilion headers and selected rooms invert to stark black (#0a0a0b) against a pure white (#ffffff) drafting grid, paired with a 3-pane 3D studio.
Emotion:     Architectural clarity, monochrome precision, zero visual noise.
Motion verb: Snap like a technical instrument (140ms cubic-bezier(0.16, 1, 0.3, 1) fill/border inversions).
Screenshot:  The White/Black 2D campus blueprint with stark black pavilion headers and an inverted selected room, or the 3-pane 3D classroom studio.
```

### Reference Sites Applied
1. **`https://teenage.engineering/` & `https://vercel.com/design` (White/Black Monochrome System)**:
   - **Architectural Paper White & Stark Black**: Matte paper-white ground (`#f6f6f4`), pure white panels (`#ffffff`), stark carbon black ink and primary fills (`#0a0a0b`), and exposed `1px solid #dcdcd8` structural grid hairlines.
   - **Inversion as Hierarchy**: Instead of decorative colors, hierarchy and active state are communicated by stark **Black-on-White / White-on-Black inversion** (active tabs, pavilion headers, primary CTAs, and the selected room on the 2D floorplan invert to `#0a0a0b` background with `#ffffff` text).
   - **Dual Polarity (`Branco / Preto` default + `Preto / Branco` toggle)**: Ships in White/Black mode by default with an instant polarity toggle in the top bar (`data-theme="dark"`).
2. **`https://www.mappedin.com/` (Mappedin)**:
   - Unified horizontal **Building Block Index Strip** above the map (a single `1px`-divided architectural ledger rather than floating KPI cards).
   - Clean 2D indoor map viewport where rooms read as architectural floor plates.
3. **`https://spline.design/` & `https://puture.co.kr/en/`**:
   - **3-Pane Studio Layout** (`270px` Left Outliner & Survey Table | `1fr` Center 3D Viewport | `330px` Right Property & Transform Inspector) with `Schibsted Grotesk` + `JetBrains Mono` tabular numerals.

## 2. Palette Tokens (White/Black Default & Black/White Inverted)
- **Ground (`--bg`)**: `#f6f6f4` (Drafting Paper White) / `#0a0a0b` (Dark mode)
- **Panel / Surface (`--bg-elevated` / `--card`)**: `#ffffff` / `#efefec` (White Sheet & Stone)
- **Ink (`--txt`)**: `#0a0a0b` (Stark Carbon Black) / `#f2f2f3` (Dark mode)
- **Muted (`--mut`)**: `#52525b` (Graphite Grey, contrast 7.4:1 on White) / `#94949e` (Dark mode)
- **Line (`--line` / `--line-strong`)**: `#dcdcd8` / `#0a0a0b` (`1px solid` structural hairlines)
- **Accent (`--acc`)**: `#0a0a0b` (Stark Black Primary Action Fill with `#ffffff` Ink)

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
