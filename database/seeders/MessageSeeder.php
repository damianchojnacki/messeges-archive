<?php

namespace Database\Seeders;

use App\Models\Message;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $records = 100000;
        $batches = $records / ($batch_size = 10000);

        $bar = $this->command->getOutput()->createProgressBar($batches);

        $bar->start();

        for ($i = 0; $i < $batches; $i++) {
            $data = [];

            for ($j = 0; $j < $batch_size; $j++) {
                $data[] = [
                    'message' => fake()->text(),
                    'details' => json_encode(rand(0, 1) ? ['priority' => rand(1, 9)] : []),
                    'created_at' => now()->subDays(rand(0, 200)),
                ];
            }

            Message::insert($data);

            $bar->advance();
        }

        $bar->finish();

        $this->command->newLine();
    }
}
