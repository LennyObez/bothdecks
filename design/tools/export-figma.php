<?php

declare(strict_types=1);

/**
 * Write what the design file must contain.
 *
 *   php design/tools/export-figma.php
 *
 * The file it writes is committed, and `check-tokens.php` refuses a copy that the source would not produce.
 * That makes the repository the place where the two halves of one system are compared: the design file is
 * held against this, name by name and mode by mode, and the answer names what disagrees.
 */

require __DIR__ . '/bootstrap.php';

use BothDecks\Design\FigmaExport;
use BothDecks\Design\Manifest;
use BothDecks\Design\TokenSource;

define('BD_FIGMA_EXPORT', getenv('BD_FIGMA_EXPORT') ?: __DIR__ . '/../tokens/figma-expected.json');

$source = TokenSource::load(BD_TOKEN_SOURCE, new Manifest());
$json = FigmaExport::build($source);
$counts = FigmaExport::counts($json);
$total = array_sum($counts);

$existing = is_file(BD_FIGMA_EXPORT) ? file_get_contents(BD_FIGMA_EXPORT) : null;
$status = $existing === $json ? 'unchanged' : 'written';

if ($status === 'written') {
    file_put_contents(BD_FIGMA_EXPORT, $json);
}

fwrite(STDOUT, sprintf("%-10s figma-expected.json\n", $status));

foreach ($counts as $name => $count) {
    fwrite(STDOUT, sprintf("           %-16s %d variables\n", $name, $count));
}

fwrite(STDOUT, sprintf("           %d in all\n", $total));
