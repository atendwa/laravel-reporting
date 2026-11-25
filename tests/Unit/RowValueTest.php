<?php

declare(strict_types=1);

use Reporting\Support\RowValue;

it('finds a row value by the snake-case, lower-case or exact header', function (): void {
    $row = ['grn' => 'GRN-1', 'delivery_note_number' => 'DN-7', 'Custom Key' => 'x'];

    expect(RowValue::for($row, 'GRN'))->toBe('GRN-1')
        ->and(RowValue::for($row, 'Delivery Note Number'))->toBe('DN-7')
        ->and(RowValue::for($row, 'Custom Key'))->toBe('x')
        ->and(RowValue::for($row, 'Missing'))->toBe('');
});
