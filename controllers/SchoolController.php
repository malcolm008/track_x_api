<?php
class SchoolController {
    private $conn;
    private $table = "schools";

    public function __construct($db) {
        $this->conn = $db;
    }

    // GET /schools
    public function getAll() {
        try {
            $query = "SELECT * FROM {$this->table} ORDER BY created_at DESC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            $schools = $stmt->fetchAll(PDO::FETCH_ASSOC);

            http_response_code(200);
            echo json_encode($schools);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                "message" => "Failed to fetch schools",
                "error" => $e->getMessage()
            ]);
        }
    }

    // GET /schools/{id}
    public function getById($id) {
        try {
            $query = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $school = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$school) {
                http_response_code(404);
                echo json_encode(["message" => "School not found"]);
                return;
            }

            http_response_code(200);
            echo json_encode($school);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                "message" => "Failed to fetch school",
                "error" => $e->getMessage()
            ]);
        }
    }

    // POST /schools
    public function create() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (
            empty($data['school_code']) ||
            empty($data['name']) ||
            empty($data['email'])
        ) {
            http_response_code(400);
            echo json_encode(["message" => "Missing required fields"]);
            return;
        }

        try {
            $query = "INSERT INTO schools
                (school_code, name, email, phone, address, city, country,
                 contact_person, total_students, total_buses, status)
                VALUES
                (:school_code, :name, :email, :phone, :address, :city, :country,
                 :contact_person, :total_students, :total_buses, :status)";

            $stmt = $this->conn->prepare($query);

            $stmt->execute([
                ':school_code' => $data['school_code'],
                ':name' => $data['name'],
                ':email' => $data['email'],
                ':phone' => $data['phone'] ?? null,
                ':address' => $data['address'] ?? null,
                ':city' => $data['city'] ?? null,
                ':country' => $data['country'] ?? null,
                ':contact_person' => $data['contact_person'] ?? null,
                ':total_students' => $data['total_students'] ?? 0,
                ':total_buses' => $data['total_buses'] ?? 0,
                ':status' => $data['status'] ?? 'active'
            ]);

            http_response_code(201);
            echo json_encode([
                "message" => "School created successfully",
                "id" => $this->conn->lastInsertId()
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                "message" => "Failed to create school",
                "error" => $e->getMessage()
            ]);
        }
    }
}
