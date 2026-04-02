<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$invoice = App\Models\InvoiceRental::where('name', 'INVRS/2026/03010')->first();
$rentalLines = $invoice->lines;
$discountLines = $rentalLines->filter(fn($l) => str_contains(strtolower($l->description), 'discount') || str_contains(strtolower($l->description), 'potongan') || $l->price_unit < 0);
$roundingLines = $rentalLines->filter(fn($l) => str_contains(strtolower($l->description), 'pembulatan') || str_contains(strtolower($l->description), 'rounding'));
$rentalLines = $rentalLines->reject(fn($l) => $discountLines->contains('id', $l->id) || $roundingLines->contains('id', $l->id))->values();
$detailTexts = $rentalLines->pluck('description')->map(fn($d) => trim($d))->filter(fn($d) => !empty($d) && !str_starts_with($d, '['))->unique()->toArray();

echo json_encode(array_values($detailTexts), JSON_PRETTY_PRINT);
