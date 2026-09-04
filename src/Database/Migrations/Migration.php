<?php

namespace Cubo\Database\Migrations;

/**
 * Molde de uma migration.
 * @package Cubo
 * @author Mateus - github.com/eeomts
 */
abstract class Migration
{
    abstract public function up(Schema $schema): void;

    /**
     * Desfaz o que o up() fez.
     */
    abstract public function down(Schema $schema): void;
}
