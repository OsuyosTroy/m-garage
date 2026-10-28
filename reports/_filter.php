<?php
/** Returns [from, to] from the query string; defaults to the current month. */
function report_range(): array {
    $from = input('from'); $to = input('to');
    if (!valid_date($from)) $from = date('Y-m-01');
    if (!valid_date($to)) $to = date('Y-m-d');
    if ($from > $to) [$from, $to] = [$to, $from];
    return [$from, $to];
}
function report_filter_form(string $from, string $to, string $extra = ''): void { ?>
<form class="toolbar no-print" method="get"><label>From <input type="date" name="from" value="<?= e($from) ?>"></label><label>To <input type="date" name="to" value="<?= e($to) ?>"></label><?= $extra ?>
<button class="btn btn-outline" type="submit">Apply</button><button class="btn btn-primary" type="button" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button></form>
<p class="muted">Period: <?= e($from) ?> to <?= e($to) ?> &middot; Generated <?= date('Y-m-d H:i') ?></p>
<?php }
