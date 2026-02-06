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
                ss.id,
                ss.subscription_code,
                ss.school_code,
                ss.school_name,
                ss.school_email,
                ss.school_phone,
                ss.school_address,
                ss.total_students,
                ss.total_buses,
                ss.plan_id,
                sp.name AS plan_name,
                sp.billing_cycle,
                sp.features,
                ss.amount,
                ss.status,
                ss.start_date,
                ss.end_date,
                ss.auto_renew,
                ss.payment_method,
                ss.transaction_id,
                ss.created_at
            FROM {$this->table_name} ss
            LEFT JOIN subscription_plans sp ON ss.plan_id = sp.id
            ORDER BY ss.created_at DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            http_response_code(200);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => $e->getMessage()]);
        }
    }

    public function getById($id) {
        try {
            $query = "SELECT 
                ss.*,
                sp.name AS plan_name,
                sp.billing_cycle,
                sp.features
            FROM {$this->table_name} ss
            LEFT JOIN subscription_plans sp ON ss.plan_id = sp.id
            WHERE ss.id = :id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["message" => "Subscription not found"]);
                return;
            }

            http_response_code(200);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => $e->getMessage()]);
        }
    }

    public function create() {
        // Set JSON header at the beginning
        header('Content-Type: application/json');
        
        // Suppress HTML errors
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
        error_reporting(E_ALL);
        ini_set('log_errors', 1);
        ini_set('error_log', 'php_errors.log');

        try {
            $data = json_decode(file_get_contents("php://input"), true);

            // Validate required fields
            $required = [
                'school_code', 'school_name', 'school_email',
                'plan_id', 'start_date', 'plan_billing_cycle'
            ];

            foreach ($required as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(["message" => "$field is required"]);
                    return;
                }
            }

            // Fetch plan details
            $planStmt = $this->conn->prepare(
                "SELECT price, billing_cycle FROM subscription_plans WHERE id = :plan_id"
            );
            $planStmt->bindParam(":plan_id", $data['plan_id'], PDO::PARAM_INT);
            $planStmt->execute();

            if ($planStmt->rowCount() === 0) {
                http_response_code(400);
                echo json_encode(["message" => "Invalid plan"]);
                return;
            }

            $plan = $planStmt->fetch(PDO::FETCH_ASSOC);
            $billingCycle = $data['plan_billing_cycle'] ?? $plan['billing_cycle'];
            
            // Calculate end date
            $startDate = new DateTime($data['start_date']);
            $endDate = clone $startDate;
            
            switch (strtolower($billingCycle)) {
                case 'monthly':
                    $endDate->modify('+1 month');
                    break;
                case 'quarterly':
                    $endDate->modify('+3 months');
                    break;
                case 'annual':
                    $endDate->modify('+1 year');
                    break;
                default:
                    $endDate->modify('+30 days');
            }

            $subscriptionCode = "SUB-" . date("Ymd") . "-" . strtoupper(uniqid());

            $query = "INSERT INTO {$this->table_name} (
                subscription_code,
                school_code,
                school_name,
                school_email,
                school_phone,
                school_address,
                total_students,
                total_buses,
                plan_id,
                amount,
                status,
                start_date,
                end_date,
                auto_renew,
                payment_method,
                transaction_id,
                created_at
            ) VALUES (
                :subscription_code,
                :school_code,
                :school_name,
                :school_email,
                :school_phone,
                :school_address,
                :total_students,
                :total_buses,
                :plan_id,
                :amount,
                :status,
                :start_date,
                :end_date,
                :auto_renew,
                :payment_method,
                :transaction_id,
                NOW()
            )";

            $stmt = $this->conn->prepare($query);

            // Use bindValue() instead of bindParam() for direct values
            $stmt->bindValue(":subscription_code", $subscriptionCode);
            $stmt->bindValue(":school_code", $data['school_code']);
            $stmt->bindValue(":school_name", $data['school_name']);
            $stmt->bindValue(":school_email", $data['school_email']);
            
            $school_phone = isset($data['school_phone']) ? $data['school_phone'] : null;
            $stmt->bindValue(":school_phone", $school_phone);
            
            $school_address = isset($data['school_address']) ? $data['school_address'] : null;
            $stmt->bindValue(":school_address", $school_address);
            
            $stmt->bindValue(":total_students", isset($data['total_students']) ? $data['total_students'] : 0, PDO::PARAM_INT);
            $stmt->bindValue(":total_buses", isset($data['total_buses']) ? $data['total_buses'] : 0, PDO::PARAM_INT);
            $stmt->bindValue(":plan_id", $data['plan_id'], PDO::PARAM_INT);
            
            // Get amount from data or use plan price
            $amount = isset($data['amount']) ? $data['amount'] : $plan['price'];
            $stmt->bindValue(":amount", $amount);
            
            $status = isset($data['status']) ? $data['status'] : 'active';
            $stmt->bindValue(":status", $status);
            
            $stmt->bindValue(":start_date", $data['start_date']);
            $stmt->bindValue(":end_date", $endDate->format('Y-m-d'));
            
            $auto_renew = isset($data['auto_renew']) ? ($data['auto_renew'] ? 1 : 0) : 0;
            $stmt->bindValue(":auto_renew", $auto_renew, PDO::PARAM_INT);
            
            $payment_method = isset($data['payment_method']) ? $data['payment_method'] : 'manual';
            $stmt->bindValue(":payment_method", $payment_method);
            
            $transaction_id = isset($data['transaction_id']) ? $data['transaction_id'] : 'TXN-' . time();
            $stmt->bindValue(":transaction_id", $transaction_id);

            if ($stmt->execute()) {
                $lastInsertId = $this->conn->lastInsertId();
                
                http_response_code(201);
                echo json_encode([
                    "message" => "Subscription created successfully",
                    "subscription_code" => $subscriptionCode,
                    "id" => $lastInsertId,
                    "start_date" => $data['start_date'],
                    "end_date" => $endDate->format('Y-m-d'),
                    "billing_cycle" => $billingCycle
                ]);
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("SQL Error: " . print_r($errorInfo, true));
                
                http_response_code(500);
                echo json_encode([
                    "message" => "Failed to create subscription",
                    "sql_error" => $errorInfo[2]
                ]);
            }

        } catch (PDOException $e) {
            error_log("PDOException in create: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "message" => "Database error",
                "error" => $e->getMessage()
            ]);
        }
    }

    public function update($id) {
        // Set JSON header FIRST
        header('Content-Type: application/json');
        
        // Turn off HTML error display
        ini_set('display_errors', 0);
        ini_set('display_startup_errors', 0);
        error_reporting(E_ALL);
        ini_set('log_errors', 1);
        ini_set('error_log', 'php_errors.log');

        try {
            // Get raw input
            $rawInput = file_get_contents("php://input");
            
            if (empty($rawInput)) {
                http_response_code(400);
                echo json_encode(["message" => "No data provided"]);
                return;
            }
            
            $data = json_decode($rawInput, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    "message" => "Invalid JSON format",
                    "json_error" => json_last_error_msg()
                ]);
                return;
            }

            error_log("Received update data for ID $id: " . print_r($data, true));

            // Check required fields
            $requiredFields = ['school_name', 'plan_id', 'amount', 'status', 'end_date'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    http_response_code(400);
                    echo json_encode(["message" => "Missing required field: $field"]);
                    return;
                }
            }

            // Check if subscription exists
            $checkStmt = $this->conn->prepare("SELECT id FROM {$this->table_name} WHERE id = :id");
            $checkStmt->bindParam(":id", $id, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if ($checkStmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["message" => "Subscription with ID $id not found"]);
                return;
            }

            // Check if plan exists
            $planStmt = $this->conn->prepare("SELECT id FROM subscription_plans WHERE id = :plan_id");
            $planStmt->bindParam(":plan_id", $data['plan_id'], PDO::PARAM_INT);
            $planStmt->execute();
            
            if ($planStmt->rowCount() === 0) {
                http_response_code(400);
                echo json_encode(["message" => "Invalid plan_id: {$data['plan_id']}"]);
                return;
            }

            // Update ALL fields that might be sent
            $query = "UPDATE {$this->table_name}
            SET
                school_name = :school_name,
                school_email = :school_email,
                school_phone = :school_phone,
                school_address = :school_address,
                total_students = :total_students,
                total_buses = :total_buses,
                plan_id = :plan_id,
                amount = :amount,
                status = :status,
                end_date = :end_date,
                auto_renew = :auto_renew,
                payment_method = :payment_method,
                transaction_id = :transaction_id
                -- Note: subscription_code, school_code, start_date, created_at are NOT updated
            WHERE id = :id";

            $stmt = $this->conn->prepare($query);

            // Bind all parameters with proper null handling
            $stmt->bindParam(":school_name", $data['school_name']);
            
            // Handle nullable fields - use isset to check
            $school_email = isset($data['school_email']) ? $data['school_email'] : null;
            $stmt->bindParam(":school_email", $school_email);
            
            $school_phone = isset($data['school_phone']) ? $data['school_phone'] : null;
            $stmt->bindParam(":school_phone", $school_phone);
            
            $school_address = isset($data['school_address']) ? $data['school_address'] : null;
            $stmt->bindParam(":school_address", $school_address);
            
            $stmt->bindParam(":total_students", $data['total_students'], PDO::PARAM_INT);
            $stmt->bindParam(":total_buses", $data['total_buses'], PDO::PARAM_INT);
            $stmt->bindParam(":plan_id", $data['plan_id'], PDO::PARAM_INT);
            $stmt->bindParam(":amount", $data['amount']);
            $stmt->bindParam(":status", $data['status']);
            
            // Format end_date properly
            $end_date = isset($data['end_date']) ? date('Y-m-d', strtotime($data['end_date'])) : null;
            $stmt->bindParam(":end_date", $end_date);
            
            $auto_renew = isset($data['auto_renew']) ? ($data['auto_renew'] ? 1 : 0) : 0;
            $stmt->bindParam(":auto_renew", $auto_renew, PDO::PARAM_INT);
            
            $payment_method = isset($data['payment_method']) ? $data['payment_method'] : null;
            $stmt->bindParam(":payment_method", $payment_method);
            
            $transaction_id = isset($data['transaction_id']) ? $data['transaction_id'] : null;
            $stmt->bindParam(":transaction_id", $transaction_id);
            
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);

            // Execute
            if ($stmt->execute()) {
                $affectedRows = $stmt->rowCount();
                
                if ($affectedRows > 0) {
                    http_response_code(200);
                    echo json_encode([
                        "message" => "Subscription updated successfully",
                        "updated_id" => $id
                    ]);
                } else {
                    http_response_code(200);
                    echo json_encode([
                        "message" => "No changes made (data might be the same)",
                        "updated_id" => $id
                    ]);
                }
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("SQL Error: " . print_r($errorInfo, true));
                
                http_response_code(500);
                echo json_encode([
                    "message" => "Failed to execute update",
                    "sql_error" => $errorInfo[2]
                ]);
            }

        } catch (PDOException $e) {
            error_log("PDOException: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "message" => "Database error",
                "error" => $e->getMessage()
            ]);
        }
    }

    public function delete($id) {
        try {
            $stmt = $this->conn->prepare(
                "DELETE FROM {$this->table_name} WHERE id = :id"
            );
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);
            $stmt->execute();

            http_response_code(200);
            echo json_encode(["message" => "Subscription deleted"]);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => $e->getMessage()]);
        }
    }
}
