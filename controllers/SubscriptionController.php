<?php
class SubscriptionController {
    private $conn;
    private $table_name = "school_subscriptions";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        try {
            $query = "SELECT ss.*, 
                      s.name as school_name, s.email as school_email, s.phone as school_phone,
                      sp.name as plan_name, sp.price as plan_price, sp.billing_cycle as plan_billing_cycle
                      FROM " . $this->table_name . " ss
                      LEFT JOIN schools s ON ss.school_id = s.id
                      LEFT JOIN subscription_plans sp ON ss.plan_id = sp.id
                      ORDER BY ss.created_at DESC";
            
            // Handle filters
            $filters = $_GET;
            $conditions = [];
            $params = [];
            
            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                $conditions[] = "ss.status = :status";
                $params[':status'] = $filters['status'];
            }
            
            if (!empty($filters['school_id'])) {
                $conditions[] = "ss.school_id = :school_id";
                $params[':school_id'] = $filters['school_id'];
            }
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            http_response_code(200);
            echo json_encode($subscriptions);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Server error: " . $e->getMessage()]);
        }
    }

    public function create() {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            
            $required = ['subscription_code', 'school_id', 'plan_id', 'amount', 'start_date', 'end_date'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(["message" => "Missing required field: $field"]);
                    return;
                }
            }
            
            // Check if school exists
            $schoolQuery = "SELECT id FROM schools WHERE id = :school_id";
            $schoolStmt = $this->conn->prepare($schoolQuery);
            $schoolStmt->bindParam(':school_id', $data['school_id']);
            $schoolStmt->execute();
            
            if ($schoolStmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["message" => "School not found"]);
                return;
            }
            
            // Check if plan exists
            $planQuery = "SELECT id FROM subscription_plans WHERE id = :plan_id";
            $planStmt = $this->conn->prepare($planQuery);
            $planStmt->bindParam(':plan_id', $data['plan_id']);
            $planStmt->execute();
            
            if ($planStmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["message" => "Plan not found"]);
                return;
            }
            
            $query = "INSERT INTO " . $this->table_name . " 
                      (subscription_code, school_id, plan_id, amount, status, 
                       start_date, end_date, auto_renew, payment_method, transaction_id) 
                      VALUES (:subscription_code, :school_id, :plan_id, :amount, :status, 
                              :start_date, :end_date, :auto_renew, :payment_method, :transaction_id)";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':subscription_code', $data['subscription_code']);
            $stmt->bindParam(':school_id', $data['school_id']);
            $stmt->bindParam(':plan_id', $data['plan_id']);
            $stmt->bindParam(':amount', $data['amount']);
            $stmt->bindParam(':status', $data['status'] ?? 'active');
            $stmt->bindParam(':start_date', $data['start_date']);
            $stmt->bindParam(':end_date', $data['end_date']);
            $stmt->bindParam(':auto_renew', $data['auto_renew'] ?? true);
            $stmt->bindParam(':payment_method', $data['payment_method'] ?? null);
            $stmt->bindParam(':transaction_id', $data['transaction_id'] ?? null);
            
            if ($stmt->execute()) {
                $lastId = $this->conn->lastInsertId();
                
                // Also create an invoice for this subscription
                $this->createInvoice($lastId, $data);
                
                // Return the created subscription with joined data
                $query = "SELECT ss.*, 
                          s.name as school_name, s.email as school_email,
                          sp.name as plan_name, sp.price as plan_price
                          FROM " . $this->table_name . " ss
                          LEFT JOIN schools s ON ss.school_id = s.id
                          LEFT JOIN subscription_plans sp ON ss.plan_id = sp.id
                          WHERE ss.id = :id";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':id', $lastId);
                $stmt->execute();
                
                $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
                
                http_response_code(201);
                echo json_encode($subscription);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to create subscription"]);
            }
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Server error: " . $e->getMessage()]);
        }
    }

    private function createInvoice($subscriptionId, $subscriptionData) {
        try {
            $invoiceNumber = 'INV-' . date('Y-m') . '-' . str_pad($subscriptionId, 4, '0', STR_PAD_LEFT);
            $issueDate = date('Y-m-d');
            $dueDate = date('Y-m-d', strtotime('+30 days'));
            
            $items = json_encode([
                [
                    'name' => 'Subscription - ' . $subscriptionData['plan_name'] ?? 'Plan',
                    'quantity' => 1,
                    'price' => $subscriptionData['amount'],
                    'amount' => $subscriptionData['amount']
                ]
            ]);
            
            $query = "INSERT INTO invoices 
                      (invoice_number, subscription_id, school_id, amount, 
                       total_amount, issue_date, due_date, status, items) 
                      VALUES (:invoice_number, :subscription_id, :school_id, :amount, 
                              :total_amount, :issue_date, :due_date, :status, :items)";
            
            $stmt = $this->conn->prepare($query);
            
            $stmt->bindParam(':invoice_number', $invoiceNumber);
            $stmt->bindParam(':subscription_id', $subscriptionId);
            $stmt->bindParam(':school_id', $subscriptionData['school_id']);
            $stmt->bindParam(':amount', $subscriptionData['amount']);
            $stmt->bindParam(':total_amount', $subscriptionData['amount']);
            $stmt->bindParam(':issue_date', $issueDate);
            $stmt->bindParam(':due_date', $dueDate);
            $stmt->bindParam(':status', $subscriptionData['status'] ?? 'pending');
            $stmt->bindParam(':items', $items);
            
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