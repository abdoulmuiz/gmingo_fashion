<?php

declare(strict_types=1);

function shop_seed_defaults(string $companyType): void
{
    $stages = [
        ['new', 'New'],
        ['in_progress', 'In progress'],
        ['ready', 'Ready'],
        ['completed', 'Completed'],
    ];
    if ($companyType === 'tailor') {
        $stages = [
            ['new', 'New'],
            ['cutting', 'Cutting'],
            ['sewing', 'Sewing'],
            ['embroidery', 'Embroidery'],
            ['finishing', 'Finishing'],
            ['fitting', 'Fitting'],
            ['ready', 'Ready'],
            ['delivered', 'Delivered'],
        ];
    }
    $last = count($stages) - 1;
    foreach ($stages as $index => $stage) {
        $role = 'open';
        if ($index === $last) {
            $role = 'finished';
        } elseif ($last > 0 && $index === $last - 1) {
            $role = 'ready';
        }
        db_insert('fashion_stage_definitions', [
            'code' => $stage[0],
            'name' => $stage[1],
            'sort_order' => $index + 1,
            'active' => 1,
            'role' => $role,
        ]);
    }
    $cutting = db_select('fashion_stage_definitions', ['name' => 'Cutting'], ['limit' => 1]);
    db_update('fashion_tenants', [
        'deposit_percent' => 0,
        'deposit_stage_id' => $cutting === [] ? null : (string) $cutting[0]['id'],
    ], ['id' => (string) current_tenant_id()]);

    $garments = $companyType === 'tailor' ? [
        ['kaftan', 'Kaftan', 0, [
            ['neck', 'neck'],
            ['shoulder', 'shoulder'],
            ['chest', 'chest'],
            ['stomach', 'stomach'],
            ['sleeve_length', 'sleeve length'],
            ['round_sleeve', 'round sleeve'],
            ['cuff', 'cuff'],
            ['top_length', 'top length'],
        ]],
        // confirm with Kano tailors
        ['senator', 'Senator', 1, [
            ['neck', 'neck'],
            ['chest', 'chest'],
            ['shoulder', 'shoulder'],
            ['sleeve_length', 'sleeve length'],
            ['length', 'length'],
        ]],
        ['agbada', 'Agbada', 1, [
            ['neck', 'neck'],
            ['chest', 'chest'],
            ['shoulder', 'shoulder'],
            ['sleeve_length', 'sleeve length'],
            ['length', 'length'],
        ]],
        ['shirt', 'Shirt', 1, [
            ['neck', 'neck'],
            ['chest', 'chest'],
            ['shoulder', 'shoulder'],
            ['sleeve', 'sleeve'],
            ['length', 'length'],
        ]],
        ['trouser', 'Trouser', 1, [
            ['waist', 'waist'],
            ['hip', 'hip'],
            ['thigh', 'thigh'],
            ['length', 'length'],
            ['bottom', 'bottom'],
        ]],
        ['gown', 'Gown', 1, [
            ['bust', 'bust'],
            ['waist', 'waist'],
            ['hip', 'hip'],
            ['length', 'length'],
        ]],
    ] : [];
    foreach ($garments as $index => $garment) {
        $garmentId = db_insert('fashion_garment_types', [
            'code' => $garment[0],
            'name' => $garment[1],
            'sort_order' => $index + 1,
            'active' => 1,
            'placeholder' => $garment[2],
            'lead_days' => null,
        ]);
        foreach ($garment[3] as $fieldIndex => $field) {
            db_insert('fashion_measurement_fields', [
                'garment_type_id' => $garmentId,
                'field_key' => $field[0],
                'name' => $field[1],
                'sort_order' => $fieldIndex + 1,
                'active' => 1,
                'placeholder' => $garment[2],
            ]);
        }
    }

    db_insert('fashion_order_counters', [
        'prefix' => $companyType === 'tailor' ? 'TK' : 'OR',
        'last_number' => 1000,
    ]);
    db_insert('fashion_receipt_counters', [
        'prefix' => 'RC',
        'last_number' => 1000,
    ]);
}
