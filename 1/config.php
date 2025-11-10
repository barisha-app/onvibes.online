<?php
// config.php
class Database {
    private $host = 'localhost';
    private $db_name = 'onvibes_online_barisha';
    private $username = 'onvib_barisha';
    private $password = '!Cpc8zP2?pSvaev1';
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Bağlantı hatası: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>
