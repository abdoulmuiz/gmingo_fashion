<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/customers.php';

function customers_index(): void
{
    require_module('fashion.orders');
    $query = trim((string) ($_GET['q'] ?? ''));
    render('customers/index', [
        'title' => t('customers'),
        'query' => $query,
        'customers' => customer_search($query),
    ]);
}

function customers_form(array $errors = [], array $old = []): void
{
    require_module('fashion.orders');
    render('customers/form', [
        'title' => t('new_customer'),
        'errors' => $errors,
        'old' => $old,
    ]);
}

function customers_save(): void
{
    require_module('fashion.orders');
    csrf_check();
    $input = [
        'name' => (string) ($_POST['name'] ?? ''),
        'phone' => (string) ($_POST['phone'] ?? ''),
        'gender' => (string) ($_POST['gender'] ?? ''),
        'customer_type' => (string) ($_POST['customer_type'] ?? ''),
    ];
    $result = customer_create($input);
    if (isset($result['errors'])) {
        customers_form($result['errors'], $input);
        return;
    }
    redirect('/customers/' . $result['id']);
}

function customers_show(string $id): void
{
    require_module('fashion.orders');
    $profile = customer_profile($id);
    if ($profile === null) {
        http_response_code(404);
        render('simple', ['title' => t('not_found'), 'body' => t('not_found_body')]);
        return;
    }
    render('customers/show', [
        'title' => $profile['name'],
        'customer' => $profile,
    ]);
}
