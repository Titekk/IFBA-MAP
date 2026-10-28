# DESIGN.md — IFBA Campus Ilhéus: Monitoramento Energético e Infraestrutura

## 1. Concept Card & Reference Architecture
```
Idea:        GitHub Primer high-contrast architectural ledger and 3-pane 3D room studio for IFBA Campus Ilhéus.
Metaphor:    Precision engineering repository + CAD blueprint sheet (GitHub Primer + Mappedin + Spline).
Signature:   High-contrast GitHub Primer pairing (#24292f dark topbar and pavilion headers over #f6f8fa/#ffffff surfaces, #0969da climatized rooms, and #1f883d/#dafbe1 active selections).
Emotion:     Technical authority, crisp legibility, zero visual noise.
Motion verb: Snap like a technical instrument (140ms cubic-bezier(0.16, 1, 0.3, 1)).
Screenshot:  The 2D campus blueprint in GitHub Primer Light/Dark contrast with the 3-pane 3D classroom studio.
```

## 2. GitHub Primer Palette Tokens (`github.com`)
- **Canvas Background (`--bg`)**: `#f6f8fa` (Light) / `#0d1117` (Dark)
- **Elevated Surface / Panels (`--bg-elevated`)**: `#ffffff` (Light) / `#161b22` (Dark)
- **Muted Surface (`--card`)**: `#f6f8fa` (Light) / `#21262d` (Dark)
- **Header & Pavilion Bar (`--header-bg` / `--header-txt`)**: `#24292f` / `#ffffff` (Light) · `#010409` / `#e6edf3` (Dark)
- **Primary Text (`--txt`)**: `#1f2328` (Light) / `#e6edf3` (Dark)
- **Secondary Text (`--mut`)**: `#59636e` (Light) / `#8b949e` (Dark)
- **Structural Border (`--line` / `--line-strong`)**: `#d0d7de` / `#8c959f` (Light) · `#30363d` / `#6e7681` (Dark)
- **Primary Action Green (`--btn-primary-bg` / `--room-sel-bg`)**: `#1f883d` / `#dafbe1` (Light) · `#238636` / `#12261e` (Dark)
- **Climatized / Interactive Accent Blue (`--ac` / `--room-ac-bg`)**: `#0969da` / `#ddf4ff` (Light) · `#2f81f7` / `#121d2f` (Dark)
- **Warning (`--warn`)**: `#9a6700` / `#fff8c5` (Light) · `#d29922` / `#2b2109` (Dark)
- **Danger (`--err`)**: `#cf222e` / `#ffebe9` (Light) · `#f85149` / `#2d1215` (Dark)

## 3. Typography & Anti-Slop Rules
- **Sans (`--font-sans`)**: `'Schibsted Grotesk', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif`
- **Mono (`--font-mono`)**: `'JetBrains Mono', ui-monospace, monospace` (`font-variant-numeric: tabular-nums`)
- **Shape Rule**: `2px` maximum radius (`--radius-sm: 2px; --radius-md: 2px; --radius-lg: 2px;`). Zero `999px` pills.
