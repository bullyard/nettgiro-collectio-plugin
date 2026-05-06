<?php

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) die("This file cannot be executed directly");

/** @var string $currentSlug */
/** @var string $curpage */

if ($_SESSION['admin'] != 1) {
    echo "Du har ikke tilgang til denne siden";
    return;
}

$counts   = collectio_cleanup_get_counts();
$pipeline = collectio_cleanup_get_pipeline_stats();
$total    = array_sum($counts);

$stuckDays = collectio_cleanup_stuck_days();

$sectionMeta = [
    'canceled_no_case' => [
        'title' => 'Avbrutt uten saksnummer',
        'desc'  => 'Status <code>canceled</code> uten <code>collectio_case_*</code>. Sak ble eksplisitt avbrutt og aldri overført til Collectio. Alltid trygt å rydde.',
        'badge' => 'secondary',
        'bulk'  => true,
    ],
    'stuck_queued_no_case' => [
        'title' => 'Kø med feilet innsending',
        'desc'  => '<code>queued</code> uten saksnummer hvor varsel var sendt (<code>followup ≥ 2</code>), fakturaen forfalt for mer enn <b>'.$stuckDays.' dager siden</b>, og er fortsatt ubetalt. Overføring burde ha skjedd men gjorde det ikke. Påvirker <b>ikke</b> fakturaer som ennå venter på 14-dagers-vinduet.',
        'badge' => 'warning',
        'bulk'  => true,
    ],
    'paid_queued' => [
        'title' => 'Kø med betalt faktura',
        'desc'  => '<code>queued</code> hvor fakturaen er fullt betalt. Trygt å rydde.',
        'badge' => 'info',
        'bulk'  => true,
    ],
    'serious_no_case' => [
        'title' => 'Alvorlig: aktiv status uten saksnummer',
        'desc'  => '<code>processing</code>/<code>completed</code>/<code>recall</code> uten saksnummer. Kun manuell gjennomgang — ingen bulk-rydding.',
        'badge' => 'danger',
        'bulk'  => false,
    ],
    'orphan_invoice_deleted' => [
        'title' => 'Faktura slettet',
        'desc'  => 'Metadata peker på faktura som ikke finnes. Trygt å rydde.',
        'badge' => 'secondary',
        'bulk'  => true,
    ],
    'orphan_followup' => [
        'title' => 'Uparet <code>collectio_followup_*</code>',
        'desc'  => 'Followup-stage uten tilhørende status. Trygt å rydde.',
        'badge' => 'secondary',
        'bulk'  => true,
    ],
];

?>

<main class="container">
    <div class="row">
        <div class="col-xl-12">

            <div class="row align-items-center mainTitleRow">
                <div class="col-sm-8 mt-2">
                    <h3 class="mt-0">
                        <i class="hio hio-wrench-screwdriver"></i> Collectio — Metadata-rydding
                    </h3>
                    <p class="text-muted mb-0">
                        Finn og rydd opp inkonsistente <code>collectio_*</code> metadata-oppføringer.
                        Store kategorier prosesseres i batcher.
                    </p>
                </div>
                <div class="col-sm-4 text-sm-right mt-2">
                    <span id="total-badge" class="badge badge-<?=$total > 0 ? 'warning' : 'success'?> py-2 px-3">
                        <?=number_format($total, 0, ',', ' ')?> funn totalt
                    </span>
                    <button type="button" id="refresh-counts" class="btn btn-sm btn-outline-secondary ml-2" title="Oppdater tall">
                        <i class="hio hio-arrow-path"></i>
                    </button>
                </div>
            </div>

            <?php
                $stc = $pipeline['status'];
                $fu  = $pipeline['followup'];
                $win = $pipeline['window'];
                $cfg = $pipeline['config'];

                $statusMeta = [
                    'processing' => ['label' => 'Under oppfølging', 'color' => 'danger',    'icon' => 'hio hio-arrow-path'],
                    'queued'     => ['label' => 'I kø',              'color' => 'warning',   'icon' => 'hio hio-clock'],
                    'completed'  => ['label' => 'Fullført',          'color' => 'success',   'icon' => 'hio hio-check-circle'],
                    'recall'     => ['label' => 'Tilbakekalt',       'color' => 'info',      'icon' => 'hio hio-arrow-uturn-left'],
                    'canceled'   => ['label' => 'Avbrutt',           'color' => 'secondary', 'icon' => 'hio hio-x-circle'],
                ];
            ?>

            <div class="pipeline-card card card-subtle bg-white border-0 shadow-sm mt-4">
                <div class="card-body">
                    <h6 class="text-muted text-uppercase mb-3" style="font-size: 11px; letter-spacing: .5px;">
                        <i class="hio hio-chart-bar"></i> Pipeline-oversikt
                    </h6>

                    <!-- Row 1: status distribution -->
                    <div class="row">
                        <?php foreach ($statusMeta as $key => $meta): $v = $stc[$key] ?? 0; ?>
                            <div class="col-6 col-md-4 col-xl mb-2">
                                <div class="pipeline-stat">
                                    <div class="pipeline-stat__label">
                                        <span class="pipeline-dot pipeline-dot--<?=$meta['color']?>"></span>
                                        <?=$meta['label']?>
                                    </div>
                                    <div class="pipeline-stat__value" data-stat="status.<?=$key?>"><?=number_format($v, 0, ',', ' ')?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($stc['queued'] > 0): ?>
                        <hr class="my-3">

                        <!-- Row 2: queued followup-stage breakdown -->
                        <div class="row align-items-center">
                            <div class="col-12 col-md-3 mb-2 mb-md-0">
                                <small class="text-muted text-uppercase" style="font-size: 10px; letter-spacing: .5px;">Followup-stage (av <?=$stc['queued']?> i kø)</small>
                            </div>
                            <div class="col-12 col-md-9">
                                <div class="row">
                                    <div class="col-6 col-md-3 mb-1">
                                        <div class="pipeline-substat">
                                            <span class="badge badge-light border"><?=$fu['fu1']?></span>
                                            <small class="text-muted">Stage 1 — venter på varsel</small>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3 mb-1">
                                        <div class="pipeline-substat">
                                            <span class="badge badge-light border"><?=$fu['fu2']?></span>
                                            <small class="text-muted">Stage 2 — varsel sendt</small>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3 mb-1">
                                        <div class="pipeline-substat">
                                            <span class="badge badge-light border"><?=$fu['fu3']?></span>
                                            <small class="text-muted">Stage 3 — overført</small>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3 mb-1">
                                        <div class="pipeline-substat">
                                            <span class="badge badge-light border"><?=$fu['fu0'] + $fu['fu_null']?></span>
                                            <small class="text-muted">Stage 0 / mangler</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">

                        <!-- Row 3: queue timing window -->
                        <div class="row align-items-center">
                            <div class="col-12 col-md-3 mb-2 mb-md-0">
                                <small class="text-muted text-uppercase" style="font-size: 10px; letter-spacing: .5px;">Kø-vindu</small>
                            </div>
                            <div class="col-12 col-md-9">
                                <div class="row">
                                    <div class="col-12 col-md-4 mb-1">
                                        <div class="pipeline-substat">
                                            <span class="badge badge-success"><?=$win['waiting']?></span>
                                            <small class="text-muted">Legitim venteperiode<br>(innenfor <?=$cfg['deadline_days']?> dager etter forfall)</small>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-4 mb-1">
                                        <div class="pipeline-substat">
                                            <span class="badge badge-info"><?=$win['ready_to_submit']?></span>
                                            <small class="text-muted">Klar for overføring<br>(<?=$cfg['deadline_days']?>–<?=$cfg['stuck_days']?> dager etter forfall)</small>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-4 mb-1">
                                        <div class="pipeline-substat">
                                            <span class="badge badge-danger"><?=$win['stuck']?></span>
                                            <small class="text-muted">Fast<br>(over <?=$cfg['stuck_days']?> dager etter forfall)</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($total === 0): ?>
                <div class="alert alert-success mt-4">
                    <i class="hio hio-check-circle"></i>
                    Ingen inkonsistenser funnet. Alt ser ryddig ut.
                </div>
            <?php endif; ?>

            <?php foreach ($sectionMeta as $key => $meta):
                $count = $counts[$key] ?? 0;
            ?>
                <section class="cleanup-section mt-3" data-category="<?=htmlspecialchars($key)?>">
                    <div class="card card-subtle bg-white border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between flex-wrap">
                                <div class="flex-grow-1">
                                    <h5 class="mb-1">
                                        <span class="badge badge-<?=$meta['badge']?> mr-2" data-count><?=number_format($count, 0, ',', ' ')?></span>
                                        <?=$meta['title']?>
                                    </h5>
                                    <small class="text-muted"><?=$meta['desc']?></small>
                                </div>
                                <div class="ml-auto d-flex align-items-center">
                                    <?php if ($count > 0): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary cleanup-sample-btn mr-2" data-category="<?=htmlspecialchars($key)?>">
                                            <i class="hio hio-magnifying-glass-plus"></i> Se eksempel
                                        </button>
                                        <?php if ($meta['bulk']): ?>
                                            <button type="button" class="btn btn-sm btn-danger cleanup-bulk-btn" data-category="<?=htmlspecialchars($key)?>">
                                                <i class="hio hio-trash"></i> Rydd alle
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-success small"><i class="hio hio-check-circle"></i> tomt</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Progress bar (hidden until bulk job starts) -->
                            <div class="cleanup-progress mt-3 d-none">
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-danger" style="width: 0%;"></div>
                                </div>
                                <small class="text-muted progress-label">0 slettet…</small>
                            </div>

                            <!-- Drill-down panel (hidden until "Se eksempel" clicked) -->
                            <div class="cleanup-drilldown mt-3 d-none">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0 table-sm cleanup-table">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>UID</th>
                                                <th>Foretak</th>
                                                <th>Faktura</th>
                                                <th>Status</th>
                                                <th>Saksnr.</th>
                                                <th>Fakturastatus</th>
                                                <th class="text-right">Beløp</th>
                                                <th class="text-right">Rest</th>
                                                <th>Registrert</th>
                                                <th class="text-right">Handling</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-2">
                                    <button type="button" class="btn btn-sm btn-light cleanup-load-more" data-category="<?=htmlspecialchars($key)?>">
                                        Last flere
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>
                </section>
            <?php endforeach; ?>

        </div>
    </div>
</main>

<style>
    .cleanup-table th { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; }
    .cleanup-table td { vertical-align: middle; font-size: 13px; }
    .cleanup-table code { font-size: 12px; }
    .cleanup-section .card-body { padding: 1rem 1.25rem; }

    /* Pipeline overview */
    .pipeline-stat { line-height: 1.2; }
    .pipeline-stat__label {
        font-size: 12px;
        color: #6c757d;
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 2px;
    }
    .pipeline-stat__value {
        font-size: 22px;
        font-weight: 600;
        color: #1d1d1f;
        font-variant-numeric: tabular-nums;
    }
    .pipeline-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #8e8e93;
        display: inline-block;
    }
    .pipeline-dot--warning   { background: #f5a623; }
    .pipeline-dot--danger    { background: #ff3b30; }
    .pipeline-dot--success   { background: #34c759; }
    .pipeline-dot--info      { background: #007aff; }
    .pipeline-dot--secondary { background: #a0a0a5; }

    .pipeline-substat { display: flex; align-items: flex-start; gap: 8px; }
    .pipeline-substat .badge { min-width: 34px; padding: 4px 8px; font-size: 12px; font-variant-numeric: tabular-nums; }
    .pipeline-substat small { line-height: 1.3; }
</style>

<script>
$(function () {
    const AJAX_URL  = '/plugins/collectio/ajax/ajax.php';
    const PAGE_SIZE = 100;
    const BATCH     = 500;

    const drilldownOffsets = {};

    function fmtMoney(v) {
        if (v === null || v === undefined || v === '') return '—';
        const n = parseFloat(v);
        if (isNaN(n)) return '—';
        return n.toLocaleString('no-NO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtDate(d) {
        if (!d) return '';
        const date = new Date(d.replace(' ', 'T'));
        if (isNaN(date.getTime())) return d;
        return date.toLocaleDateString('no-NO') + ' ' +
               date.toLocaleTimeString('no-NO', { hour: '2-digit', minute: '2-digit' });
    }

    function renderRow(row) {
        const invoiceLink = row.hash
            ? '<a href="/?go=showinvoice&hash=' + encodeURIComponent(row.hash) + '" target="_blank">#' + (row.invoiceid || '?') + '</a>' +
              '<br><small class="text-muted">id: ' + row.invoice_id + '</small>'
            : '<span class="text-muted">id: ' + row.invoice_id + '</span>';

        const caseCell = row.case_no
            ? '<code>' + $('<span>').text(row.case_no).html() + '</code>'
            : '<span class="text-muted">—</span>';

        const invoiceStatusCell = row.invoice_status
            ? '<span class="text-' + (row.invoice_status === 'payed' ? 'success' : 'dark') + '">' + row.invoice_status + '</span>'
            : '<span class="text-danger">faktura slettet</span>';

        const followupOnly = row.status === 'orphan_followup' ? ' data-only="followup"' : '';

        return '' +
            '<tr data-invoice-id="' + row.invoice_id + '" data-uid="' + row.uid + '">' +
                '<td>' + row.uid + '</td>' +
                '<td>' + $('<span>').text(row.company_name || '—').html() +
                    (row.company_email ? '<br><small class="text-muted">' + $('<span>').text(row.company_email).html() + '</small>' : '') +
                '</td>' +
                '<td>' + invoiceLink + '</td>' +
                '<td><code>' + $('<span>').text(row.status || '').html() + '</code>' +
                    (row.followup_stage !== null && row.followup_stage !== undefined ? ' <small class="text-muted">fu=' + row.followup_stage + '</small>' : '') +
                '</td>' +
                '<td>' + caseCell + '</td>' +
                '<td>' + invoiceStatusCell + '</td>' +
                '<td class="text-right">' + fmtMoney(row.value) + '</td>' +
                '<td class="text-right">' + fmtMoney(row.last_value) + '</td>' +
                '<td><small>' + fmtDate(row.status_datestamp) + '</small></td>' +
                '<td class="text-right">' +
                    '<button type="button" class="btn btn-xs btn-outline-danger cleanup-single-btn" ' +
                        'data-invoice-id="' + row.invoice_id + '" data-uid="' + row.uid + '"' + followupOnly + '>' +
                        '<i class="hio hio-trash"></i> Rydd' +
                    '</button>' +
                '</td>' +
            '</tr>';
    }

    // ---- drill-down sample ---------------------------------------------------

    function loadSample($section, category, reset) {
        if (reset) drilldownOffsets[category] = 0;
        const offset = drilldownOffsets[category] || 0;
        const $drill = $section.find('.cleanup-drilldown');
        const $tbody = $drill.find('tbody');
        const $more  = $drill.find('.cleanup-load-more');

        $more.prop('disabled', true).text('Laster…');
        $drill.removeClass('d-none');

        $.ajax({
            url: AJAX_URL, type: 'POST', dataType: 'JSON',
            data: { req: 'cleanup_list', category: category, limit: PAGE_SIZE, offset: offset }
        }).done(function (resp) {
            if (resp && resp.status === 'ok') {
                if (reset) $tbody.empty();
                const rows = resp.rows || [];
                rows.forEach(function (r) { $tbody.append(renderRow(r)); });
                drilldownOffsets[category] = offset + rows.length;

                if (rows.length < PAGE_SIZE) {
                    $more.addClass('d-none');
                } else {
                    $more.removeClass('d-none').prop('disabled', false).text('Last flere (+' + PAGE_SIZE + ')');
                }
            } else {
                $.fn.alert((resp && resp.message) || 'Kunne ikke hente liste.', 'Feil');
                $more.prop('disabled', false).text('Last flere');
            }
        }).fail(function () {
            $more.prop('disabled', false).text('Last flere');
            $.fn.alert('Kommunikasjonsfeil.', 'Feil');
        });
    }

    $(document).on('click', '.cleanup-sample-btn', function () {
        const $section = $(this).closest('section');
        const category = $(this).data('category');
        loadSample($section, category, true);
    });

    $(document).on('click', '.cleanup-load-more', function () {
        const $section = $(this).closest('section');
        const category = $(this).data('category');
        loadSample($section, category, false);
    });

    // ---- single-row cleanup -------------------------------------------------

    $(document).on('click', '.cleanup-single-btn', function () {
        const $btn = $(this);
        const $row = $btn.closest('tr');
        const invoiceId = $btn.data('invoice-id');
        const uid = $btn.data('uid');
        const only = $btn.data('only') || '';

        $.fn.confirm('Slette metadata for faktura-id ' + invoiceId + ' (uid ' + uid + ')?', function (ans) {
            if (!ans) return;
            $btn.prop('disabled', true);

            $.ajax({
                url: AJAX_URL, type: 'POST', dataType: 'JSON',
                data: { req: 'cleanup_delete_single', invoice_id: invoiceId, target_uid: uid, only: only }
            }).done(function (resp) {
                if (resp && resp.status === 'ok') {
                    $row.fadeOut(200, function () { $(this).remove(); });
                    refreshCounts();
                } else {
                    $btn.prop('disabled', false);
                    $.fn.alert((resp && resp.message) || 'Kunne ikke rydde.', 'Feil');
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                $.fn.alert('Kommunikasjonsfeil.', 'Feil');
            });
        });
    });

    // ---- progressive bulk cleanup -------------------------------------------

    function runBulk(category, $section, initialTotal) {
        const $progressWrap = $section.find('.cleanup-progress');
        const $bar          = $progressWrap.find('.progress-bar');
        const $label        = $progressWrap.find('.progress-label');
        const $bulkBtn      = $section.find('.cleanup-bulk-btn');
        const $sampleBtn    = $section.find('.cleanup-sample-btn');

        $progressWrap.removeClass('d-none');
        $bulkBtn.prop('disabled', true);
        $sampleBtn.prop('disabled', true);

        let deletedTotal = 0;
        const total = initialTotal;

        function tick() {
            $.ajax({
                url: AJAX_URL, type: 'POST', dataType: 'JSON',
                data: { req: 'cleanup_delete_bulk', category: category, batch: BATCH }
            }).done(function (resp) {
                if (!resp || resp.status !== 'ok') {
                    $.fn.alert((resp && resp.message) || 'Rydding feilet.', 'Feil');
                    $bulkBtn.prop('disabled', false);
                    $sampleBtn.prop('disabled', false);
                    return;
                }
                deletedTotal += (resp.processed || 0);
                const pct = total > 0 ? Math.min(100, Math.round((deletedTotal / total) * 100)) : 100;
                $bar.css('width', pct + '%');
                $label.text(deletedTotal.toLocaleString('no-NO') + ' / ' +
                            total.toLocaleString('no-NO') + ' prosessert (' +
                            resp.remaining.toLocaleString('no-NO') + ' gjenstår)');

                // Update section's own count badge
                $section.find('[data-count]').text(resp.remaining.toLocaleString('no-NO'));

                if (resp.done || resp.remaining <= 0) {
                    $bar.css('width', '100%').removeClass('bg-danger').addClass('bg-success');
                    $label.text('Fullført — ' + deletedTotal.toLocaleString('no-NO') + ' prosessert.');
                    refreshCounts();
                    setTimeout(function () { $progressWrap.addClass('d-none'); }, 2000);
                } else {
                    tick();
                }
            }).fail(function () {
                $bulkBtn.prop('disabled', false);
                $sampleBtn.prop('disabled', false);
                $.fn.alert('Kommunikasjonsfeil under bulk-rydding.', 'Feil');
            });
        }

        tick();
    }

    $(document).on('click', '.cleanup-bulk-btn', function () {
        const $section = $(this).closest('section');
        const category = $(this).data('category');
        const count    = parseInt(String($section.find('[data-count]').text()).replace(/\s/g, ''), 10) || 0;

        if (count === 0) return;

        $.fn.confirm(
            'Rydde <b>' + count.toLocaleString('no-NO') + '</b> metadata-oppføringer i kategorien <b>' + category + '</b>?' +
            '<br><small>Prosesseres i batcher à ' + BATCH + ' om gangen. Handlingen kan ikke angres.</small>',
            function (ans) {
                if (ans) runBulk(category, $section, count);
            }
        );
    });

    // ---- refresh counts -----------------------------------------------------

    function refreshCounts() {
        $.ajax({
            url: AJAX_URL, type: 'POST', dataType: 'JSON',
            data: { req: 'cleanup_counts' }
        }).done(function (resp) {
            if (!resp || resp.status !== 'ok') return;

            let total = 0;
            Object.keys(resp.counts).forEach(function (k) {
                const v = resp.counts[k];
                total += v;
                const $sec = $('section[data-category="' + k + '"]');
                $sec.find('[data-count]').text(v.toLocaleString('no-NO'));
                if (v === 0) {
                    $sec.find('.cleanup-sample-btn, .cleanup-bulk-btn').prop('disabled', true);
                }
            });
            $('#total-badge').text(total.toLocaleString('no-NO') + ' funn totalt');

            if (resp.pipeline && resp.pipeline.status) {
                Object.keys(resp.pipeline.status).forEach(function (k) {
                    $('[data-stat="status.' + k + '"]').text(
                        (resp.pipeline.status[k] || 0).toLocaleString('no-NO')
                    );
                });
            }
        });
    }

    $('#refresh-counts').on('click', refreshCounts);
});
</script>
