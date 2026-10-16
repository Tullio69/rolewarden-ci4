<?php
// Phase W (S1): reference only. The role matrix column widths of the baseline b1cca14 (<work>/basefull), module data
// only, at the four widths, for comparison with Y07 (no check, information).
$MATWB = <<<'JS'
(() => { const t = document.querySelector('table.rw-matrix'); if (!t) return null; const w = (sel) => [...t.querySelectorAll(sel)].map((th) => Math.round(th.getBoundingClientRect().width * 10) / 10);
  const sc = t.parentElement; return { act: w('thead th.rw-m-act'), anno: w('thead th.rw-m-anno'), area: w('thead th.rw-m-area'), tableW: Math.round(t.getBoundingClientRect().width), boxW: sc.clientWidth, overflowX: document.documentElement.scrollWidth - document.documentElement.clientWidth }; })()
JS;
$AW = app_copy('appW', "$WORK/basefull", true);
$cW = serve8($AW, 'W');
$st = [blogin('owner@v8b.test', OPW)];
foreach ([1280, 1366, 1440, 1920] as $w) { $st[] = ['op' => 'viewport', 'width' => $w, 'height' => 900]; $st[] = ['op' => 'goto', 'url' => BASE . 'rolewarden/roles/' . role_id('admin'), 'tag' => "g $w"]; $st[] = ['op' => 'eval', 'expr' => $MATWB, 'tag' => "w $w"]; }
$rw = browser($st, 'baseline widths');
foreach ([1280, 1366, 1440, 1920] as $w) info("baseline b1cca14, admin role matrix at $w px: " . json_encode(bval($rw, "w $w")));
server_stop();
