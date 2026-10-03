<?php

declare(strict_types=1);

namespace WpAlchemist\Framework\Database;

/**
 * A migration file returns an instance of this class:
 *
 *     return new class extends Migration {
 *         public function up(Schema $schema): void { ... }
 *         public function down(Schema $schema): void { ... }
 *     };
 */
abstract class Migration
{
    abstract public function up(Schema $schema): void;

    /** Reverse up(). Runs on uninstall. */
    public function down(Schema $schema): void
    {
    }
}
