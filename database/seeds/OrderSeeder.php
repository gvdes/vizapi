
<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderSeeder extends Seeder{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(){
        DB::table('cash_status')->insert([
            ['id' => 1, 'name' => 'Activa'],
            ['id' => 2, 'name' => 'Desactivada'],
            ['id' => 3, 'name' => 'Bloqueada']
        ]);

        DB::table('printer_types')->insert([
            ['id' => 1, 'name' => 'Preventa'],
            ['id' => 2, 'name' => 'Cajas'],
            ['id' => 3, 'name' => 'Bodega'],
            ['id' => 4, 'name' => 'Bodega / Carrito']
        ]);

        DB::table('order_process')->insert([
            ['id' => 1, 'name' => 'Pedido iniciado', 'active' => 1, 'allow' => 0],
            /* ['id' => 2, 'name' => 'Levantando pedido', 'active' => 1, 'allow' => 0], */
            ['id' => 2, 'name' => 'Pedido levantado', 'active' => 1, 'allow' => 0],
            ['id' => 3, 'name' => 'Pedido en espera de válidación', 'active' => 1, 'allow' => 1],
            ['id' => 4, 'name' => 'Ha llegado a bodega el pedido', 'active' => 1, 'allow' => 0],
            ['id' => 5, 'name' => 'Surtiendo el pedido', 'active' => 1, 'allow' => 0],
            ['id' => 6, 'name' => 'El pedido ha terminado de surtirse', 'active' => 1, 'allow' => 1],
            ['id' => 7, 'name' => 'El pedido esta esperando en caja', 'active' => 1, 'allow' => 1],
            ['id' => 8, 'name' => 'El pedido se esta cobrando en caja', 'active' => 1, 'allow' => 1],
            ['id' => 9, 'name' => 'El pedido se ha cobrado', 'active' => 1, 'allow' => 0],
            ['id' => 10, 'name' => 'El pedido ha finalizado', 'active' => 1, 'allow' => 0],
            ['id' => 11, 'name' => 'El pedido ha sido cancelado', 'active' => 1, 'allow' => 0],
            ['id' => 12, 'name' => 'El pedido ha expirado', 'active' => 1, 'allow' => 0]
        ]);

        DB::table('printers')->insert([
            ['id' => 1, 'name'=> 'Preventa', 'ip'=> '192.168.1.200', '_workpoint' => 1, '_type' => 1],
            ['id' => 2, 'name'=> 'Bodega', 'ip'=> '192.168.1.200', '_workpoint' => 1, '_type' => 2],
            ['id' => 3, 'name'=> 'Bodega / carrito', 'ip'=> '192.168.1.200', '_workpoint' => 1, '_type' => 3],
            ['id' => 4, 'name'=> 'Caja', 'ip'=> '192.168.1.200', '_workpoint' => 1, '_type' => 4]
        ]);
    }
}