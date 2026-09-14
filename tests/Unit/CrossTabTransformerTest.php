<?php

declare(strict_types=1);

use Reporting\Utilities\CrossTabTransformer;

it('pivots flat data into a cross-tab structure', function (): void {
    $transformer = new CrossTabTransformer;

    $data = collect([
        ['cashier' => 'Alice', 'payment_mode' => 'Cash', 'total_amount' => 100.0],
        ['cashier' => 'Alice', 'payment_mode' => 'Card', 'total_amount' => 200.0],
        ['cashier' => 'Bob', 'payment_mode' => 'Cash', 'total_amount' => 150.0],
        ['cashier' => 'Bob', 'payment_mode' => 'Card', 'total_amount' => 50.0],
    ]);

    $result = $transformer->pivot($data, 'cashier', 'payment_mode', 'total_amount');

    expect($result['column_groups'])->toBe(['Card', 'Cash']);
    expect($result['rows'])->toHaveCount(2);

    expect($result['rows']['Alice']['Card'])->toBe(200.0);
    expect($result['rows']['Alice']['Cash'])->toBe(100.0);
    expect($result['rows']['Bob']['Card'])->toBe(50.0);
    expect($result['rows']['Bob']['Cash'])->toBe(150.0);

    expect($result['aggregates']['Card'])->toBe(250.0);
    expect($result['aggregates']['Cash'])->toBe(250.0);
});

it('fills empty intersections with zero', function (): void {
    $transformer = new CrossTabTransformer;

    $data = collect([
        ['cashier' => 'Alice', 'payment_mode' => 'Cash', 'total_amount' => 100.0],
        ['cashier' => 'Bob', 'payment_mode' => 'Card', 'total_amount' => 50.0],
    ]);

    $result = $transformer->pivot($data, 'cashier', 'payment_mode', 'total_amount');

    expect($result['rows']['Alice']['Card'])->toEqual(0);
    expect($result['rows']['Bob']['Cash'])->toEqual(0);
});

it('handles single row of data', function (): void {
    $transformer = new CrossTabTransformer;

    $data = collect([
        ['cashier' => 'Alice', 'payment_mode' => 'Cash', 'total_amount' => 100.0],
    ]);

    $result = $transformer->pivot($data, 'cashier', 'payment_mode', 'total_amount');

    expect($result['column_groups'])->toBe(['Cash']);
    expect($result['rows'])->toHaveCount(1);
    expect($result['rows']['Alice']['Cash'])->toBe(100.0);
    expect($result['aggregates']['Cash'])->toBe(100.0);
});

it('sums duplicate row-column intersections', function (): void {
    $transformer = new CrossTabTransformer;

    $data = collect([
        ['cashier' => 'Alice', 'payment_mode' => 'Cash', 'total_amount' => 100.0],
        ['cashier' => 'Alice', 'payment_mode' => 'Cash', 'total_amount' => 75.0],
    ]);

    $result = $transformer->pivot($data, 'cashier', 'payment_mode', 'total_amount');

    expect($result['rows']['Alice']['Cash'])->toBe(175.0);
    expect($result['aggregates']['Cash'])->toBe(175.0);
});

it('returns empty arrays for empty data', function (): void {
    $transformer = new CrossTabTransformer;

    $result = $transformer->pivot(collect(), 'cashier', 'payment_mode', 'total_amount');

    expect($result['column_groups'])->toBe([]);
    expect($result['rows'])->toBe([]);
    expect($result['aggregates'])->toBe([]);
    expect($result['static_columns'])->toBe([]);
});

it('preserves static columns that are not part of the grouping', function (): void {
    $transformer = new CrossTabTransformer;

    $data = collect([
        ['cashier' => 'Alice', 'payment_mode' => 'Cash', 'total_amount' => 100.0, 'department' => 'Sales'],
        ['cashier' => 'Alice', 'payment_mode' => 'Card', 'total_amount' => 200.0, 'department' => 'Sales'],
    ]);

    $result = $transformer->pivot($data, 'cashier', 'payment_mode', 'total_amount');

    expect($result['static_columns'])->toBe(['department']);
    expect($result['rows']['Alice']['department'])->toBe('Sales');
});
