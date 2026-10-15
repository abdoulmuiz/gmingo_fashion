<?php

declare(strict_types=1);

function customer_create(array $input): array
{
    $name = trim((string) ($input['name'] ?? ''));
    $phone = phone_normalize((string) ($input['phone'] ?? ''));
    $gender = (string) ($input['gender'] ?? '');
    $type = (string) ($input['customer_type'] ?? 'regular');
    $errors = [];
    if ($name === '' || strlen($name) > 190) {
        $errors['name'] = 'required';
    }
    if ($phone === null) {
        $errors['phone'] = 'invalid_phone';
    }
    if (!in_array($gender, ['male', 'female'], true)) {
        $errors['gender'] = 'required';
    }
    if (!in_array($type, ['vip', 'regular'], true)) {
        $errors['customer_type'] = 'required';
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }
    $id = db_insert('fashion_customers', [
        'name' => $name,
        'phone' => $phone,
        'gender' => $gender,
        'customer_type' => $type,
        'messages_opt_in' => 0,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    return ['id' => $id];
}

function customer_search(string $query): array
{
    $query = trim($query);
    if ($query === '') {
        return db_select('fashion_customers', [], ['order' => 'name ASC', 'limit' => 200]);
    }
    return db_query(
        'SELECT * FROM fashion_customers WHERE tenant_id = :tenant_id AND (name LIKE :q ESCAPE \'\\\\\' OR phone LIKE :phone ESCAPE \'\\\\\') ORDER BY name ASC LIMIT 200',
        ['q' => like_contains($query), 'phone' => like_contains($query)]
    );
}

function customer_orders(string $customerId): array
{
    $rows = db_query(
        'SELECT o.id, o.order_number, o.due_date, o.created_at,
            (SELECT COALESCE(SUM(i.quantity * i.price_kobo), 0) FROM fashion_order_items i WHERE i.order_id = o.id AND i.tenant_id = :tenant_id) AS total_kobo,
            (SELECT COALESCE(SUM(p.amount_kobo), 0) FROM fashion_payments p WHERE p.order_id = o.id AND p.tenant_id = :tenant_id) AS paid_kobo
         FROM fashion_orders o
         WHERE o.tenant_id = :tenant_id AND o.customer_id = :customer_id
         ORDER BY o.created_at DESC',
        ['customer_id' => $customerId]
    );
    $orders = [];
    foreach ($rows as $row) {
        $total = (int) $row['total_kobo'];
        $paid = (int) $row['paid_kobo'];
        $balance = $total - $paid;
        $orders[] = [
            'id' => $row['id'],
            'order_number' => $row['order_number'],
            'due_date' => $row['due_date'],
            'total_kobo' => $total,
            'paid_kobo' => $paid,
            'balance_kobo' => $balance,
        ];
    }
    return $orders;
}

function customer_profile(string $id): ?array
{
    if (!is_uuid($id)) {
        return null;
    }
    $rows = db_select('fashion_customers', ['id' => $id]);
    if ($rows === []) {
        return null;
    }
    $customer = $rows[0];
    $orders = customer_orders($id);
    $spent = 0;
    $owed = 0;
    foreach ($orders as $order) {
        $spent += $order['paid_kobo'];
        if ($order['balance_kobo'] > 0) {
            $owed += $order['balance_kobo'];
        }
    }
    $customer['orders'] = $orders;
    $customer['order_count'] = count($orders);
    $customer['spent_kobo'] = $spent;
    $customer['owed_kobo'] = $owed;
    return $customer;
}
