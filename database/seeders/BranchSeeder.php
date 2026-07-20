<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * 建立 6 間分店的初始資料
     */
    public function run(): void
    {
        foreach (Branch::defaultBranches() as $branch) {
            Branch::firstOrCreate(
                ['code' => $branch['code']],
                $branch
            );
        }
    }
}
