<?php

class SchoolController {
    private $conn;
    private $table = "schools";

    public function __construct($db) {
        $this->conn = $db;
    }

    // GET /schools
    public function getAll() {
        $stmt = $this->conn->prepare(
            "SELECT * FROM {$this->table} ORDER BY created_at DESC"
        );
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // GET /schools/{id}
    public function getById($id) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM {$this->table} WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $school = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$school) {
            http_response_code(404);
            echo json_encode(["message" => "School not found"]);
            return;
        }

        echo json_encode($school);
    }

    // POST /schools
    public function create() {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['school_code']) || empty($data['name']) || empty($data['email'])) {
            http_response_code(400);
            echo json_encode(["message" => "Missing required fields"]);
            return;
        }

        $stmt = $this->conn->prepare("
            INSERT INTO schools (
                school_code, name, email, phone, address, city, country,
                contact_person, total_students, total_buses, status,
                created_at, updated_at
            ) VALUES (
                :school_code, :name, :email, :phone, :address, :city, :country,
                :contact_person, :total_students, :total_buses, :status,
                NOW(), NOW()
            )
        ");

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

        $id = $this->conn->lastInsertId();

        $stmt = $this->conn->prepare("SELECT * FROM schools WHERE id = ?");
        $stmt->execute([$id]);

        http_response_code(201);
        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    }

    // PUT /schools/{id}
    public function update($id) {
        $data = json_decode(file_get_contents("php://input"), true);

        $stmt = $this->conn->prepare("
            UPDATE schools SET
                name = :name,
                email = :email,
                phone = :phone,
                address = :address,
                city = :city,
                country = :country,
                contact_person = :contact_person,
                total_students = :total_students,
                total_buses = :total_buses,
                status = :status,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            ':name' => $data['name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'] ?? null,
            ':address' => $data['address'] ?? null,
            ':city' => $data['city'] ?? null,
            ':country' => $data['country'] ?? null,
            ':contact_person' => $data['contact_person'] ?? null,
            ':total_students' => $data['total_students'] ?? 0,
            ':total_buses' => $data['total_buses'] ?? 0,
            ':status' => $data['status'] ?? 'active',
            ':id' => $id
        ]);

        $stmt = $this->conn->prepare("SELECT * FROM schools WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    }

    // DELETE /schools/{id}
    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM schools WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(["message" => "School not found"]);
            return;
        }

        echo json_encode(["success" => true]);
    }
}
