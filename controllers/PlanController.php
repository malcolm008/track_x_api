<?php

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
            header('Content-Type: application/json');
            echo json_encode($plans);
        } catch(PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
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
                header('Content-Type: application/json');
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
            header('Content-Type: application/json');
            echo json_encode($plan);
        } catch(PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(["message" => "Server error: " . $e->getMessage()]);
        }
    }

    // CREATE new plan
    public function create() {
        try {
            // Get raw input
            $raw_input = file_get_contents("php://input");
            if (empty($raw_input)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(["message" => "No data provided"]);
                return;
            }
            
            $data = json_decode($raw_input, true);
            
            // Check if JSON decode failed
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(["message" => "Invalid JSON: " . json_last_error_msg()]);
                return;
            }
            
            // Validate required fields
            $required = ['plan_code', 'name', 'description', 'price'];
            $missing = [];
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    $missing[] = $field;
                }
            }
            
            if (!empty($missing)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(["message" => "Missing required fields: " . implode(', ', $missing)]);
                return;
            }
            
            // Prepare the query
            $query = "INSERT INTO " . $this->table_name . " 
                      (plan_code, name, description, price, billing_cycle, 
                       max_students, max_buses, features, limitations, is_active) 
                      VALUES (:plan_code, :name, :description, :price, :billing_cycle, 
                              :max_students, :max_buses, :features, :limitations, :is_active)";
            
            $stmt = $this->conn->prepare($query);
            
            // Bind parameters - FIXED: Use bindValue instead of bindParam for literals
            $plan_code = $data['plan_code'];
            $name = $data['name'];
            $description = $data['description'];
            $price = floatval($data['price']);
            $billing_cycle = $data['billing_cycle'] ?? 'monthly';
            $max_students = isset($data['max_students']) ? intval($data['max_students']) : null;
            $max_buses = isset($data['max_buses']) ? intval($data['max_buses']) : null;
            
            // Prepare JSON fields
            $features = null;
            if (!empty($data['features'])) {
                $features = is_string($data['features']) ? $data['features'] : json_encode($data['features']);
            }
            
            $limitations = null;
            if (!empty($data['limitations'])) {
                $limitations = is_string($data['limitations']) ? $data['limitations'] : json_encode($data['limitations']);
            }
            
            $is_active = isset($data['is_active']) ? (bool)$data['is_active'] : true;
            
            // Use bindValue instead of bindParam for compatibility
            $stmt->bindValue(':plan_code', $plan_code, PDO::PARAM_STR);
            $stmt->bindValue(':name', $name, PDO::PARAM_STR);
            $stmt->bindValue(':description', $description, PDO::PARAM_STR);
            $stmt->bindValue(':price', $price);
            $stmt->bindValue(':billing_cycle', $billing_cycle, PDO::PARAM_STR);
            $stmt->bindValue(':max_students', $max_students, $max_students !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':max_buses', $max_buses, $max_buses !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':features', $features, $features !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':limitations', $limitations, $limitations !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':is_active', $is_active, PDO::PARAM_BOOL);
            
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
                header('Content-Type: application/json');
                echo json_encode($plan);
            } else {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(["message" => "Failed to create plan", "error" => $stmt->errorInfo()]);
            }
        } catch(PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(["message" => "Server error: " . $e->getMessage()]);
        } catch(Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(["message" => "Error: " . $e->getMessage()]);
        }
    }

    // UPDATE plan
    public function update($id) {
        try {
            // Get raw input
            $raw_input = file_get_contents("php://input");
            if (empty($raw_input)) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(["message" => "No data provided"]);
                return;
            }
            
            $data = json_decode($raw_input, true);
            
            // Check if JSON decode failed
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(["message" => "Invalid JSON: " . json_last_error_msg()]);
                return;
            }
            
            // Check if plan exists
            $check_query = "SELECT id FROM " . $this->table_name . " WHERE id = :id";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bindParam(':id', $id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() === 0) {
                http_response_code(404);
                header('Content-Type: application/json');
                echo json_encode(["message" => "Plan not found"]);
                return;
            }
            
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
                      is_active = :is_active,
                      updated_at = CURRENT_TIMESTAMP
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            
            // Bind parameters - FIXED: Use bindValue
            $plan_code = $data['plan_code'] ?? '';
            $name = $data['name'] ?? '';
            $description = $data['description'] ?? '';
            $price = isset($data['price']) ? floatval($data['price']) : 0.0;
            $billing_cycle = $data['billing_cycle'] ?? 'monthly';
            $max_students = isset($data['max_students']) ? intval($data['max_students']) : null;
            $max_buses = isset($data['max_buses']) ? intval($data['max_buses']) : null;
            
            // Prepare JSON fields
            $features = null;
            if (!empty($data['features'])) {
                $features = is_string($data['features']) ? $data['features'] : json_encode($data['features']);
            }
            
            $limitations = null;
            if (!empty($data['limitations'])) {
                $limitations = is_string($data['limitations']) ? $data['limitations'] : json_encode($data['limitations']);
            }
            
            $is_active = isset($data['is_active']) ? (bool)$data['is_active'] : true;
            
            // Use bindValue for all parameters
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':plan_code', $plan_code, PDO::PARAM_STR);
            $stmt->bindValue(':name', $name, PDO::PARAM_STR);
            $stmt->bindValue(':description', $description, PDO::PARAM_STR);
            $stmt->bindValue(':price', $price);
            $stmt->bindValue(':billing_cycle', $billing_cycle, PDO::PARAM_STR);
            $stmt->bindValue(':max_students', $max_students, $max_students !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':max_buses', $max_buses, $max_buses !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':features', $features, $features !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':limitations', $limitations, $limitations !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':is_active', $is_active, PDO::PARAM_BOOL);
            
            if ($stmt->execute()) {
                // Return the updated plan
                $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                
                $plan = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Decode JSON fields
                if (isset($plan['features']) && !empty($plan['features'])) {
                    $plan['features'] = json_decode($plan['features'], true);
                }
                if (isset($plan['limitations']) && !empty($plan['limitations'])) {
                    $plan['limitations'] = json_decode($plan['limitations'], true);
                }
                
                http_response_code(200);
                header('Content-Type: application/json');
                echo json_encode($plan);
            } else {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(["message" => "Failed to update plan", "error" => $stmt->errorInfo()]);
            }
        } catch(PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(["message" => "Server error: " . $e->getMessage()]);
        } catch(Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(["message" => "Error: " . $e->getMessage()]);
        }
    }

    // DELETE plan (soft delete)
    public function delete($id) {
        try {
            $query = "UPDATE " . $this->table_name . " SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                http_response_code(200);
                header('Content-Type: application/json');
                echo json_encode(["message" => "Plan deleted successfully"]);
            } else {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(["message" => "Failed to delete plan", "error" => $stmt->errorInfo()]);
            }
        } catch(PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(["message" => "Server error: " . $e->getMessage()]);
        }
    }
}