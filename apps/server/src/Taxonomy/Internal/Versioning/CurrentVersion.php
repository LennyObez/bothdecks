<?php

declare(strict_types=1);

namespace BothDecks\Taxonomy\Internal\Versioning;

use Pulsar\Database\ConnectionInterface;

/**
 * The taxonomy version every read goes through.
 *
 * At most one version is current, by a partial unique index; this reads it once per process, which under a
 * process manager is once per request, and refuses to answer when there is none, because a resolver, a
 * bubble or a report running against no version would have to invent one. A long-lived worker sees a new
 * import after `refresh()` or a restart.
 */
final class CurrentVersion
{
    private ?int $id = null;

    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {}

    /**
     * @throws NoCurrentVersionException
     */
    public function id(): int
    {
        if ($this->id !== null) {
            return $this->id;
        }

        $row = $this->connection->query('SELECT id FROM taxonomy_version WHERE is_current')->first();

        if ($row === null) {
            throw new NoCurrentVersionException('No taxonomy version is current. Take a snapshot and import it: taxonomy:snapshot, then taxonomy:import.');
        }

        return $this->id = $row->getInt('id');
    }

    /**
     * Forget the cached identifier, for a process that imports and then reads.
     */
    public function refresh(): void
    {
        $this->id = null;
    }
}
