class PlanController {
    private $conn;
    private $table_name = "subscription_plans";

    public function __construct($db) {
        $this->conn = $db;
    }

    // GET all plans
    public function getAll() {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE is_active = 1 ORDER BY price ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decode JSON fields
            foreach ($plans as &$plan) {
                if (isset($plan['features']) && !empty($plan['features'])) {
                    $plan['features'] = json_decode($plan['features'], true);
                }
                if (isset($plan['limitations']) && !empty($plan['limitations'])) {
                    $plan['limitations'] = json_decode($plan['limitations'], true);
                }
            }
            
            http_response_code(200);
            echo json_encode($plans);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Server error: " . $e->getMessage()]);
        }
    }

    // GET single plan by ID
    public function getById($id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id AND is_active = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$plan) {
                http_response_code(404);
                echo json_encode(["message" => "Plan not found"]);
                return;
            }
            
            // Decode JSON fields
            if (isset($plan['features']) && !empty($plan['features'])) {
                $plan['features'] = json_decode($plan['features'], true);
            }
            if (isset($plan['limitations']) && !empty($plan['limitations'])) {
                $plan['limitations'] = json_decode($plan['limitations'], true);
            }
            
            http_response_code(200);
            echo json_encode($plan);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Server error: " . $e->getMessage()]);
        }
    }

    // CREATE new plan
    public function create() {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            
            $required = ['plan_code', 'name', 'description', 'price'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(["message" => "Missing required field: $field"]);
                    return;
                }
            }
            
            $query = "INSERT INTO " . $this->table_name . " 
                      (plan_code, name, description, price, billing_cycle, 
                       max_students, max_buses, features, limitations, is_active) 
                      VALUES (:plan_code, :name, :description, :price, :billing_cycle, 
                              :max_students, :max_buses, :features, :limitations, :is_active)";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':plan_code', $data['plan_code']);
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':description', $data['description']);
            $stmt->bindParam(':price', $data['price']);
            $stmt->bindParam(':billing_cycle', $data['billing_cycle'] ?? 'monthly');
            $stmt->bindParam(':max_students', $data['max_students'] ?? null);
            $stmt->bindParam(':max_buses', $data['max_buses'] ?? null);
            
            $features = !empty($data['features']) ? json_encode($data['features']) : null;
            $limitations = !empty($data['limitations']) ? json_encode($data['limitations']) : null;
            
            $stmt->bindParam(':features', $features);
            $stmt->bindParam(':limitations', $limitations);
            $is_active = $data['is_active'] ?? true;
            $stmt->bindParam(':is_active', $is_active);
            
            if ($stmt->execute()) {
                $lastId = $this->conn->lastInsertId();
                
                // Return the created plan
                $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':id', $lastId);
                $stmt->execute();
                
                $plan = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Decode JSON fields
                if (isset($plan['features']) && !empty($plan['features'])) {
                    $plan['features'] = json_decode($plan['features'], true);
                }
                if (isset($plan['limitations']) && !empty($plan['limitations'])) {
                    $plan['limitations'] = json_decode($plan['limitations'], true);
                }
                
                http_response_code(201);
                echo json_encode($plan);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to create plan"]);
            }
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Server error: " . $e->getMessage()]);
        }
    }

    // UPDATE plan
    public function update($id) {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            
            $query = "UPDATE " . $this->table_name . " SET 
                      plan_code = :plan_code,
                      name = :name,
                      description = :description,
                      price = :price,
                      billing_cycle = :billing_cycle,
                      max_students = :max_students,
                      max_buses = :max_buses,
                      features = :features,
                      limitations = :limitations,
                      is_active = :is_active
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':plan_code', $data['plan_code']);
            $stmt->bindParam(':name', $data['name']);
            $stmt->bindParam(':description', $data['description']);
            $stmt->bindParam(':price', $data['price']);
            $stmt->bindParam(':billing_cycle', $data['billing_cycle'] ?? 'monthly');
            $stmt->bindParam(':max_students', $data['max_students'] ?? null);
            $stmt->bindParam(':max_buses', $data['max_buses'] ?? null);
            
            $features = !empty($data['features']) ? json_encode($data['features']) : null;
            $limitations = !empty($data['limitations']) ? json_encode($data['limitations']) : null;
            
            $stmt->bindParam(':features', $features);
            $stmt->bindParam(':limitations', $limitations);
            $is_active = $data['is_active'] ?? true;
            $stmt->bindParam(':is_active', $is_active);
            
            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(["message" => "Plan updated successfully"]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to update plan"]);
            }
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Server error: " . $e->getMessage()]);
        }
    }

    // DELETE plan (soft delete)
    public function delete($id) {
        try {
            $query = "UPDATE " . $this->table_name . " SET is_active = 0 WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(["message" => "Plan deleted successfully"]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to delete plan"]);
            }
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Server error: " . $e->getMessage()]);
        }
    }
}