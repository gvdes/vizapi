<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationSeeder extends Seeder{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(){
        DB::table('celler_type')->insert([
            ['id' => 1, 'name' => 'General', 'alias' => 'GEN'],
            ['id' => 2, 'name' => 'Exhibición', 'alias' => 'EXH'],
        ]);
        $workpoints = DB::table('workpoints')->get();
        foreach($workpoints as $workpoint){
            DB::table('celler')->insert([
                ['name' => 'General', '_workpoint' => $workpoint->id, '_type' => 1],
                ['name' => 'Exhibición', '_workpoint' => $workpoint->id, '_type' => 2]
            ]);
        }
    }
}