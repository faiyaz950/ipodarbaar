<style>
    .an-bar-row { display: grid; grid-template-columns: minmax(0, 1fr) 70px 70px; gap: 12px; align-items: center; padding: 10px 18px; border-bottom: 1px solid var(--border); font-size: 13.5px; }
    .an-bar-row:last-child { border-bottom: 0; }
    .an-bar-row .lbl { min-width: 0; }
    .an-bar-row .lbl b { display: block; font-weight: 600; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .an-bar-row .lbl small { display: block; color: var(--muted); font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .an-bar-row .num { text-align: right; font-variant-numeric: tabular-nums; }
    .an-share { height: 4px; border-radius: 999px; background: var(--surface-3); margin-top: 6px; overflow: hidden; }
    .an-share i { display: block; height: 100%; border-radius: 999px; background: var(--an-bar); }
    .an-head { display: grid; grid-template-columns: minmax(0, 1fr) 70px 70px; gap: 12px; padding: 10px 18px; font-size: 11.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--border); }
    .an-head span:not(:first-child) { text-align: right; }
    .an-chart { --an-bar: #2F5BEA; position: relative; padding: 8px 18px 4px; }
    :root[data-theme="dark"] .an-chart, :root[data-theme="dark"] .an-share { --an-bar: #5B7CF0; }
    .an-share { --an-bar: #2F5BEA; }
    .an-plot { position: relative; height: 220px; margin: 10px 0 0 48px; }
    .an-grid-line { position: absolute; left: 0; right: 0; border-top: 1px solid var(--border); }
    .an-grid-line span { position: absolute; right: 100%; margin-right: 8px; transform: translateY(-50%); font-size: 11px; color: var(--muted); font-variant-numeric: tabular-nums; }
    .an-cols { position: absolute; inset: 0; display: flex; align-items: flex-end; gap: 2px; }
    .an-slot { flex: 1; height: 100%; display: flex; align-items: flex-end; justify-content: center; min-width: 0; }
    .an-slot i { display: block; width: 100%; max-width: 24px; background: var(--an-bar); border-radius: 4px 4px 0 0; }
    .an-slot:hover i { opacity: .72; }
    .an-x { display: flex; gap: 2px; margin: 6px 0 0 48px; font-size: 11px; color: var(--muted); }
    .an-x span { flex: 1; min-width: 0; text-align: center; white-space: nowrap; overflow: visible; }
    .an-tip { position: absolute; pointer-events: none; background: var(--navy-900); color: #fff; border-radius: 8px; padding: 8px 10px; font-size: 12.5px; line-height: 1.45; white-space: nowrap; transform: translate(-50%, calc(-100% - 10px)); box-shadow: var(--shadow); }
    :root[data-theme="dark"] .an-tip { background: var(--surface-3); }
    .an-tip b { font-variant-numeric: tabular-nums; }
    .an-delta { font-size: 13px; font-weight: 600; margin-left: 6px; }
    .an-range { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 18px; }
    .an-range form { display: flex; gap: 6px; align-items: center; font-size: 13px; }
    .an-range input[type=date] { height: 32px; padding: 0 8px; }
    .an-path { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; }
    .an-live { display: inline-flex; align-items: center; gap: 6px; }
    .an-live i { width: 8px; height: 8px; border-radius: 50%; background: var(--green); box-shadow: 0 0 0 3px var(--green-soft); }
    @media (max-width: 720px) { .an-bar-row, .an-head { grid-template-columns: minmax(0, 1fr) 56px 56px; padding: 10px 14px; } }
</style>
