<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(){
        /* DB::table('price_list')->insert([
            ['id' => 1, 'name' => 'Menudeo', 'alias' => 'MEN'],
            ['id' => 2, 'name' => 'Mayoreo', 'alias' => 'MAY'],
            ['id' => 3, 'name' => 'Docena', 'alias' => 'DOC'],
            ['id' => 4, 'name' => 'Media caja', 'alias' => 'MED'],
            ['id' => 5, 'name' => 'Caja', 'alias' => 'CAJ'],
            ['id' => 6, 'name' => 'Especial', 'alias' => 'ESP'],
            ['id' => 7, 'name' => 'Centro', 'alias' => 'CEN'],
            ['id' => 8, 'name' => 'Costo', 'alias' => 'COS']
        ]);
        
        DB::table('product_actions')->insert([
            ['id' => 1, 'name' => 'Alta', 'description' => 'Alta del producto'],
            ['id' => 2, 'name' => 'Modificación', 'description' => 'Modificación del producto'],
            ['id' => 3, 'name' => 'Asignación de precios', 'description' => 'Se han establecido precios al producto por primera vez'],
            ['id' => 4, 'name' => 'Modificación de precios', 'description' => 'Se han modificado los precios del productos'],
            ['id' => 5, 'name' => 'Agotado', 'description' => 'Sé cambiado el status del producto ha agotado'],
            ['id' => 6, 'name' => 'Reservado', 'description' => 'Todas las existencias de producto pertenecen a pedidos en este momento'],
            ['id' => 7, 'name' => 'Bloqueado', 'description' => 'Se ha bloqueado el producto en el sistema']
        ]);
             */
        DB::table('product_categories')->insert([
            ['id' => 1, 'name' => 'Mochila', 'deep' => 0, 'root' => 0],
            ['id' => 2, 'name' => 'Primaria', 'deep' => 1, 'root' =>1],
            ['id' => 3, 'name' => 'Kinder', 'deep' => 1, 'root' =>1],
            ['id' => 4, 'name' => 'Lonchera', 'deep' => 1, 'root' =>1],
            ['id' => 5, 'name' => 'Lapicera doble', 'deep' => 1, 'root' =>1],
            ['id' => 6, 'name' => 'Lapicera triple', 'deep' => 1, 'root' =>1],
            ['id' => 7, 'name' => 'Messenger', 'deep' => 1, 'root' =>1],
            ['id' => 8, 'name' => 'Bolsa', 'deep' => 1, 'root' =>1],
            ['id' => 9, 'name' => 'Mariconera', 'deep' => 1, 'root' =>1],
            ['id' => 10, 'name' => 'Maleta', 'deep' => 1, 'root' =>1],
            ['id' => 11, 'name' => 'Cangurera', 'deep' => 1, 'root' =>1],
            ['id' => 12, 'name' => 'Mochila carro', 'deep' => 1, 'root' =>1],
            ['id' => 13, 'name' => 'Paquete mochila', 'deep' => 1, 'root' =>1],
            ['id' => 14, 'name' => 'Mini', 'deep' => 1, 'root' =>1],
            ['id' => 15, 'name' => 'Cartera y monedero', 'deep' => 1, 'root' =>1],
            ['id' => 16, 'name' => 'Cosmetiquera', 'deep' => 1, 'root' =>1],
            /* ['id' => 17, 'name' => 'Series', 'deep' => 0, 'root' =>0],
            ['id' => 18, 'name' => 'Serie luz normal', 'deep' => 1, 'root' =>17],
            ['id' => 19, 'name' => 'Serie led', 'deep' => 1, 'root' =>17],
            ['id' => 20, 'name' => 'Cascada normal', 'deep' => 1, 'root' =>17],
            ['id' => 21, 'name' => 'Cascada led', 'deep' => 1, 'root' =>17],
            ['id' => 22, 'name' => 'Serie en red', 'deep' => 1, 'root' =>17],
            ['id' => 23, 'name' => 'Serie en red led', 'deep' => 1, 'root' =>17],
            ['id' => 24, 'name' => 'Series de arroz', 'deep' => 1, 'root' =>17],
            ['id' => 25, 'name' => 'Serie magica y control led', 'deep' => 1, 'root' =>17],
            ['id' => 26, 'name' => 'Manguera luminosa', 'deep' => 1, 'root' =>17],
            ['id' => 27, 'name' => 'Manguera led', 'deep' => 1, 'root' =>17],
            ['id' => 28, 'name' => 'Serie en forma de canica', 'deep' => 1, 'root' =>17],
            ['id' => 29, 'name' => 'Figura de acrilico', 'deep' => 1, 'root' =>17],
            ['id' => 30, 'name' => 'Figura sencilla', 'deep' => 1, 'root' =>17],
            ['id' => 31, 'name' => 'Adorno inflable', 'deep' => 1, 'root' =>17],
            ['id' => 32, 'name' => 'Punta de arbol', 'deep' => 1, 'root' =>17],
            ['id' => 33, 'name' => 'Arbol fibra optica', 'deep' => 1, 'root' =>17],
            ['id' => 34, 'name' => 'Arbol navideño', 'deep' => 1, 'root' =>17],
            ['id' => 35, 'name' => 'Esferas', 'deep' => 1, 'root' =>17],
            ['id' => 36, 'name' => 'Paquete de oferta', 'deep' => 1, 'root' =>17], */
            ['id' => 37, 'name' => 'Juguete', 'deep' => 0, 'root' =>0],
            ['id' => 38, 'name' => 'Montable', 'deep' => 1, 'root' =>37],
            ['id' => 39, 'name' => 'Radio control', 'deep' => 1, 'root' =>37],
            ['id' => 40, 'name' => 'Carros', 'deep' => 1, 'root' =>37],
            ['id' => 41, 'name' => 'Pistolas', 'deep' => 1, 'root' =>37],
            ['id' => 42, 'name' => 'Drones', 'deep' => 1, 'root' =>37],
            ['id' => 43, 'name' => 'Autopista', 'deep' => 1, 'root' =>37],
            ['id' => 44, 'name' => 'Deportes', 'deep' => 1, 'root' =>37],
            ['id' => 45, 'name' => 'Instrumentos', 'deep' => 1, 'root' =>37],
            ['id' => 46, 'name' => 'Cocinas', 'deep' => 1, 'root' =>37],
            ['id' => 47, 'name' => 'Muñecas', 'deep' => 1, 'root' =>37],
            ['id' => 48, 'name' => 'Castillos', 'deep' => 1, 'root' =>37],
            ['id' => 49, 'name' => 'Belleza', 'deep' => 1, 'root' =>37],
            ['id' => 50, 'name' => 'Bisuteria', 'deep' => 1, 'root' =>37],
            ['id' => 51, 'name' => 'Carreolas', 'deep' => 1, 'root' =>37],
            ['id' => 52, 'name' => 'Hogar', 'deep' => 1, 'root' =>37],
            ['id' => 53, 'name' => 'Postres', 'deep' => 1, 'root' =>37],
            ['id' => 54, 'name' => 'Accesorios cocina', 'deep' => 1, 'root' =>37],
            ['id' => 55, 'name' => 'Patines', 'deep' => 1, 'root' =>37],
            ['id' => 56, 'name' => 'Juguete ECO', 'deep' => 1, 'root' =>37],
            ['id' => 57, 'name' => 'Varios', 'deep' => 1, 'root' =>37],
            ['id' => 58, 'name' => 'Papeleria', 'deep' => 0, 'root' =>0],
            ['id' => 59, 'name' => 'Plumas y marcadores', 'deep' => 1, 'root' =>58],
            ['id' => 60, 'name' => 'Engrapadoras', 'deep' => 1, 'root' =>58],
            ['id' => 61, 'name' => 'Accesorio para oficina', 'deep' => 1, 'root' =>58],
            ['id' => 62, 'name' => 'Post-it', 'deep' => 1, 'root' =>58],
            ['id' => 63, 'name' => 'Libretas', 'deep' => 1, 'root' =>58],
            ['id' => 64, 'name' => 'Didacticos', 'deep' => 1, 'root' =>58],
            ['id' => 65, 'name' => 'Accesorios escolares', 'deep' => 1, 'root' =>58],
            ['id' => 66, 'name' => 'Paraguas', 'deep' => 0, 'root' =>0],
            ['id' => 67, 'name' => 'Mini sencillo', 'deep' => 1, 'root' =>66],
            ['id' => 68, 'name' => 'Mini filtro solar', 'deep' => 1, 'root' =>66],
            ['id' => 69, 'name' => 'Mini doble tela', 'deep' => 1, 'root' =>66],
            ['id' => 70, 'name' => 'Mini automatico doble tela', 'deep' => 1, 'root' =>66],
            ['id' => 71, 'name' => 'Mini doble accion doble tela', 'deep' => 1, 'root' =>66],
            ['id' => 72, 'name' => 'Macana doble tela', 'deep' => 1, 'root' =>66],
            ['id' => 73, 'name' => 'Macana filtro solar', 'deep' => 1, 'root' =>66],
            ['id' => 74, 'name' => 'Paraguas 21 pulgadas', 'deep' => 1, 'root' =>66],
            ['id' => 75, 'name' => 'Paraguas de estrellas doble tela', 'deep' => 1, 'root' =>66],
            ['id' => 76, 'name' => 'Paraguas inrompible 23 pulgadas', 'deep' => 1, 'root' =>66],
            ['id' => 77, 'name' => 'Paraguas satinado 16V', 'deep' => 1, 'root' =>66],
            ['id' => 78, 'name' => 'Paraguas vogue 24V', 'deep' => 1, 'root' =>66],
            ['id' => 79, 'name' => 'Paraguas jumbo sencillo', 'deep' => 1, 'root' =>66],
            ['id' => 80, 'name' => 'Paraguas jumbo filtro solar', 'deep' => 1, 'root' =>66],
            ['id' => 81, 'name' => 'Paraguas jumbo doble tela', 'deep' => 1, 'root' =>66],
            ['id' => 82, 'name' => 'Paraguas jumbo 16V estrella', 'deep' => 1, 'root' =>66],
            ['id' => 83, 'name' => 'Mini jumbo doble tela', 'deep' => 1, 'root' =>66],
            ['id' => 84, 'name' => 'Mini jumbo 2 accion doble tela', 'deep' => 1, 'root' =>66],
            ['id' => 85, 'name' => 'Paraguas licencias niñ@', 'deep' => 1, 'root' =>66],
            ['id' => 86, 'name' => 'Paraguas de niño', 'deep' => 1, 'root' =>66],
            ['id' => 87, 'name' => 'Paraguas de niña', 'deep' => 1, 'root' =>66],
            ['id' => 88, 'name' => 'Paraguas de animalitos', 'deep' => 1, 'root' =>66],
            ['id' => 89, 'name' => 'Impermiable niño', 'deep' => 1, 'root' =>66],
            ['id' => 90, 'name' => 'Impermiable adulto', 'deep' => 1, 'root' =>66],
            ['id' => 91, 'name' => 'Impermiable completo', 'deep' => 1, 'root' =>66],
            ['id' => 92, 'name' => 'Paraguas de playa', 'deep' => 1, 'root' =>66],
            ['id' => 93, 'name' => 'Carpas', 'deep' => 1, 'root' =>66],
            ['id' => 94, 'name' => 'Accesorios', 'deep' => 0, 'root' =>0],
            ['id' => 95, 'name' => 'Belleza', 'deep' => 1, 'root' =>94],
            ['id' => 96, 'name' => 'Herramientas', 'deep' => 1, 'root' =>94],
            ['id' => 97, 'name' => 'Higiene', 'deep' => 1, 'root' =>94],
            ['id' => 98, 'name' => 'Hogar', 'deep' => 1, 'root' =>94],
            ['id' => 99, 'name' => 'Juegos', 'deep' => 1, 'root' =>94],
            ['id' => 100, 'name' => 'Ropa y accesorios', 'deep' => 1, 'root' =>94],
            ['id' => 101, 'name' => 'Tazas platos y tarros', 'deep' => 1, 'root' =>94],
            ['id' => 102, 'name' => 'Calculadora', 'deep' => 0, 'root' =>0],
            ['id' => 103, 'name' => 'Pequeña', 'deep' => 1, 'root' =>102],
            ['id' => 104, 'name' => 'Calculadora cientifica', 'deep' => 1, 'root' =>102],
            ['id' => 105, 'name' => 'Sumadora', 'deep' => 1, 'root' =>102],
            ['id' => 106, 'name' => 'Memoria', 'deep' => 1, 'root' =>102],
            ['id' => 107, 'name' => 'Reloj y portarretrato', 'deep' => 1, 'root' =>102],
            ['id' => 108, 'name' => 'Peluche', 'deep' => 0, 'root' =>0],
            ['id' => 109, 'name' => 'Transporte', 'deep' => 0, 'root' =>0],
            ['id' => 110, 'name' => 'Fletes', 'deep' => 1, 'root' =>109],
            ['id' => 111, 'name' => 'Electronicos', 'deep' => 0, 'root' =>0],
            ['id' => 112, 'name' => 'Audifonos', 'deep' => 1, 'root' =>111],
            ['id' => 113, 'name' => 'Bocina', 'deep' => 1, 'root' =>111],
            ['id' => 114, 'name' => 'Baterias', 'deep' => 1, 'root' =>111],
            ['id' => 115, 'name' => 'Difusor', 'deep' => 1, 'root' =>111],
            ['id' => 116, 'name' => 'Cables', 'deep' => 1, 'root' =>111],
            ['id' => 117, 'name' => 'Cargadores', 'deep' => 1, 'root' =>111],
            ['id' => 118, 'name' => 'Varios', 'deep' => 1, 'root' =>111],
            ['id' => 119, 'name' => 'Felpa', 'deep' => 0, 'root' =>0],
            ['id' => 120, 'name' => 'Miniatura', 'deep' => 1, 'root' =>119],
            ['id' => 121, 'name' => 'Figuras', 'deep' => 1, 'root' =>119],
            ['id' => 122, 'name' => 'Coronas y letreros', 'deep' => 1, 'root' =>119],
            ['id' => 123, 'name' => 'Bota', 'deep' => 1, 'root' =>119],
            ['id' => 124, 'name' => 'Pie de arbol', 'deep' => 1, 'root' =>119],
            ['id' => 125, 'name' => 'Cojin', 'deep' => 1, 'root' =>119],
            ['id' => 126, 'name' => 'Flor', 'deep' => 1, 'root' =>119],
            ['id' => 127, 'name' => 'Varios', 'deep' => 1, 'root' =>119],
            ['id' => 128, 'name' => 'Fig ECO', 'deep' => 1, 'root' =>119],
            ['id' => 129, 'name' => 'Bolsa ecologica', 'deep' => 1, 'root' =>94],
            ['id' => 130, 'name' => 'Navidad', 'deep' => 0, 'root' =>0],
            ['id' => 131, 'name' => 'Serie', 'deep' => 1, 'root' =>130],
            ['id' => 132, 'name' => 'Cascada', 'deep' => 2, 'root' =>131],
            ['id' => 133, 'name' => 'Lineal', 'deep' => 2, 'root' =>131],
            ['id' => 134, 'name' => 'Manguera', 'deep' => 2, 'root' =>131],
            ['id' => 135, 'name' => 'Maya', 'deep' => 2, 'root' =>131],
            ['id' => 136, 'name' => 'Red', 'deep' => 2, 'root' =>131],
            ['id' => 137, 'name' => 'Cortina', 'deep' => 2, 'root' =>131],
            ['id' => 138, 'name' => 'Arbol', 'deep' => 1, 'root' =>130],
            ['id' => 139, 'name' => 'Pie de arbol', 'deep' => 2, 'root' =>138],
            ['id' => 140, 'name' => 'Punta de arbol', 'deep' => 2, 'root' =>138],
            ['id' => 141, 'name' => 'Esfera', 'deep' => 2, 'root' =>138],
            ['id' => 142, 'name' => 'Adorno', 'deep' => 1, 'root' =>130],
            ['id' => 143, 'name' => 'Bolsa', 'deep' => 1, 'root' =>130],
            ['id' => 144, 'name' => 'Calendario', 'deep' => 1, 'root' =>130],
            ['id' => 145, 'name' => 'Camino', 'deep' => 1, 'root' =>130],
            ['id' => 146, 'name' => 'Canasta', 'deep' => 1, 'root' =>130],
            ['id' => 147, 'name' => 'Cojin', 'deep' => 1, 'root' =>130],
            ['id' => 148, 'name' => 'Colgante', 'deep' => 1, 'root' =>130],
            ['id' => 149, 'name' => 'Corbata', 'deep' => 1, 'root' =>130],
            ['id' => 150, 'name' => 'Diadema', 'deep' => 1, 'root' =>130],
            ['id' => 151, 'name' => 'Diamantina', 'deep' => 1, 'root' =>130],
            ['id' => 152, 'name' => 'Dulcero', 'deep' => 1, 'root' =>130],
            ['id' => 153, 'name' => 'Escarcha', 'deep' => 1, 'root' =>130],
            ['id' => 154, 'name' => 'Farol', 'deep' => 1, 'root' =>130],
            ['id' => 155, 'name' => 'Figura', 'deep' => 1, 'root' =>130],
            ['id' => 156, 'name' => 'Florero', 'deep' => 1, 'root' =>130],
            ['id' => 157, 'name' => 'Flores', 'deep' => 1, 'root' =>130],
            ['id' => 158, 'name' => 'Funda', 'deep' => 1, 'root' =>130],
            ['id' => 159, 'name' => 'Gorro', 'deep' => 1, 'root' =>130],
            ['id' => 160, 'name' => 'Inflable', 'deep' => 1, 'root' =>130],
            ['id' => 161, 'name' => 'Juego de baño', 'deep' => 1, 'root' =>130],
            ['id' => 162, 'name' => 'Letrero', 'deep' => 1, 'root' =>130],
            ['id' => 163, 'name' => 'Miniatura', 'deep' => 1, 'root' =>130],
            ['id' => 164, 'name' => 'Muñeco', 'deep' => 1, 'root' =>130],
            ['id' => 165, 'name' => 'Proyector', 'deep' => 1, 'root' =>130],
            ['id' => 166, 'name' => 'Reloj', 'deep' =>1, 'root' =>130],
            ['id' => 167, 'name' => 'Stickers', 'deep' => 1, 'root' =>130],
            ['id' => 168, 'name' => 'Tapete', 'deep' => 1, 'root' =>130],
            ['id' => 169, 'name' => 'Corona', 'deep' => 1, 'root' =>130],
            ['id' => 170, 'name' => 'Bota', 'deep' => 1, 'root' =>130],
            ['id' => 404, 'name' => 'Sin categoría', 'deep' => 0, 'root' =>0],
        ]);
                
        DB::table('product_status')->insert([
            ['id' => 1, 'name' => 'Disponible'],
            ['id' => 2, 'name' => 'Reservado'],
            ['id' => 3, 'name' => 'Agotado'],
            ['id' => 4, 'name' => 'Bloqueado']
        ]);

        DB::table('product_units')->insert([
            ['id' => 1, 'name' => 'Pieza', 'alias' => 'Pz', 'equivalence' => 0],
            ['id' => 2, 'name' => 'Docena', 'alias' => 'Doc', 'equivalence' => 12],
            ['id' => 3, 'name' => 'Caja', 'alias' => 'Caj', 'equivalence' => 0]
        ]);

        DB::table('providers')->insert([
            ['id' => '404', 'name' => 'Proveedor varios', 'alias' => 'Proveedor varios', 'adress' => json_encode(["calle" => '', "municipio" => '']), 'description' => '', 'phone' => '', 'email'=> '']
        ]);

        for($x=131; $x<139; $x++){
            DB::table('category_attributes')->insert([
                ['name' => 'Luces', '_category' => $x, "details" => json_encode([ "type" => "select", "options" => [6,8,9,10,12,16,18,20,22,24,25,28,30,31,32,36,40,48,50,54,56,60,64,66,72,74,80,88,100,108,120,128,138,140,150,160,200,240,264,276,300,320,348,400,480,500,600,700,800,900,1000, 'N/A']])],
                ['name' => 'Metros', '_category' => $x, "details" => json_encode([ "type" => "select", "options" => [2,3,5,7,10,12,15]])],
                ['name' => 'Mágica', '_category' => $x, "details" => json_encode([ "type" => "select", "options" => ["Si", "No"]])],
                ['name' => 'Tipo de foco', '_category' => $x, "details" => json_encode([ "type" => "select", "options" => ["Normal", "V8", "V9"]])],
                ['name' => 'Color del cable', '_category' => $x, "details" => json_encode([ "type" => "select", "options" => ["Azul", "Blanca", "Rojo", "Verde"]])]
            ]);
        }
            
        for($x=130; $x<170; $x++){
            DB::table('category_attributes')->insert([
                ['name' => 'Color', '_category' => $x, "details" => json_encode([ "type" => "select", "options" => ["Azul", "Blanca", "Rojo", "Verde"]])],
                ['name' => 'Musical', '_category' => $x, "details" => json_encode([ "type" => "select", "options" => ["Si", "No"]])],
                ['name' => 'Presentación', '_category' => $x, "details" => json_encode([ "type" => "select", "options" => ["Unicel", "Caja", "Individual", "Docena"]])],
            ]);
        }
    }
}