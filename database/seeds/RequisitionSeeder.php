<?php

use Illuminate\Database\Seeder;

class RequisitionSeeder extends Seeder{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(){
        
        DB::table('type_requisition')->insert([
            ['id' => 1, 'name' => 'Manual', 'shortname' => 'MAN'],
            ['id' => 2, 'name' => 'Automático', 'shortname' => 'AUT'],
        ]);

        DB::table('requisition_process')->insert([
            ['id' => 1, 'name' => 'Levantando pedido'],
            ['id' => 2, 'name' => 'Ha llegado a bodega el pedido'],
            ['id' => 3, 'name' => 'Surtiendo el pedido'],
            ['id' => 4, 'name' => 'El pedido ha terminado de surtirse'],
            ['id' => 5, 'name' => 'El pedido esta en camino'],
            ['id' => 6, 'name' => 'El pedido ha llegado a su destino'],
            ['id' => 7, 'name' => 'Inicio de válidación'],
            ['id' => 8, 'name' => 'Fin de válidación'],
            ['id' => 9, 'name' => 'El pedido ha finalizado'],
            ['id' => 10, 'name' => 'El pedido ha sido cancelado'],
            ['id' => 11, 'name' => 'El pedido ha expirado']
        ]);
    }
}
