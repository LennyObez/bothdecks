<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Ingestion;

use Pulsar\Database\ConnectionInterface;

/**
 * Multi-row inserts, in batches, through the connection's named placeholders.
 *
 * An import writes about two million rows. One statement per row would take an hour; one statement per
 * thousand rows takes a minute and stays well under the engine's limit on bound parameters.
 */
final class BulkWriter
{
    private const int ROWS_PER_STATEMENT = 1000;

    /** @var list<list<scalar|null>> */
    private array $rows = [];

    private int $written = 0;

    /**
     * @param list<string> $columns
     */
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table,
        private readonly array $columns,
        private readonly string $suffix = '',
    ) {}

    /**
     * @param list<scalar|null> $row In column order.
     */
    public function add(array $row): void
    {
        if (\count($row) !== \count($this->columns)) {
            throw new \LogicException(\sprintf('%s takes %d values per row; got %d.', $this->table, \count($this->columns), \count($row)));
        }

        $this->rows[] = $row;

        if (\count($this->rows) >= self::ROWS_PER_STATEMENT) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->rows === []) {
            return;
        }

        $placeholders = [];
        $bindings = [];

        foreach ($this->rows as $r => $row) {
            $names = [];

            foreach ($row as $c => $value) {
                $name = 'v' . $r . '_' . $c;
                $names[] = ':' . $name;
                $bindings[$name] = $value;
            }

            $placeholders[] = '(' . implode(', ', $names) . ')';
        }

        $sql = \sprintf(
            'INSERT INTO %s (%s) VALUES %s %s',
            $this->table,
            implode(', ', $this->columns),
            implode(', ', $placeholders),
            $this->suffix,
        );

        $this->written += $this->connection->execute($sql, $bindings);
        $this->rows = [];
    }

    public function written(): int
    {
        return $this->written;
    }
}
