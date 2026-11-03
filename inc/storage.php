<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Copenhagen');

function data_file(string $user): string
{
    return __DIR__ . '/data/' . hash('sha256', $user) . '.json';
}

/**
 * Åbner brugerens datafil med lås, kalder $fn(&$data) og gemmer ændringerne.
 * Datastruktur: ['running' => ?int (starttidspunkt), 'entries' => [...]]
 */
function with_user_data(string $user, callable $fn)
{
    $path = data_file($user);
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Mappen inc/data findes ikke og kunne ikke oprettes. Opret den manuelt via FTP.');
    }
    $fh = @fopen($path, 'c+');
    if (!$fh) {
        throw new RuntimeException('Kan ikke skrive til mappen inc/data. Sæt rettighederne på mappen til 755 eller 775.');
    }
    flock($fh, LOCK_EX);
    $raw  = stream_get_contents($fh);
    $data = $raw ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        $data = ['running' => null, 'running_note' => '', 'entries' => []];
    }

    $result = $fn($data);

    rewind($fh);
    ftruncate($fh, 0);
    fwrite($fh, json_encode($data, JSON_UNESCAPED_UNICODE));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    return $result;
}

/** Renser en note: fjerner styretegn, trimmer og begrænser til 200 tegn. */
function clean_note(string $s): string
{
    $s = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $s) ?? '';
    $s = trim($s);
    return preg_match('/^.{0,200}/su', $s, $m) ? $m[0] : '';
}

/** Gemmer et tidsrum. Går det over midnat, deles det op på de enkelte dage. */
function add_entry(array &$data, int $start, int $end, string $note = ''): void
{
    $cur = $start;
    while ($cur < $end) {
        $segEnd = min($end, strtotime('tomorrow', $cur));
        $data['entries'][] = [
            'id'      => bin2hex(random_bytes(6)),
            'date'    => date('Y-m-d', $cur),
            'start'   => $cur,
            'end'     => $segEnd,
            'seconds' => $segEnd - $cur,
            'note'    => $note,
        ];
        $cur = $segEnd;
    }
}

/** Det klienten får at vide: kørende timer samt de seneste 30 dage. */
function build_state(array $data): array
{
    $todayStart = strtotime('today');
    $from       = strtotime('-29 days', $todayStart);

    $days = [];
    foreach ($data['entries'] as $e) {
        if ($e['start'] < $from) {
            continue;
        }
        $d = $e['date'];
        $days[$d] ??= ['date' => $d, 'total' => 0, 'entries' => []];
        $days[$d]['total']    += $e['seconds'];
        $days[$d]['entries'][] = $e;
    }
    krsort($days);
    foreach ($days as &$day) {
        usort($day['entries'], fn($a, $b) => $a['start'] <=> $b['start']);
    }
    unset($day);

    return [
        'now'           => time(),
        'today_start'   => $todayStart,
        'running_start' => $data['running'],
        'running_note'  => (string) ($data['running_note'] ?? ''),
        'days'          => array_values($days),
    ];
}
