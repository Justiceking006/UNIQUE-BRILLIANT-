<?php
// connect.php - Secure Database Connection
class Database {
    private $host = 'localhost';
    private $db_name = 'primeleg_Unique';
    private $username = 'primeleg_Unique';
    private $password = '1234501210@Uni';
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                )
            );
            
        } catch(PDOException $exception) {
            error_log("Database connection error: " . $exception->getMessage());
            return null;
        }
        
        return $this->conn;
    }
    
    public function generateStudentCode() {
        return 'UBS' . date('Y') . strtoupper(bin2hex(random_bytes(4)));
    }
    
    public function generateStudentPIN() {
        return sprintf("%06d", random_int(0, 999999));
    }
}

// Create and export database instance
$GLOBALS['database'] = new Database();

function getDBConnection() {
    return $GLOBALS['database']->getConnection();
}

function generateStudentCode() {
    return $GLOBALS['database']->generateStudentCode();
}

function generateStudentPIN() {
    return $GLOBALS['database']->generateStudentPIN();
}

function sanitize($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
?>