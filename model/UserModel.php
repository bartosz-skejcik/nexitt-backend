<?php
require_once __DIR__ . "/../model/Database.php";

class UserModel extends Database {
    public function getUser($userId) {
        return $this->select("SELECT * FROM users WHERE id = ?", ["i", $userId]);
    }
}
?>