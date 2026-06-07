<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ImportMedicinesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:medicines {file} {--business_id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ingest Hugging Face medicine dataset into the pharmacy module.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $file = $this->argument('file');
        $businessId = $this->option('business_id');

        if (!$businessId) {
            $business = DB::table('businesses')->first();
            if (!$business) {
                $this->error("No business found in the database.");
                return Command::FAILURE;
            }
            $businessId = $business->id;
        }

        if (!file_exists($file)) {
            $this->error("File not found at path: {$file}");
            return Command::FAILURE;
        }

        $this->info("Starting ingestion of medicines into Business ID: {$businessId}");

        $user = DB::table('users')->where('business_id', $businessId)->first();
        if (!$user) {
            $user = DB::table('users')->first();
            if (!$user) {
                $this->error("No user found in the system to assign created_by. Please create a user first.");
                return Command::FAILURE;
            }
        }
        $createdBy = $user->id;

        $isParquet = Str::endsWith($file, '.parquet');
        
        $records = LazyCollection::make(function () use ($file, $isParquet) {
            if ($isParquet) {
                $reader = new \Flow\Parquet\Reader();
                $parquetFile = $reader->read($file);
                foreach ($parquetFile->values() as $row) {
                    yield $row;
                }
            } else {
                $handle = fopen($file, 'r');
                while (($line = fgetcsv($handle)) !== false) {
                    yield $line;
                }
                fclose($handle);
            }
        });

        $headers = [];
        $totalRows = 0;
        $insertedCount = 0;

        $now = Carbon::now()->toDateTimeString();
        
        $this->info("Calculating total rows...");
        $totalLines = 0;
        
        if ($isParquet) {
            // Count rows in parquet
            $reader = new \Flow\Parquet\Reader();
            $parquetFile = $reader->read($file);
            foreach ($parquetFile->values() as $row) {
                $totalLines++;
            }
        } else {
            $handle = fopen($file, 'r');
            while (fgets($handle) !== false) {
                $totalLines++;
            }
            fclose($handle);
            $totalLines = max(0, $totalLines - 1); // Exclude header
        }

        $bar = $this->output->createProgressBar($totalLines);
        $bar->start();

        // Find or create default category/unit if needed
        $categoryId = DB::table('categories')->where('business_id', $businessId)->value('id');
        if (!$categoryId) {
            $categoryId = DB::table('categories')->insertGetId([
                'business_id' => $businessId,
                'name' => 'Medicine',
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now
            ]);
        }

        $unitId = DB::table('units')->where('business_id', $businessId)->value('id');
        if (!$unitId) {
            $unitId = DB::table('units')->insertGetId([
                'business_id' => $businessId,
                'name' => 'Pieces',
                'short_name' => 'pcs',
                'allow_decimal' => 0,
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now
            ]);
        }

        $brandCache = [];

        DB::beginTransaction();

        try {
            $chunk = [];
            foreach ($records as $index => $row) {
                if (!$isParquet) {
                    if ($index === 0) {
                        $headers = array_map('strtolower', array_map('trim', $row));
                        continue;
                    }
                    if (count($row) !== count($headers)) continue;
                    $data = array_combine($headers, $row);
                } else {
                    // Parquet is associative
                    $data = $row;
                }

                // Map data from parquet: brand name, generic, manufacturer, dosage form, strength, package container
                $brandNameRaw = $data['brand name'] ?? '';
                $strength = $data['strength'] ?? '';
                $formulation = $data['formulation'] ?? $data['dosage form'] ?? '';
                
                $name = trim("{$brandNameRaw} {$strength} {$formulation}");
                if (empty($name)) {
                    $name = 'Unknown Medicine ' . mt_rand(1000, 9999);
                }

                $genericName = $data['generic name'] ?? $data['generic'] ?? null;
                $companyName = $data['company name'] ?? $data['manufacturer'] ?? 'Unknown Company';
                
                $price = floatval($data['price'] ?? 0);
                $packageContainer = $data['package container'] ?? '';
                if ($price == 0 && strpos($packageContainer, '৳') !== false) {
                    $priceParts = explode('৳', $packageContainer);
                    if (isset($priceParts[1])) {
                        $price = floatval(trim($priceParts[1]));
                    }
                }

                // Auto-create brand
                if (!isset($brandCache[$companyName])) {
                    $brand = DB::table('brands')
                        ->where('business_id', $businessId)
                        ->where('name', $companyName)
                        ->first();
                        
                    if (!$brand) {
                        $brandId = DB::table('brands')->insertGetId([
                            'business_id' => $businessId,
                            'name' => $companyName,
                            'created_by' => $createdBy,
                            'created_at' => $now,
                            'updated_at' => $now
                        ]);
                        $brandCache[$companyName] = $brandId;
                    } else {
                        $brandCache[$companyName] = $brand->id;
                    }
                }

                $sku = 'MED-' . Str::upper(Str::random(6)) . '-' . mt_rand(1000, 9999);

                $chunk[] = [
                    'name' => $name,
                    'generic_name' => $genericName,
                    'business_id' => $businessId,
                    'type' => 'single',
                    'unit_id' => $unitId,
                    'brand_id' => $brandCache[$companyName],
                    'category_id' => $categoryId,
                    'sku' => $sku,
                    'barcode_type' => 'C128',
                    'enable_stock' => 1,
                    'alert_quantity' => 10,
                    'purchase_price' => $price,
                    'sell_price_inc_tax' => $price, // simple mapping
                    'has_serial_number' => 0,
                    'created_by' => $createdBy,
                    'created_at' => $now,
                    'updated_at' => $now
                ];

                if (count($chunk) === 500) {
                    DB::table('products')->insert($chunk);
                    $insertedCount += count($chunk);
                    $chunk = [];
                    $bar->advance(500);
                }
            }

            if (!empty($chunk)) {
                DB::table('products')->insert($chunk);
                $insertedCount += count($chunk);
                $bar->advance(count($chunk));
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("\nError during import: " . $e->getMessage());
            return Command::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Successfully imported {$insertedCount} medicines.");

        return Command::SUCCESS;
    }
}
