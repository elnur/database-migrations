<?php
/*
 * Copyright (c) 2012-2013 Elnur Abdurrakhimov
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
namespace Elnur\Database;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Statement;

class MigrationManager
{
    /**
     * @var Connection
     */
    private $db;

    /**
     * @var string
     */
    private $dir;

    /**
     * @param Connection $db
     * @param string     $dir
     */
    public function __construct(Connection $db, $dir)
    {
        $this->db  = $db;
        $this->dir = $dir;
    }

    public function migrate()
    {
        $this->initSchemaTable();

        $this->db->beginTransaction();

        foreach ($this->findMigrations($this->dir) as $migration) {
            if ($migration <= $this->getCurrentVersion()) {
                continue;
            }

            $path = $this->dir.DIRECTORY_SEPARATOR.$migration.'.sql';

            $this->db->exec(file_get_contents($path));
            $this->db->exec("UPDATE schema SET version = '{$migration}'");
        }

        $this->db->commit();
    }

    /**
     * @param string $dir
     * @return array
     */
    private function findMigrations($dir)
    {
        $migrations = array_filter(scandir($dir), function ($filename) {
            if (!preg_match('|^\d+\.sql$|', $filename)) {
                return false;
            }

            return true;
        });

        array_walk($migrations, function ($value, $key) use (&$migrations) {
            $migrations[$key] = pathinfo($value, PATHINFO_FILENAME);
        });

        sort($migrations);

        return $migrations;
    }

    private function initSchemaTable()
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS schema(version varchar PRIMARY KEY)');
    }

    /**
     * @return string
     */
    private function getCurrentVersion()
    {
        /** @var $result Statement */
        $result  = $this->db->query('SELECT version FROM schema');
        $version = $result->fetchColumn();

        if (!$version) {
            $version = '0';
            $this->db->exec("INSERT INTO schema VALUES ('{$version}')");
        }

        return $version;
    }
}
