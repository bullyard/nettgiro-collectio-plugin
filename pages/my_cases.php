<?php

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) die("This file cannot be executed directly");

/** @var int|string|null $uid */
/** @var string $currentSlug */
/** @var string $curpage */

if (!is_numeric($uid)) die("Access denied");

$creditorID = \Metadata::get('collectio_creditor_id', $uid);
$db         = \DB::getInstance();

$statusFilter = isset($_REQUEST['status']) ? preg_replace('/[^a-z]/', '', (string)$_REQUEST['status']) : 'all';
$search       = isset($_REQUEST['q']) ? trim((string)$_REQUEST['q']) : '';

$allStatuses = [
    'queued'     => ['label' => 'I kø',          'badge' => 'warning', 'icon' => 'hio hio-clock'],
    'processing' => ['label' => 'Under oppfølging', 'badge' => 'danger',  'icon' => 'hio hio-arrow-path'],
    'completed'  => ['label' => 'Fullført',      'badge' => 'success', 'icon' => 'hio hio-check-circle'],
    'recall'     => ['label' => 'Tilbakekalt',   'badge' => 'info',    'icon' => 'hio hio-arrow-uturn-left'],
    'canceled'   => ['label' => 'Avbrutt',       'badge' => 'secondary', 'icon' => 'hio hio-x-circle'],
];

$counts = array_fill_keys(array_keys($allStatuses), 0);
$counts['all'] = 0;

$countStmt = $db->prepare("
    SELECT ms.meta_data AS status, COUNT(*) AS cnt
    FROM metadata ms
    INNER JOIN invoice i
        ON i.id = CAST(SUBSTRING(ms.meta_key, 18) AS UNSIGNED)
       AND i.uid = ms.uid
    WHERE ms.uid = ?
      AND ms.meta_key LIKE 'collectio\\_status\\_%'
    GROUP BY ms.meta_data
");
$countStmt->bind_param('i', $uid);
$countStmt->execute();
$countRes = $countStmt->get_result();
while ($row = $countRes->fetch_assoc()) {
    if (isset($counts[$row['status']])) {
        $counts[$row['status']] = (int)$row['cnt'];
    }
    $counts['all'] += (int)$row['cnt'];
}
$countStmt->close();

$sql = "
    SELECT
        i.id, i.hash, i.invoiceid, i.recipient, i.value, i.last_value,
        i.duedate, i.status AS invoice_status, i.datestamp AS invoice_datestamp,
        ms.meta_data AS case_status,
        ms.datestamp AS status_datestamp,
        mc.meta_data AS case_no,
        mf.meta_data AS followup_stage,
        c.name AS client_name,
        c.client_id AS client_id,
        ls.price AS service_price,
        ls.datestamp AS service_datestamp
    FROM metadata ms
    INNER JOIN invoice i
        ON i.id = CAST(SUBSTRING(ms.meta_key, 18) AS UNSIGNED)
       AND i.uid = ms.uid
    LEFT JOIN metadata mc
        ON mc.uid = ms.uid
       AND mc.meta_key = CONCAT('collectio_case_', i.id)
    LEFT JOIN metadata mf
        ON mf.uid = ms.uid
       AND mf.meta_key = CONCAT('collectio_followup_', i.id)
    LEFT JOIN clients c
        ON c.uid = i.uid
       AND c.client_id = i.recipient
    LEFT JOIN log_services ls
        ON ls.uid = i.uid
       AND ls.ref_id = i.id
       AND ls.type = 'collectio'
    WHERE ms.uid = ?
      AND ms.meta_key LIKE 'collectio\\_status\\_%'
";

$params = [$uid];
$types  = 'i';

if ($statusFilter !== 'all' && isset($allStatuses[$statusFilter])) {
    $sql .= " AND ms.meta_data = ? ";
    $params[] = $statusFilter;
    $types   .= 's';
}

if ($search !== '') {
    $sql .= " AND (
        CAST(i.invoiceid AS CHAR) LIKE CONCAT('%', ?, '%')
        OR c.name LIKE CONCAT('%', ?, '%')
        OR mc.meta_data LIKE CONCAT('%', ?, '%')
    ) ";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types   .= 'sss';
}

$sql .= " ORDER BY ms.datestamp DESC, i.duedate DESC";

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$cases = [];
while ($row = $result->fetch_assoc()) {
    $cases[] = $row;
}
$stmt->close();

$hasCreditor = is_numeric($creditorID);

?>

<main class="container">
    <div class="row">
        <div class="col-xl-12">

            <div class="row align-items-center mainTitleRow">
                <div class="col-sm-8 mt-2">
                    <h3 class="mt-0">
                        <i class="hio hio-shield-check"></i> Innkrevingssaker
                    </h3>
                    <p class="text-muted mb-0">
                        Oversikt over alle fakturaer som er sendt til automatisk oppfølging hos Collectio AS
                        <?php if ($hasCreditor): ?>
                            <span class="badge badge-light border ml-2">Kreditor-ID: <b><?=htmlspecialchars((string)$creditorID)?></b></span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-sm-4 text-sm-right mt-2">
                    <a href="mailto:post@collectio.no" class="btn btn-sm btn-outline-primary">
                        <i class="hio hio-envelope"></i> Kontakt Collectio
                    </a>
                    <a href="tel:+4722806290" class="btn btn-sm btn-outline-primary">
                        <i class="hio hio-phone"></i> 22 80 62 90
                    </a>
                </div>
            </div>

            <?php if (!$hasCreditor): ?>
                <div class="mt-3">
                    <?php AlertHelper::al(
                        'Inkassotjenesten er ikke aktivert',
                        'Du må godta <a href="#" class="collectio_accept_terms">vilkår og betingelser</a> for å aktivere automatisk oppfølging (purring/inkasso).',
                        'warning', '!'
                    ); ?>
                </div>
            <?php endif; ?>

            <!-- Status filter pills -->
            <nav class="collectio-filter-pills mt-4 mb-3" aria-label="Filtrer etter status">
                <?php
                $summary = [
                    'all' => ['label' => 'Alle', 'dot' => null],
                ] + array_map(function($s){
                    return ['label' => $s['label'], 'dot' => $s['badge']];
                }, $allStatuses);

                foreach ($summary as $key => $meta):
                    $isActive = ($statusFilter === $key);
                    $count    = $counts[$key] ?? 0;
                    $qs       = http_build_query(['go' => $curpage, 'status' => $key, 'q' => $search]);
                ?>
                    <a href="<?=$currentSlug?>?<?=$qs?>" class="collectio-pill <?=$isActive ? 'is-active' : ''?>">
                        <?php if (!empty($meta['dot'])): ?>
                            <span class="collectio-pill__dot collectio-pill__dot--<?=$meta['dot']?>"></span>
                        <?php endif; ?>
                        <span class="collectio-pill__label"><?=$meta['label']?></span>
                        <span class="collectio-pill__count"><?=$count?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <!-- Filter form -->
            <form method="get" action="" class="mt-1 mb-3">
                <input type="hidden" name="go" value="<?=htmlspecialchars($curpage)?>">
                <input type="hidden" name="status" value="<?=htmlspecialchars($statusFilter)?>">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text bg-white pill-left">
                            <i class="hio hio-magnifying-glass text-muted"></i>
                        </span>
                    </div>
                    <input type="text" name="q" value="<?=htmlspecialchars($search)?>"
                        class="form-control border-left-0"
                        placeholder="Søk etter fakturanummer, kundenavn eller saksnummer…">
                    <div class="input-group-append">
                        <?php if ($search !== ''): ?>
                            <a href="<?=$currentSlug?>?go=<?=htmlspecialchars($curpage)?>&status=<?=htmlspecialchars($statusFilter)?>" class="btn btn-outline-secondary">
                                <i class="hio hio-x-mark"></i>
                            </a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">Søk</button>
                    </div>
                </div>
            </form>

            <!-- Table -->
            <div class="card card-subtle bg-white border-0 shadow-sm">
                <div class="card-body p-0">
                    <?php if (empty($cases)): ?>
                        <div class="text-center py-5">
                            <i class="hio hio-inbox text-muted mb-3" style="font-size: 3rem;"></i>
                            <h5 class="text-muted">Ingen saker funnet</h5>
                            <p class="text-muted mb-0">
                                <?= $search !== '' || $statusFilter !== 'all'
                                    ? 'Prøv å endre filteret eller søket.'
                                    : 'Du har ingen innkrevingssaker ennå.' ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 collectio-cases-table">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Saksnr.</th>
                                        <th>Faktura</th>
                                        <th>Kunde</th>
                                        <th class="text-right">Beløp</th>
                                        <th>Forfall</th>
                                        <th>Opprettet</th>
                                        <th>Status</th>
                                        <th class="text-right">Handlinger</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($cases as $case):
                                    $caseStatus = $case['case_status'];
                                    $meta       = $allStatuses[$caseStatus] ?? ['label' => $caseStatus, 'badge' => 'secondary', 'icon' => 'hio hio-question-mark-circle'];
                                    $hash       = $case['hash'];
                                    $caseNo     = $case['case_no'];
                                    $invoiceUrl = $currentSlug . '?go=showinvoice&hash=' . urlencode($hash);
                                    $clientUrl  = $case['client_id'] ? $currentSlug . '?go=client&id=' . (int)$case['client_id'] : null;
                                ?>
                                    <tr data-hash="<?=htmlspecialchars($hash)?>" data-status="<?=htmlspecialchars($caseStatus)?>">
                                        <td>
                                            <?php if (!empty($caseNo)): ?>
                                                <span class="text-dark font-weight-bold"><?=htmlspecialchars($caseNo)?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?=$invoiceUrl?>" class="text-primary">#<?=htmlspecialchars($case['invoiceid'])?></a>
                                        </td>
                                        <td>
                                            <?php if ($clientUrl): ?>
                                                <a href="<?=$clientUrl?>" class="text-dark"><?=htmlspecialchars($case['client_name'] ?? 'Ukjent')?></a>
                                            <?php else: ?>
                                                <span class="text-muted"><?=htmlspecialchars($case['client_name'] ?? '—')?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right">
                                            <?=currency_nor2((float)$case['value'])?>
                                            <?php if ((float)$case['last_value'] > 0 && (float)$case['last_value'] !== (float)$case['value']): ?>
                                                <br><small class="text-muted">Rest: <?=currency_nor2((float)$case['last_value'])?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?=format_time_form($case['duedate'])?></small></td>
                                        <td><small><?=format_time_form($case['status_datestamp'])?></small></td>
                                        <td>
                                            <span class="badge badge-<?=$meta['badge']?>">
                                                <i class="<?=$meta['icon']?>"></i> <?=$meta['label']?>
                                            </span>
                                            <div class="collectio-status-live small text-muted mt-1 d-none"></div>
                                        </td>
                                        <td class="text-right">
                                            <?php $ddId = 'dropdownCaseActions_'.(int)$case['id']; ?>
                                            <div class="dropdown dropdown-config">
                                                <button class="btn btn-sm dropdown-toggle" type="button" id="<?=$ddId?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                    <i class="hio hio-ellipsis-horizontal"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-right" aria-labelledby="<?=$ddId?>" style="min-width: 195px;">
                                                    <a class="dropdown-item" href="<?=$invoiceUrl?>">
                                                        <i class="hio hio-document-text"></i> Se faktura
                                                    </a>
                                                    <?php if ($clientUrl): ?>
                                                        <a class="dropdown-item" href="<?=$clientUrl?>">
                                                            <i class="hio hio-identification"></i> Se kunde
                                                        </a>
                                                    <?php endif; ?>

                                                    <?php if ($caseStatus === 'processing' && !empty($caseNo)): ?>
                                                        <?php /*
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item collectio-get-status" href="#" data-hash="<?=htmlspecialchars($hash)?>">
                                                            <i class="hio hio-arrow-path"></i> Hent gjeldende status
                                                        </a>
                                                        <a class="dropdown-item text-danger collectio-recall-case" href="#" data-hash="<?=htmlspecialchars($hash)?>" data-case="<?=htmlspecialchars($caseNo)?>">
                                                            <i class="hio hio-arrow-uturn-left"></i> Tilbakekall sak
                                                        </a>
                                                        */ ?>
                                                    <?php endif; ?>

                                                    <?php if ($caseStatus === 'queued'): ?>
                                                        <div class="dropdown-divider"></div>
                                                        <a class="dropdown-item text-danger collectio-stop-case" href="#" data-hash="<?=htmlspecialchars($hash)?>">
                                                            <i class="hio hio-x-circle"></i> Stopp oppfølging
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-3 small text-muted">
                <i class="hio hio-information-circle"></i>
                Saker er kun synlige her dersom oppfølgingsstatus er registrert.
                Tilbakekall og status oppdateres direkte mot Collectio AS.
            </div>

        </div>
    </div>
</main>

<style>
    .collectio-cases-table th { font-size: 12px; text-transform: uppercase; letter-spacing: .5px; }
    .collectio-cases-table td { vertical-align: middle; }

    /* Minimal status filter pills */
    .collectio-filter-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin: 0;
    }
    .collectio-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        border-radius: 999px;
        background: rgba(0, 0, 0, 0.04);
        color: #1d1d1f;
        font-size: 13px;
        font-weight: 500;
        line-height: 1;
        text-decoration: none;
        transition: background-color .15s ease, color .15s ease;
        white-space: nowrap;
    }
    .collectio-pill:hover,
    .collectio-pill:focus {
        background: rgba(0, 0, 0, 0.08);
        color: #1d1d1f;
        text-decoration: none;
    }
    .collectio-pill.is-active {
        background: #1d1d1f;
        color: #fff;
    }
    .collectio-pill__count {
        font-variant-numeric: tabular-nums;
        font-weight: 500;
        font-size: 12px;
        color: rgba(0, 0, 0, 0.45);
    }
    .collectio-pill.is-active .collectio-pill__count {
        color: rgba(255, 255, 255, 0.75);
    }
    .collectio-pill__dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #8e8e93;
        flex-shrink: 0;
    }
    .collectio-pill__dot--warning   { background: #f5a623; }
    .collectio-pill__dot--danger    { background: #ff3b30; }
    .collectio-pill__dot--success   { background: #34c759; }
    .collectio-pill__dot--info      { background: #007aff; }
    .collectio-pill__dot--secondary { background: #a0a0a5; }
</style>

<script>
$(function () {
    const AJAX_URL = '/plugins/collectio/ajax/ajax.php';

    function disable($el) { $el.css('pointer-events', 'none').css('opacity', 0.6); }
    function enable($el)  { $el.css('pointer-events', '').css('opacity', 1); }

    $(document).on('click', '.collectio-get-status', function (e) {
        e.preventDefault();
        const $link  = $(this);
        const hash   = $link.data('hash');
        const $row   = $link.closest('tr');
        const $live  = $row.find('.collectio-status-live');

        disable($link);
        $live.removeClass('d-none').html('<i class="spinner-border spinner-border-sm mr-1"></i> Henter status…');

        $.ajax({
            url: AJAX_URL,
            type: 'GET',
            dataType: 'JSON',
            data: { req: 'get_status', hash: hash }
        }).done(function (resp) {
            $live.html(resp && resp.response ? resp.response : 'Ingen status returnert.');
        }).fail(function () {
            $live.html('<span class="text-danger">Kunne ikke hente status.</span>');
        }).always(function () {
            enable($link);
        });
    });

    $(document).on('click', '.collectio-recall-case', function (e) {
        e.preventDefault();
        const $link   = $(this);
        const hash    = $link.data('hash');
        const caseNo  = $link.data('case');

        $.fn.confirm(
            'Vil du tilbakekalle sak <b>' + caseNo + '</b> fra Collectio?<br><small>Saken stanses hos partner og du overtar selv videre oppfølging.</small>',
            function (ans) {
                if (!ans) return;
                show_loader();

                $.ajax({
                    url: AJAX_URL,
                    type: 'GET',
                    dataType: 'JSON',
                    data: { req: 'recall_case', hash: hash }
                }).done(function (resp) {
                    hide_loader();
                    if (resp && resp.status === 'success') {
                        $.fn.alert('Saken er tilbakekalt.', 'Vellykket', function () { window.location.reload(); });
                    } else {
                        $.fn.alert((resp && resp.message) ? resp.message : 'Kunne ikke tilbakekalle sak.', 'Feil');
                    }
                }).fail(function () {
                    hide_loader();
                    $.fn.alert('Kunne ikke kommunisere med server.', 'Feil');
                });
            }
        );
    });

    $(document).on('click', '.collectio-stop-case', function (e) {
        e.preventDefault();
        const hash = $(this).data('hash');

        $.fn.confirm('Vil du avbryte automatisk oppfølging for denne fakturaen?', function (ans) {
            if (!ans) return;
            show_loader();

            $.ajax({
                url: AJAX_URL,
                type: 'GET',
                dataType: 'JSON',
                data: { req: 'stop_case', hash: hash }
            }).done(function (resp) {
                hide_loader();
                if (resp && resp.status === 'ok') {
                    window.location.reload();
                } else {
                    $.fn.alert((resp && resp.message) ? resp.message : 'Kunne ikke stoppe oppfølging.', 'Feil');
                }
            }).fail(function () {
                hide_loader();
                $.fn.alert('Kunne ikke kommunisere med server.', 'Feil');
            });
        });
    });
});
</script>
