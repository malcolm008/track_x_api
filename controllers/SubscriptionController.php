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
        try {
            $data = json_decode(file_get_contents("php://input"), true);

            // Validate required fields
            $required = [
                'school_code', 'school_name', 'school_email',
                'plan_id', 'start_date', 'end_date'
            ];

            foreach ($required as $field) {
                if (empty($data[$field])) {
                    http_response_code(400);
                    echo json_encode(["message" => "$field is required"]);
                    return;
                }
            }

            // Fetch plan price
            $planStmt = $this->conn->prepare(
                "SELECT price FROM subscription_plans WHERE id = :plan_id"
            );
            $planStmt->bindParam(":plan_id", $data['plan_id'], PDO::PARAM_INT);
            $planStmt->execute();

            if ($planStmt->rowCount() === 0) {
                http_response_code(400);
                echo json_encode(["message" => "Invalid plan"]);
                return;
            }

            $plan = $planStmt->fetch(PDO::FETCH_ASSOC);

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

            $stmt->bindParam(":subscription_code", $subscriptionCode);
            $stmt->bindParam(":school_code", $data['school_code']);
            $stmt->bindParam(":school_name", $data['school_name']);
            $stmt->bindParam(":school_email", $data['school_email']);
            $stmt->bindParam(":school_phone", $data['school_phone'] ?? null);
            $stmt->bindParam(":school_address", $data['school_address'] ?? null);
            $stmt->bindParam(":total_students", $data['total_students'] ?? 0, PDO::PARAM_INT);
            $stmt->bindParam(":total_buses", $data['total_buses'] ?? 0, PDO::PARAM_INT);
            $stmt->bindParam(":plan_id", $data['plan_id'], PDO::PARAM_INT);
            $stmt->bindParam(":amount", $plan['price']);
            $stmt->bindParam(":status", $data['status'] ?? 'active');
            $stmt->bindParam(":start_date", $data['start_date']);
            $stmt->bindParam(":end_date", $data['end_date']);
            $stmt->bindParam(":auto_renew", !empty($data['auto_renew']) ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindParam(":payment_method", $data['payment_method'] ?? 'manual');
            $stmt->bindParam(":transaction_id", $data['transaction_id'] ?? 'TXN-' . time());

            $stmt->execute();

            http_response_code(201);
            echo json_encode([
                "message" => "Subscription created",
                "subscription_code" => $subscriptionCode
            ]);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => $e->getMessage()]);
        }
    }

    public function update($id) {
        try {
            $data = json_decode(file_get_contents("php://input"), true);

            $query = "UPDATE {$this->table_name}
            SET
                status = :status,
                end_date = :end_date,
                auto_renew = :auto_renew,
                amount = :amount
            WHERE id = :id";

            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(":status", $data['status']);
            $stmt->bindParam(":end_date", $data['end_date']);
            $stmt->bindParam(":auto_renew", !empty($data['auto_renew']) ? 1 : 0, PDO::PARAM_INT);
            $stmt->bindParam(":amount", $data['amount']);
            $stmt->bindParam(":id", $id, PDO::PARAM_INT);

            $stmt->execute();

            http_response_code(200);
            echo json_encode(["message" => "Subscription updated"]);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => $e->getMessage()]);
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
