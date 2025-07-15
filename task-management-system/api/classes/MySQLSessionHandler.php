<?php
// MySQLSessionHandler.php
// Custom session handler for PHP using MySQL
class MySQLSessionHandler implements SessionHandlerInterface {
    private $pdo;
    private $table;
    private $ttl;

    public function __construct(PDO $pdo, $table = 'sessions', $ttl = 3600) {
        $this->pdo = $pdo;
        $this->table = $table;
        $this->ttl = $ttl;
        $this->createTableIfNotExists();
    }

    public function open($savePath, $sessionName) {
        return true;
    }

    public function close() {
        return true;
    }

    public function read($id) {
        $stmt = $this->pdo->prepare("SELECT data FROM {$this->table} WHERE id = :id AND expires > :now");
        $stmt->execute([
            ':id' => $id,
            ':now' => time()
        ]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return $row['data'];
        }
        return '';
    }

    public function write($id, $data) {
        $expires = time() + $this->ttl;
        $stmt = $this->pdo->prepare("REPLACE INTO {$this->table} (id, data, expires) VALUES (:id, :data, :expires)");
        return $stmt->execute([
            ':id' => $id,
            ':data' => $data,
            ':expires' => $expires
        ]);
    }

    public function destroy($id) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function gc($maxlifetime) {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE expires < :now");
        return $stmt->execute([':now' => time()]);
    }

    private function createTableIfNotExists() {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (
            `id` VARCHAR(128) NOT NULL PRIMARY KEY,
            `data` BLOB NOT NULL,
            `expires` INT(11) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $this->pdo->exec($sql);
    }
} 