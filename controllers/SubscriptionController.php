<?php
class SubscriptionController {
    private $conn;
    private $table_name = "school_subscriptions";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        try {
            $query = "SELECT 
                        ss.id, ss.school_id, ss.plan_id, ss.amount as price, 
                        ss.status, ss.start_date, ss.end_date, 
                        ss.auto_renew, ss.created_at, ss.updated_at,
                        s.name as school_name,
                        sp.name as plan_name, sp.billing_cycle,
                        sp.features as plan_features, sp.limitations as plan_limitations
                      FROM " . $this->table_name . " ss
                      LEFT JOIN schools s ON ss.school_id = s.id
                      LEFT JOIN subscription_plans sp ON ss.plan_id = sp.id
                      WHERE 1=1";
            
            // Handle filters
            $params = [];
            $conditions = [];
            
            if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
                $conditions[] = "ss.status = :status";
                $params[':status'] = $_GET['status'];
            }
            
            if (!empty($_GET['school_id'])) {
                $conditions[] = "ss.school_id = :school_id";
                $params[':school_id'] = $_GET['school_id'];
            }
            
            if (!empty($_GET['plan_id'])) {
                $conditions[] = "ss.plan_id = :plan_id";
                $params[':plan_id'] = $_GET['plan_id'];
            }
            
            if (!empty($conditions)) {
                $query .= " AND " . implode(" AND ", $conditions);
            }
            
            $query .= " ORDER BY ss.created_at DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format the response to match Flutter model
            $formattedSubscriptions = array_map(function($sub) {
                return [
                    'id' => (int)$sub['id'],
                    'school_id' => (int)$sub['school_id'],
                    'school_name' => $sub['school_name'],
                    'plan_id' => (int)$sub['plan_id'],
                    'plan_name' => $sub['plan_name'],
                    'price' => (float)$sub['price'],
                    'billing_cycle' => $sub['billing_cycle'],
                    'status' => $sub['status'],
                    'start_date' => $sub['start_date'],
                    'renewal_date' => $sub['end_date'], // Assuming renewal_date = end_date
                    'end_date' => $sub['end_date'],
                    'features' => $sub['plan_features'] ? json_decode($sub['plan_features'], true) : null,
                    'created_at' => $sub['created_at'],
                    'updated_at' => $sub['updated_at']
                ];
            }, $subscriptions);
            
            http_response_code(200);
            echo json_encode($formattedSubscriptions);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Server error: " . $e->getMessage()]);
        }
    }

    public function getById($id) {
        try {
            $query = "SELECT 
                        ss.id, ss.school_id, ss.plan_id, ss.amount as price, 
                        ss.status, ss.start_date, ss.end_date, 
                        ss.auto_renew, ss.created_at, ss.updated_at,
                        s.name as school_name,
                        sp.name as plan_name, sp.billing_cycle,
                        sp.features as plan_features, sp.limitations as plan_limitations
                      FROM " . $this->table_name . " ss
                      LEFT JOIN schools s ON ss.school_id = s.id
                      LEFT JOIN subscription_plans sp ON ss.plan_id = sp.id
                      WHERE ss.id = :id";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Format to match Flutter model
                $formattedSubscription = [
                    'id' => (int)$subscription['id'],
                    'school_id' => (int)$subscription['school_id'],
                    'school_name' => $subscription['school_name'],
                    'plan_id' => (int)$subscription['plan_id'],
                    'plan_name' => $subscription['plan_name'],
                    'price' => (float)$subscription['price'],
                    'billing_cycle' => $subscription['billing_cycle'],
                    'status' => $subscription['status'],
                    'start_date' => $subscription['start_date'],
                    'renewal_date' => $subscription['end_date'],
                    'end_date' => $subscription['end_date'],
                    'features' => $subscription['plan_features'] ? json_decode($subscription['plan_features'], true) : null,
                    'created_at' => $subscription['created_at'],
                    'updated_at' => $subscription['updated_at']
                ];
                
                http_response_code(200);
                echo json_encode($formattedSubscription);
            } else {
                http_response_code(404);
                echo json_encode(["message" => "Subscription not found"]);
            }
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Server error: " . $e->getMessage()]);
        }
    }

    public function create() {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            
            $required = ['school_id', 'plan_id', 'start_date', 'end_date'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(["message" => "Missing required field: $field"]);
                    return;
                }
            }
            
            // Check if school exists
            $schoolQuery = "SELECT id, name FROM schools WHERE id = :school_id";
            $schoolStmt = $this->conn->prepare($schoolQuery);
            $schoolStmt->bindParam(':school_id', $data['school_id']);
            $schoolStmt->execute();
            
            if ($schoolStmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["message" => "School not found"]);
                return;
            }
            $school = $schoolStmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if plan exists and get price
            $planQuery = "SELECT id, name, price, billing_cycle FROM subscription_plans WHERE id = :plan_id";
            $planStmt = $this->conn->prepare($planQuery);
            $planStmt->bindParam(':plan_id', $data['plan_id']);
            $planStmt->execute();
            
            if ($planStmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["message" => "Plan not found"]);
                return;
            }
            $plan = $planStmt->fetch(PDO::FETCH_ASSOC);
            
            // Generate subscription code
            $subscriptionCode = 'SUB-' . date('Ymd') . '-' . strtoupper(uniqid());
            
            $query = "INSERT INTO " . $this->table_name . " 
                      (subscription_code, school_id, plan_id, amount, status, 
                       start_date, end_date, auto_renew, payment_method, transaction_id) 
                      VALUES (:subscription_code, :school_id, :plan_id, :amount, :status, 
                              :start_date, :end_date, :auto_renew, :payment_method, :transaction_id)";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':subscription_code', $subscriptionCode);
            $stmt->bindParam(':school_id', $data['school_id']);
            $stmt->bindParam(':plan_id', $data['plan_id']);
            $stmt->bindParam(':amount', $plan['price']);
            $stmt->bindParam(':status', $data['status'] ?? 'active');
            $stmt->bindParam(':start_date', $data['start_date']);
            $stmt->bindParam(':end_date', $data['end_date']);
            $stmt->bindParam(':auto_renew', $data['auto_renew'] ?? 1);
            $stmt->bindParam(':payment_method', $data['payment_method'] ?? null);
            $stmt->bindParam(':transaction_id', $data['transaction_id'] ?? null);
            
            if ($stmt->execute()) {
                $lastId = $this->conn->lastInsertId();
                
                // Return the created subscription with joined data
                $returnQuery = "SELECT 
                                ss.id, ss.school_id, ss.plan_id, ss.amount as price, 
                                ss.status, ss.start_date, ss.end_date, 
                                ss.auto_renew, ss.created_at, ss.updated_at,
                                s.name as school_name,
                                sp.name as plan_name, sp.billing_cycle,
                                sp.features as plan_features, sp.limitations as plan_limitations
                              FROM " . $this->table_name . " ss
                              LEFT JOIN schools s ON ss.school_id = s.id
                              LEFT JOIN subscription_plans sp ON ss.plan_id = sp.id
                              WHERE ss.id = :id";
                
                $returnStmt = $this->conn->prepare($returnQuery);
                $returnStmt->bindParam(':id', $lastId);
                $returnStmt->execute();
                
                $subscription = $returnStmt->fetch(PDO::FETCH_ASSOC);
                
                // Format to match Flutter model
                $formattedSubscription = [
                    'id' => (int)$subscription['id'],
                    'school_id' => (int)$subscription['school_id'],
                    'school_name' => $subscription['school_name'],
                    'plan_id' => (int)$subscription['plan_id'],
                    'plan_name' => $subscription['plan_name'],
                    'price' => (float)$subscription['price'],
                    'billing_cycle' => $subscription['billing_cycle'],
                    'status' => $subscription['status'],
                    'start_date' => $subscription['start_date'],
                    'renewal_date' => $subscription['end_date'],
                    'end_date' => $subscription['end_date'],
                    'features' => $subscription['plan_features'] ? json_decode($subscription['plan_features'], true) : null,
                    'created_at' => $subscription['created_at'],
                    'updated_at' => $subscription['updated_at']
                ];
                
                // Create invoice for this subscription
                $this->createInvoice($lastId, $formattedSubscription);
                
                http_response_code(201);
                echo json_encode($formattedSubscription);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to create subscription"]);
            }
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Server error: " . $e->getMessage()]);
        }
    }

    public function update($id) {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            
            $query = "UPDATE " . $this->table_name . " 
                      SET status = :status,
                          end_date = :end_date,
                          auto_renew = :auto_renew,
                          updated_at = NOW()
                      WHERE id = :id";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':status', $data['status']);
            $stmt->bindParam(':end_date', $data['end_date']);
            $stmt->bindParam(':auto_renew', $data['auto_renew'] ?? 1);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                // Return updated subscription
                $this->getById($id);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to update subscription"]);
            }
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Server error: " . $e->getMessage()]);
        }
    }

    public function delete($id) {
        try {
            // First check if subscription exists
            $checkQuery = "SELECT id FROM " . $this->table_name . " WHERE id = :id";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["message" => "Subscription not found"]);
                return;
            }
            
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(["message" => "Subscription deleted successfully"]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to delete subscription"]);
            }
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Server error: " . $e->getMessage()]);
        }
    }

    private function createInvoice($subscriptionId, $subscriptionData) {
        try {
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(uniqid());
            $issueDate = date('Y-m-d');
            $dueDate = date('Y-m-d', strtotime('+30 days'));
            
            // Prepare invoice items
            $items = [
                [
                    'description' => 'Subscription - ' . $subscriptionData['plan_name'],
                    'quantity' => 1,
                    'unit_price' => $subscriptionData['price'],
                    'total' => $subscriptionData['price']
                ]
            ];
            
            $query = "INSERT INTO invoices 
                      (invoice_number, subscription_id, school_id, amount, 
                       tax, total_amount, invoice_date, due_date, status, items) 
                      VALUES (:invoice_number, :subscription_id, :school_id, :amount, 
                              :tax, :total_amount, :invoice_date, :due_date, :status, :items)";
            
            $stmt = $this->conn->prepare($query);
            
            $tax = 0.00; // Assuming no tax for now
            $totalAmount = $subscriptionData['price'] + $tax;
            
            $stmt->bindParam(':invoice_number', $invoiceNumber);
            $stmt->bindParam(':subscription_id', $subscriptionId);
            $stmt->bindParam(':school_id', $subscriptionData['school_id']);
            $stmt->bindParam(':amount', $subscriptionData['price']);
            $stmt->bindParam(':tax', $tax);
            $stmt->bindParam(':total_amount', $totalAmount);
            $stmt->bindParam(':invoice_date', $issueDate);
            $stmt->bindParam(':due_date', $dueDate);
            $stmt->bindParam(':status', $status = 'pending');
            $stmt->bindParam(':items', json_encode($items));
            
            $stmt->execute();
            
            return $this->conn->lastInsertId();
        } catch(PDOException $e) {
            // Log error but don't fail subscription creation
            error_log("Failed to create invoice: " . $e->getMessage());
            return null;
        }
    }
}
?>