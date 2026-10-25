<?php

/*
 * Renders the textile scenario matrix into animazon_textiles_erp_test_results.html from a real PHPUnit JUnit log.
 *
 *   php tests/Support/render_textile_matrix.php run            run phpunit.textile-matrix.xml, then render
 *   php tests/Support/render_textile_matrix.php <junit.xml>    render from an existing log
 *
 * Results come only from the log: a case is PASS when every listed test passed, FAIL when any failed, NO RESULT when a
 * listed test is missing from the log, and NOT BUILT when the matrix says no code exists for it.
 */

$root = dirname(__DIR__, 2);
$report = $root.'/animazon_textiles_erp_test_results.html';
$evidenceDir = $root.'/storage/logs/textile-evidence';
$shots = 'tests-e2e/screenshots/matrix';
$junit = $argv[1] ?? $root.'/storage/logs/textile-matrix.xml';
if ($junit === 'run') {
    $junit = $root.'/storage/logs/textile-matrix.xml';
    @mkdir(dirname($junit), 0777, true);
    array_map('unlink', glob($evidenceDir.'/{,prints/}*.{json,html}', GLOB_BRACE) ?: []);
    // Tests record what they posted and what it changed (tests/Support/RecordsTestEvidence.php).
    putenv('ERP_TEST_EVIDENCE_DIR='.$evidenceDir);
    passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/vendor/bin/phpunit').' -c '.escapeshellarg($root.'/phpunit.textile-matrix.xml')
        .' --log-junit '.escapeshellarg($junit));
}

/** Screenshots each printed document with headless Chrome; returns report-relative image paths by print file name. */
function screenshotPrints(string $root, string $evidenceDir, string $shots, array $wanted): array
{
    $chrome = getenv('CHROME_PATH') ?: (glob(getenv('LOCALAPPDATA').'/ms-playwright/chromium-*/chrome-win*/chrome.exe') ?: [null])[0];
    if (!$chrome || !is_file($chrome)) { fwrite(STDERR, "Chrome not found (set CHROME_PATH); prints are linked, not screenshotted.\n"); return []; }
    @mkdir($root.'/'.$shots, 0777, true);
    array_map('unlink', glob($root.'/'.$shots.'/*.png') ?: []);
    $images = [];
    foreach (array_unique($wanted) as $print) {
        $source = $evidenceDir.'/prints/'.$print; $image = $shots.'/'.preg_replace('/\.html$/', '.png', $print);
        if (!is_file($source)) continue;
        exec(escapeshellarg($chrome).' --headless=new --disable-gpu --hide-scrollbars --window-size=900,1200 --screenshot='
            .escapeshellarg($root.'/'.$image).' '.escapeshellarg('file:///'.str_replace('\\', '/', realpath($source))).' 2>&1');
        if (is_file($root.'/'.$image)) $images[$print] = $image;
    }
    return $images;
}
if (!is_file($junit)) {
    fwrite(STDERR, "JUnit log not found: $junit\n");
    exit(1);
}

$matrix = require __DIR__.'/textile_scenario_matrix.php';
$results = [];
foreach ((new SimpleXMLElement(file_get_contents($junit)))->xpath('//testcase') as $case) {
    $class = substr(strrchr('\\'.(string) $case['class'], '\\'), 1);
    $state = isset($case->failure) || isset($case->error) ? 'fail' : (isset($case->skipped) ? 'skip' : 'pass');
    $message = trim((string) ($case->failure ?? $case->error ?? ''));
    $results[$class.'::'.$case['name']] = ['state' => $state, 'time' => (float) $case['time'], 'message' => strtok($message, "\n") ?: ''];
}

function caseResult(array $case, array $results): array
{
    if ($case['status'] === 'not_built') return ['not_built', []];
    $tests = array_map(fn ($test) => ['name' => $test] + ($results[$test] ?? ['state' => 'missing', 'time' => 0, 'message' => 'Not in the JUnit log']), $case['tests']);
    $states = array_column($tests, 'state');
    $result = in_array('fail', $states, true) ? 'fail' : (in_array('missing', $states, true) ? 'missing' : (in_array('skip', $states, true) ? 'skip' : 'pass'));
    return [$result, $tests];
}

$e = fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
$label = ['pass' => 'PASS', 'fail' => 'FAIL', 'missing' => 'NO RESULT', 'skip' => 'SKIPPED', 'not_built' => 'NOT BUILT'];
$support = ['built' => 'Built', 'partial' => 'Partial', 'not_built' => 'Not built'];

$rows = []; $count = ['pass' => 0, 'fail' => 0, 'missing' => 0, 'skip' => 0, 'not_built' => 0]; $partial = $partialPass = $builtPass = 0; $byCard = [];
foreach ($matrix['cases'] as $case) {
    [$result, $tests] = caseResult($case, $results);
    $count[$result]++;
    if ($case['status'] === 'partial') { $partial++; $partialPass += $result === 'pass'; }
    if ($case['status'] === 'built') $builtPass += $result === 'pass';
    foreach ($case['cards'] as $card) $byCard[$card][] = [$case, $result];
    $rows[$case['stage'] ?? substr($case['id'], 0, 3)][] = [$case, $result, $tests];
}
$referenced = array_unique(array_merge(...array_map(fn ($case) => $case['tests'], $matrix['cases'])));
$evidence = [];
foreach ($referenced as $test) {
    $file = $evidenceDir.'/'.str_replace('::', '__', $test).'.json';
    if (is_file($file)) $evidence[$test] = json_decode(file_get_contents($file), true) ?: [];
}
$images = screenshotPrints($root, $evidenceDir, $shots, array_merge(...array_values(array_map(fn ($item) => $item['prints'] ?? [], $evidence)) ?: [[]]));
$json = fn ($value) => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$ran = count($results); $passed = count(array_filter($results, fn ($r) => $r['state'] === 'pass'));
$seconds = array_sum(array_column($results, 'time'));
$commit = trim((string) @shell_exec('git -C '.escapeshellarg($root).' rev-parse --short HEAD 2>'.(PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null')));
$when = date('Y-m-d H:i', filemtime($junit));

ob_start(); ?>
  <!-- TEXTILE-MATRIX:START -->
  <!-- Generated by tests/Support/render_textile_matrix.php; edit tests/Support/textile_scenario_matrix.php, not this block. -->
  <style>
    .tm-wrap { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; padding: 20px; margin-bottom: 32px; }
    .tm-lead { color: var(--text-muted); font-size: 13px; line-height: 1.6; margin-bottom: 16px; }
    .tm-lead code { background: rgba(15,23,42,.7); padding: 1px 6px; border-radius: 4px; color: var(--text); font-size: 12px; }
    .tm-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-bottom: 18px; }
    .tm-tile { background: rgba(15,23,42,.6); border: 1px solid var(--card-border); border-radius: 10px; padding: 12px 14px; }
    .tm-tile b { display: block; font-size: 22px; font-variant-numeric: tabular-nums; }
    .tm-tile span { font-size: 11.5px; color: var(--text-muted); }
    .tm-filters { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
    .tm-filters button { background: transparent; color: var(--text-muted); border: 1px solid var(--card-border); border-radius: 999px; padding: 5px 12px; font-size: 12px; cursor: pointer; }
    .tm-filters button.on { background: var(--accent); border-color: var(--accent); color: #fff; }
    .tm-stage { font-size: 13px; font-weight: 700; color: #fff; margin: 18px 0 8px; }
    .tm-scroll { overflow-x: auto; }
    .tm-table { width: 100%; border-collapse: collapse; font-size: 12.5px; min-width: 760px; }
    .tm-table th { text-align: left; color: var(--text-muted); font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; padding: 6px 8px; border-bottom: 1px solid var(--card-border); }
    .tm-table td { padding: 9px 8px; border-bottom: 1px solid rgba(51,65,85,.6); vertical-align: top; }
    .tm-id { font-family: ui-monospace, monospace; color: var(--text-muted); white-space: nowrap; }
    .tm-title { color: #fff; font-weight: 600; }
    .tm-detail { color: var(--text-muted); margin-top: 3px; line-height: 1.5; }
    .tm-note { color: var(--warning); margin-top: 4px; line-height: 1.5; }
    .tm-chip { display: inline-block; font-size: 10.5px; padding: 1px 6px; border-radius: 4px; border: 1px solid var(--card-border); color: var(--text-muted); margin: 0 3px 3px 0; }
    .tm-res { display: inline-block; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; white-space: nowrap; }
    .tm-pass { background: var(--success-glow); color: var(--success); }
    .tm-fail { background: rgba(239,68,68,.18); color: var(--danger); }
    .tm-missing, .tm-skip { background: rgba(245,158,11,.18); color: var(--warning); }
    .tm-not_built { background: rgba(148,163,184,.15); color: var(--text-muted); }
    .tm-support-partial { color: var(--warning); } .tm-support-not_built { color: var(--text-muted); } .tm-support-built { color: var(--success); }
    .tm-table details summary { cursor: pointer; color: var(--info); font-size: 12px; }
    .tm-table details ul { margin: 6px 0 0 16px; padding: 0; color: var(--text-muted); font-size: 11.5px; line-height: 1.6; }
    .tm-table details li { word-break: break-word; }
    .tm-gaps { margin-top: 20px; display: grid; gap: 8px; }
    .tm-gap { background: rgba(15,23,42,.6); border-left: 3px solid var(--warning); border-radius: 6px; padding: 8px 12px; font-size: 12.5px; color: var(--text-muted); }
    .tm-gap.nb { border-left-color: var(--text-muted); }
    .tm-gap b { color: #fff; }
    .matrix-note { font-size: 11.5px; margin: -2px 0 10px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
  </style>
  <div class="section-title" id="textile-matrix">
    Textile Scenario Matrix &middot; Real Test Results
    <span><?= count($matrix['cases']) ?> cases &middot; PHPUnit run <?= $e($when) ?><?= $commit ? ' &middot; commit '.$e($commit) : '' ?></span>
  </div>
  <div class="tm-wrap">
    <p class="tm-lead">
      Each case runs through the real purchase, job work, sale, GST and ledger services (in-memory database, no data inserted by hand).
      PASS means every listed test passed in the run below; NOT BUILT means the ERP has no code for it yet.
      Regenerate with <code>php tests/Support/render_textile_matrix.php run</code>.
    </p>
    <div class="tm-tiles">
      <div class="tm-tile"><b style="color:var(--success)"><?= $builtPass ?></b><span>built, tests pass</span></div>
      <div class="tm-tile"><b style="color:var(--warning)"><?= $partial ?></b><span>partly built (<?= $partialPass ?> pass)</span></div>
      <div class="tm-tile"><b style="color:var(--danger)"><?= $count['fail'] + $count['missing'] + $count['skip'] ?></b><span>cases fail / no result</span></div>
      <div class="tm-tile"><b style="color:var(--text-muted)"><?= $count['not_built'] ?></b><span>not built</span></div>
      <div class="tm-tile"><b><?= $passed ?> / <?= $ran ?></b><span>tests passed (<?= count($referenced) ?> mapped)</span></div>
      <div class="tm-tile"><b><?= number_format($seconds, 1) ?>s</b><span>test time</span></div>
    </div>
    <div class="tm-filters" role="group" aria-label="Filter by business type">
      <button type="button" class="on" data-tm-type="">All types</button>
<?php foreach ($matrix['types'] as $code => $name): ?>
      <button type="button" data-tm-type="<?= $e($code) ?>"><?= $e($name) ?></button>
<?php endforeach; ?>
    </div>
<?php foreach ($matrix['stages'] as $stage => $stageName): if (empty($rows[$stage])) continue; ?>
    <div class="tm-stage" data-tm-stage><?= $e($stageName) ?></div>
    <div class="tm-scroll"><table class="tm-table">
      <thead><tr><th style="width:70px">ID</th><th>Case</th><th style="width:150px">Business types</th><th style="width:80px">Support</th><th style="width:96px">Result</th><th style="width:250px">Tests</th></tr></thead>
      <tbody>
<?php foreach ($rows[$stage] as [$case, $result, $tests]): ?>
        <tr data-tm-types="<?= $e(implode(' ', $case['types'])) ?>">
          <td class="tm-id"><?= $e($case['id']) ?></td>
          <td><div class="tm-title"><?= $e($case['title']) ?></div>
<?php if (!empty($case['detail'])): ?>            <div class="tm-detail"><?= $e($case['detail']) ?></div>
<?php endif; if (!empty($case['note'])): ?>            <div class="tm-note"><?= $e($case['note']) ?></div>
<?php endif; ?>          </td>
          <td><?php foreach ($case['types'] as $type): ?><span class="tm-chip"><?= $e($type) ?></span><?php endforeach; ?></td>
          <td class="tm-support-<?= $case['status'] ?>"><?= $support[$case['status']] ?></td>
          <td><span class="tm-res tm-<?= $result ?>"><?= $label[$result] ?></span></td>
          <td><?php if ($tests): ?><details><summary><?= count($tests) ?> test<?= count($tests) > 1 ? 's' : '' ?></summary><ul>
<?php foreach ($tests as $test): ?>            <li><span class="tm-res tm-<?= $test['state'] ?>"><?= $label[$test['state']] ?></span> <?= $e($test['name']) ?><?= $test['time'] ? ' ('.number_format($test['time'], 2).'s)' : '' ?><?= $test['state'] !== 'pass' && $test['message'] ? ' &mdash; '.$e($test['message']) : '' ?></li>
<?php endforeach; ?>          </ul></details><?php else: ?><span style="color:var(--text-muted)">&mdash;</span><?php endif; ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table></div>
<?php endforeach; ?>
    <div class="tm-stage">Gaps found (backlog)</div>
    <div class="tm-gaps">
<?php foreach ($matrix['cases'] as $case): if ($case['status'] === 'built') continue; ?>
      <div class="tm-gap<?= $case['status'] === 'not_built' ? ' nb' : '' ?>"><b><?= $e($case['id']) ?> &middot; <?= $e($case['title']) ?></b> &mdash; <?= $support[$case['status']] ?>. <?= $e($case['note'] ?? '') ?></div>
<?php endforeach; ?>
    </div>
  </div>

  <div class="section-title" id="textile-matrix-log">
    Scenario Test Execution Log
    <span>Input given vs result obtained &middot; recorded by each PHPUnit test from the posted database</span>
  </div>
  <div class="table-container tm-log">
    <table>
      <thead><tr><th>#</th><th>Test case &amp; module</th><th>Status</th><th>Input given (documents posted)</th><th>Result obtained (stock / ledger / open items)</th><th>Screenshot</th></tr></thead>
      <tbody>
<?php foreach ($matrix['cases'] as $case): [$result, $tests] = caseResult($case, $results); ?>
<?php if (!$tests): ?>
        <tr data-tm-types="<?= $e(implode(' ', $case['types'])) ?>">
          <td><strong><?= $e($case['id']) ?></strong></td>
          <td><div style="font-weight:700;color:#fff;"><?= $e($case['title']) ?></div><div style="font-size:11px;color:var(--text-muted);margin-top:2px;"><?= $e($matrix['stages'][substr($case['id'], 0, 3)] ?? '') ?></div></td>
          <td><span class="badge badge-info">NOT BUILT</span></td>
          <td colspan="3" style="color:var(--text-muted);font-size:12px;"><?= $e($case['note'] ?? 'No code exists for this case yet.') ?></td>
        </tr>
<?php endif; foreach ($tests as $i => $test): $proof = $evidence[$test['name']] ?? []; [$class, $method] = explode('::', $test['name']); ?>
        <tr data-tm-types="<?= $e(implode(' ', $case['types'])) ?>">
          <td><strong><?= $e($case['id']) ?><?= count($tests) > 1 ? '.'.($i + 1) : '' ?></strong></td>
          <td>
            <div style="font-weight:700;color:#fff;"><?= $e($case['title']) ?></div>
            <div style="font-size:11px;color:var(--text-muted);margin-top:2px;word-break:break-word;"><?= $e($class) ?> &middot; <?= $e(str_replace('_', ' ', preg_replace('/^test_/', '', $method))) ?></div>
            <div style="font-size:10px;color:var(--accent);margin-top:4px;">&#9201; <?= (int) round($test['time'] * 1000) ?>ms<?= $case['status'] === 'partial' ? ' &middot; partial support' : '' ?></div>
          </td>
          <td><span class="badge <?= $test['state'] === 'pass' ? 'badge-pass' : 'badge-fail' ?>"><?= $label[$test['state']] ?></span></td>
          <td><pre class="code-block"><?= $e($proof ? ($proof['input'] ?? [] ? $json($proof['input']) : 'No documents posted (setup or refusal case)') : 'No evidence recorded (run with: php tests/Support/render_textile_matrix.php run)') ?></pre></td>
          <td><pre class="code-block result-block"><?= $e($test['state'] !== 'pass' ? $test['message'] : ($proof ? $json(($proof['result'] ?? []) ?: ['effect' => 'No stock, ledger or open-item change (configuration or refusal case)']) : '')) ?></pre></td>
          <td><?php foreach ($proof['prints'] ?? [] as $print): if (isset($images[$print])): ?><img src="<?= $e($images[$print]) ?>" class="thumb-img" onclick="openModal('<?= $e($images[$print]) ?>')" alt="<?= $e($print) ?>" loading="lazy" style="margin-bottom:6px;"><?php endif; endforeach; ?><?= empty($proof['prints']) ? '<span style="color:var(--text-muted);font-size:11px;">No printed document</span>' : '' ?></td>
        </tr>
<?php endforeach; endforeach; ?>
      </tbody>
    </table>
  </div>
  <script>
    document.querySelectorAll('[data-tm-type]').forEach(function (button) {
      button.addEventListener('click', function () {
        document.querySelectorAll('[data-tm-type]').forEach(function (b) { b.classList.toggle('on', b === button); });
        var type = button.getAttribute('data-tm-type');
        document.querySelectorAll('tr[data-tm-types]').forEach(function (row) {
          row.style.display = !type || row.getAttribute('data-tm-types').split(' ').indexOf(type) !== -1 ? '' : 'none';
        });
      });
    });
  </script>
  <!-- TEXTILE-MATRIX:END -->
<?php
$block = ob_get_clean();

$html = file_get_contents($report);
// The report is saved with CRLF line endings; keep them.
$eol = str_contains($html, "\r\n") ? "\r\n" : "\n";
$block = str_replace("\n", $eol, str_replace("\r\n", "\n", rtrim($block)));
$html = preg_replace('/  <!-- TEXTILE-MATRIX:START -->.*?<!-- TEXTILE-MATRIX:END -->/s', str_replace(['\\', '$'], ['\\\\', '\$'], $block), $html, 1, $replaced);
if (!$replaced) {
    fwrite(STDERR, "Markers <!-- TEXTILE-MATRIX:START/END --> not found in $report\n");
    exit(1);
}

// Each studio scenario card shows the matrix cases that answer it.
$html = preg_replace('/\s*<div class="matrix-note"[^>]*>.*?<\/div>/s', '', $html);
$html = preg_replace_callback('/(<span>Scenario (\d\.[A-E]):.*?)(\r?\n([ \t]*)<div class="scenario-pills">)/s', function ($match) use ($byCard, $e, $label, $eol) {
    $cases = $byCard[$match[2]] ?? [];
    $parts = $cases ? array_map(fn ($item) => '<span class="tm-res tm-'.$item[1].'">'.$label[$item[1]].'</span> '.$e($item[0]['id'])
        .($item[0]['status'] === 'partial' ? ' (partial)' : ''), $cases) : ['<span class="tm-res tm-missing">NO CASE</span>'];
    return $match[1].$eol.$match[4].'<div class="matrix-note" data-matrix-card="'.$match[2].'">Matrix: '.implode(' ', $parts).' &middot; <a href="#textile-matrix" style="color:var(--info)">results</a></div>'.$match[3];
}, $html);

file_put_contents($report, $html);
printf("Rendered %d cases: %d built + pass, %d partial (%d pass), %d fail/no result, %d not built. %d/%d tests passed.\n",
    count($matrix['cases']), $builtPass, $partial, $partialPass, $count['fail'] + $count['missing'] + $count['skip'], $count['not_built'], $passed, $ran);
exit($count['fail'] + $count['missing'] ? 1 : 0);
