<?php


namespace Mutoco\Mplus\Import;

use Mutoco\Mplus\Import\Step\StepInterface;
use Mutoco\Mplus\Parse\Result\TreeNode;
use Mutoco\Mplus\Serialize\SerializableTrait;

if (class_exists('SQLite3')) {
    class SqliteImportRegistry implements RegistryInterface
    {
        use SerializableTrait;

        protected ?\SQLite3 $db = null;
        protected ?string $filename = null;
        protected \SQLite3Stmt $treeInsert;
        protected \SQLite3Stmt $treeSelect;
        protected \SQLite3Stmt $treeDelete;

        protected \SQLite3Stmt $relationInsert;
        protected \SQLite3Stmt $relationSelect;

        protected \SQLite3Stmt $moduleInsert;
        protected \SQLite3Stmt $moduleSelect;
        protected \SQLite3Stmt $moduleSelectAll;

        protected \SQLite3Stmt $queueInsert;
        protected \SQLite3Stmt $queueDelete;
        protected bool $isQueueDirty = true;
        protected int $queueCount = 0;

        public function addStep(StepInterface $step, int $priority): void
        {
            $this->isQueueDirty = true;
            $this->getDb();
            $this->queueInsert->reset();
            $this->queueInsert->bindValue(':priority', $priority, SQLITE3_INTEGER);
            $this->queueInsert->bindValue(':value', serialize($step), SQLITE3_BLOB);
            if ($result = $this->queueInsert->execute()) {
                $result->finalize();
                return;
            }

            throw new \Exception('Unable to insert tree into SQlite DB');
        }

        public function getNextStep(?int &$priority): ?StepInterface
        {
            $db = $this->getDb();
            $row = $db->querySingle('SELECT * FROM queue ORDER BY priority DESC, id ASC LIMIT 1',true);
            if ($row && isset($row['id'])) {
                $priority = $row['priority'] ?? 0;
                $this->deleteFromQueue($row['id']);
                return unserialize($row['value']);
            }
            return null;
        }

        public function getRemainingSteps(): int
        {
            if ($this->isQueueDirty) {
                $this->queueCount = $this->getDb()->querySingle('SELECT COUNT(*) FROM queue');
                $this->isQueueDirty = false;
            }

            return $this->queueCount;
        }

        public function hasImportedTree(string $module, string $id): bool
        {
            return $this->getImportedTree($module, $id) !== null;
        }

        public function getImportedTree(string $module, string $id): ?TreeNode
        {
            $this->getDb();
            $this->treeSelect->reset();
            $this->treeSelect->bindValue(':module', $module, SQLITE3_TEXT);
            $this->treeSelect->bindValue(':id', $id, SQLITE3_TEXT);
            if (($result = $this->treeSelect->execute()) && $result->numColumns() > 0) {
                $data = $result->fetchArray(SQLITE3_ASSOC);
                return $data && isset($data['value']) ? unserialize($data['value']) : null;
            }
            return null;
        }

        public function setImportedTree(string $module, string $id, TreeNode $tree): void
        {
            $this->getDb();
            $this->treeInsert->reset();
            $this->treeInsert->bindValue(':module', $module, SQLITE3_TEXT);
            $this->treeInsert->bindValue(':id', $id, SQLITE3_TEXT);
            $this->treeInsert->bindValue(':value', serialize($tree), SQLITE3_BLOB);
            if ($result = $this->treeInsert->execute()) {
                $result->finalize();
                return;
            }

            throw new \Exception('Unable to insert tree into SQlite DB');
        }

        public function clearImportedTree(string $module, string $id): bool
        {
            $db = $this->getDb();
            $this->treeDelete->reset();
            $this->treeDelete->bindValue(':module', $module, SQLITE3_TEXT);
            $this->treeDelete->bindValue(':id', $id, SQLITE3_TEXT);
            if ($result = $this->treeDelete->execute()) {
                $result->finalize();
                return $db->changes() > 0;
            }
            return false;
        }

        public function reportImportedRelation(string $class, string $id, string $name, array $ids): void
        {
            $this->getDb();
            $this->relationInsert->reset();
            $this->relationInsert->bindValue(':class', $class, SQLITE3_TEXT);
            $this->relationInsert->bindValue(':id', $id, SQLITE3_TEXT);
            $this->relationInsert->bindValue(':relation', $name, SQLITE3_TEXT);
            $this->relationInsert->bindValue(':value', serialize($ids), SQLITE3_BLOB);
            if ($result = $this->relationInsert->execute()) {
                $result->finalize();
                return;
            }

            throw new \Exception('Unable to insert tree into SQlite DB');
        }

        public function hasImportedRelation(string $class, string $id, string $name): bool
        {
            return !empty($this->getRelationIds($class, $id, $name));
        }

        public function getRelationIds(string $class, string $id, string $name): array
        {
            $this->getDb();
            $this->relationSelect->reset();
            $this->relationSelect->bindValue(':class', $class, SQLITE3_TEXT);
            $this->relationSelect->bindValue(':id', $id, SQLITE3_TEXT);
            $this->relationSelect->bindValue(':relation', $name, SQLITE3_TEXT);
            if (($result = $this->relationSelect->execute()) && $result->numColumns() > 0) {
                $data = $result->fetchArray(SQLITE3_ASSOC);
                return $data && isset($data['value']) ? unserialize($data['value']) : [];
            }
            return [];
        }

        public function reportImportedModule(string $name, string $id): void
        {
            $this->getDb();
            $this->moduleInsert->reset();
            $this->moduleInsert->bindValue(':module', $name, SQLITE3_TEXT);
            $this->moduleInsert->bindValue(':id', $id, SQLITE3_TEXT);
            if ($result = $this->moduleInsert->execute()) {
                $result->finalize();
                return;
            }

            throw new \Exception('Unable to insert module into SQlite DB');
        }

        public function hasImportedModule(string $name, ?string $id = null): bool
        {
            $this->getDb();

            if ($id === null) {
                return !empty($this->getImportedIds($name));
            }

            $this->moduleSelect->reset();
            $this->moduleSelect->bindValue(':module', $name, SQLITE3_TEXT);
            $this->moduleSelect->bindValue(':id', $id, SQLITE3_TEXT);

            if (($result = $this->moduleSelect->execute()) && $result->numColumns() > 0) {
                $data = $result->fetchArray(SQLITE3_ASSOC);
                return $data && isset($data['id']);
            }
            return false;
        }

        public function getImportedIds(string $module): array
        {
            $this->getDb();
            $this->moduleSelectAll->reset();
            $this->moduleSelectAll->bindValue(':module', $module, SQLITE3_TEXT);
            if (($result = $this->moduleSelectAll->execute()) && $result->numColumns() > 0) {
                $set = [];
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $set[] = $row['id'];
                }
                return $set;
            }
            return [];
        }

        public function clear(): void
        {
            $db = $this->getDb();
            $db->exec('DELETE FROM trees');
            $db->exec('DELETE FROM relations');
            $db->exec('DELETE FROM modules');
            $db->close();
            $this->db = null;
            $this->isQueueDirty = true;
            $this->queueCount = 0;
            unlink($this->filename);
            $this->filename = null;
        }

        protected static string $create_tree = <<<SQL
CREATE TABLE IF NOT EXISTS 'trees' (
    id TEXT NOT NULL,
    module TEXT NOT NULL,
    value BLOB NOT NULL,
    PRIMARY KEY (id, module)
)
SQL;
        protected static string $create_relations = <<<SQL
CREATE TABLE IF NOT EXISTS 'relations' (
    id TEXT NOT NULL,
    class TEXT NOT NULL,
    relation TEXT NOT NULL,
    value BLOB NOT NULL,
    PRIMARY KEY (id, class, relation)
)
SQL;

        protected static string $create_modules = <<<SQL
CREATE TABLE IF NOT EXISTS 'modules' (
    id TEXT NOT NULL,
    module TEXT NOT NULL,
    PRIMARY KEY (id, module)
)
SQL;
        protected static string $create_queue = <<<SQL
CREATE TABLE IF NOT EXISTS 'queue' (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    priority INTEGER NOT NULL,
    value BLOB NOT NULL
)
SQL;

        protected function getDb(): \SQLite3
        {
            if ($this->db) {
                return $this->db;
            }

            $this->filename = tempnam(sys_get_temp_dir(), 'sq3import');
            $this->db = new \SQLite3($this->filename);
            $this->prepareStatements();

            return $this->db;
        }

        protected function prepareStatements(): void
        {
            $db = $this->getDb();
            $this->db->exec(self::$create_modules);
            $this->db->exec(self::$create_tree);
            $this->db->exec(self::$create_relations);
            $this->db->exec(self::$create_queue);

            $this->treeDelete = $db->prepare('DELETE FROM trees WHERE id=:id AND module=:module');
            $this->treeInsert = $db->prepare('REPLACE INTO trees (id, module, value) VALUES (:id, :module, :value)');
            $this->treeSelect = $db->prepare('SELECT value FROM trees WHERE id=:id AND module=:module');

            $this->relationInsert = $db->prepare('REPLACE INTO relations (id, class, relation, value) VALUES (:id, :class, :relation, :value)');
            $this->relationSelect = $db->prepare('SELECT value FROM relations WHERE id=:id AND class=:class AND relation=:relation');

            $this->moduleInsert = $db->prepare('REPLACE INTO modules (id, module) VALUES (:id, :module)');
            $this->moduleSelect = $db->prepare('SELECT id FROM modules WHERE module=:module AND id=:id');
            $this->moduleSelectAll = $db->prepare('SELECT id FROM modules WHERE module=:module');

            $this->queueInsert = $db->prepare('INSERT INTO queue (priority, value) VALUES (:priority, :value)');
            $this->queueDelete = $db->prepare('DELETE FROM queue WHERE id=:id');
        }

        protected function deleteFromQueue(int $id): bool
        {
            $this->isQueueDirty = true;
            $db = $this->getDb();
            $this->queueDelete->reset();
            $this->queueDelete->bindValue(':id', $id, SQLITE3_INTEGER);
            if ($result = $this->queueDelete->execute()) {
                $result->finalize();
                return $db->changes() > 0;
            }
            return false;
        }

        protected function getSerializableObject(): \stdClass
        {
            $obj = new \stdClass();
            $obj->filename = $this->filename;
            return $obj;
        }

        protected function unserializeFromObject(\stdClass $obj): void
        {
            $this->filename = $obj->filename;
            $this->db = new \SQLite3($this->filename);
            $this->prepareStatements();
        }
    }
}
