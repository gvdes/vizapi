<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InitSeeder extends Seeder{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(){
        DB::table('workpoints_types')->insert([
            ['id'=> 1, 'name'=> 'CEDIS'],
            ['id'=> 2, 'name'=> 'Sucursal'],
            ['id' =>3, 'name' => 'Cluster']
        ]);

        DB::table('workpoints')->insert([
            ['id' => 1, 'name' => 'CEDIS San Pablo', 'alias' => 'CEDISSAP', '_type' => 1],
            ['id' => 2, 'name' => 'CEDIS Pantaco', 'alias' => 'CEDISPAN', '_type' => 1],
            ['id' => 3, 'name' => 'San Pablo Uno', 'alias' => 'SP1', '_type' => 2],
            ['id' => 4, 'name' => 'San Pablo Dos', 'alias' => 'SP2', '_type' => 2],
            ['id' => 5, 'name' => 'Correo Uno', 'alias' => 'CO1', '_type' => 2],
            ['id' => 6, 'name' => 'Correo Dos', 'alias' => 'CO2', '_type' => 2],
            ['id' => 7, 'name' => 'Apartado Uno', 'alias' => 'AP1', '_type' => 2],
            ['id' => 8, 'name' => 'Apartado Dos', 'alias' => 'AP2', '_type' => 2],
            ['id' => 9, 'name' => 'Ramon Corona Uno', 'alias' => 'RC1', '_type' => 2],
            ['id' => 10, 'name' => 'Ramon Corona Dos', 'alias' => 'RC2', '_type' => 2],
            ['id' => 11, 'name' => 'Brasil Uno', 'alias' => 'BRA1', '_type' => 2],
            ['id' => 12, 'name' => 'Brasil Dos', 'alias' => 'BRA2', '_type' => 2],
            ['id' => 13, 'name' => 'Bolivia', 'alias' => 'BOL', '_type' => 2],
            ['id' => 404, 'name' => 'Clouster', 'alias' => 'VIZ', '_type' => 3],
        ]);

        DB::table('account_status')->insert([
            ['id' => 1, 'name' => 'Cuenta nueva', 'description' => 'Sin inicio de sesión previa'],
            ['id' => 2, 'name' => 'Cuenta activa', 'description' => 'Cuenta activa'],
            ['id' => 3, 'name' => 'Cuenta disponible', 'description' => 'Cuenta sin sesiones activas'],
            ['id' => 4, 'name' => 'Cuenta Archivada/Bloqueada', 'description' => 'Cuenta archivada/bloqueada por administrador']
        ]);

        DB::table('account_log_types')->insert([
            ['id'=> 1, 'name'=> 'Creación de la cuenta'],
            ['id'=> 2, 'name'=> 'Actualización de datos'],
            ['id'=> 3, 'name'=> 'Cambio de contraseña'],
            ['id'=> 4, 'name'=> 'Inicio de sesión'],
            ['id'=> 5, 'name'=> 'Sesión cerrada'],
            ['id'=> 6, 'name'=> 'Cambio de status'],
            ['id'=> 7, 'name'=> 'Se ha otorgado acceso a una nueva sucursal'],
            ['id'=> 8, 'name'=> 'Se ha quitado acceso a una nueva sucursal'],
            ['id'=> 9, 'name'=> 'Se ha cambiado el rol'],
            ['id'=> 10, 'name'=> 'Cambio de permisos'],
        ]);

        DB::table('roles')->insert([
            ['id'=> 1, 'name'=> 'root'],
            ['id'=> 2, 'name'=> 'Administrador general'],
            ['id'=> 3, 'name'=> 'Administrador sucursal'],
            ['id'=> 4, 'name'=> 'Vendedor'],
            ['id'=> 5, 'name'=> 'Cajero'],
            ['id'=> 6, 'name'=> 'Administrador de almacenes'],
            ['id'=> 7, 'name'=> 'Bodeguero']
        ]);

        DB::table('modules_app')->insert([
            ['id'=> 1, 'name'=> 'Usuarios', 'deep'=> 0, 'root'=> 0, 'path'=> 'usuarios'],
            ['id'=> 2, 'name'=> 'Gestión de perfil', 'deep'=> 1, 'root'=> 1, 'path'=> 'perfil'],
            ['id'=> 3, 'name'=> 'Gestión de usuarios', 'deep'=> 1, 'root'=> 1, 'path'=> 'perfiles'],
            ['id'=> 4, 'name'=> 'Preventa', 'deep'=> 0, 'root'=> 0, 'path'=> 'preventa'],
            ['id'=> 5, 'name'=> 'Preventa - Pedidos', 'deep'=> 1, 'root'=> 4, 'path'=> '/pedidos'],
            ['id'=> 6, 'name'=> 'Preventa - Validación', 'deep'=> 1, 'root'=> 4, 'path'=> 'validate'],
            ['id'=> 7, 'name'=> 'Preventa - Bodega', 'deep'=> 1, 'root'=> 4, 'path'=> 'bodega'],
            ['id'=> 8, 'name'=> 'Preventa - Bodega/Salida', 'deep'=> 1, 'root'=> 4, 'path'=> 'bodega/salida'],
            ['id'=> 9, 'name'=> 'Preventa - Caja', 'deep'=> 1, 'root'=> 4, 'path'=> 'caja'],
            ['id'=> 10, 'name'=> 'Preventa - Caja/Salida', 'deep'=> 1, 'root'=> 4, 'path'=> 'caja/salida'],
            ['id'=> 11, 'name'=> 'Preventa - Configuracion', 'deep'=> 1, 'root'=> 4, 'path'=> 'configuracion'],
            ['id'=> 12, 'name'=> 'Preventa - Reportes', 'deep'=> 1, 'root'=> 4, 'path'=> 'reportes'],
            ['id'=> 13, 'name'=> 'Almacenes', 'deep'=> 0, 'root'=> 0, 'path'=> 'almacenes'],
            ['id'=> 14, 'name'=> 'Contador', 'deep'=> 1, 'root'=> 13, 'path'=> 'contador'],
            ['id'=> 15, 'name'=> 'Ubicador', 'deep'=> 1, 'root'=> 13, 'path'=> 'ubicador'],
            ['id'=> 16, 'name'=> 'Mínimos y máximos', 'deep'=> 1, 'root'=> 13, 'path'=> 'minymax'],
            /* ['id'=> 4, 'name'=> 'Pedidos CEDIS', 'deep'=> 0 , 'root'=> 0],
            ['id'=> 5, 'name'=> 'Generación pedidos CEDIS', 'deep'=> 1 , 'root'=> 4],
            ['id'=> 6, 'name'=> 'Dashboard pedidos CEDIS', 'deep'=> 1 , 'root'=> 4],
            ['id'=> 7, 'name'=> 'Etiquetas', 'deep'=> 0, 'root'=> 0] */
        ]);

        DB::table('permissions')->insert([
            ['id'=> 1, '_module'=> 2, 'name'=> 'Modificación de datos personales'],
            ['id'=> 2, '_module'=> 3, 'name'=> 'Creación de usuarios'],
            ['id'=> 3, '_module'=> 3, 'name'=> 'Visualización de todos los usuarios'],
            ['id'=> 4, '_module'=> 3, 'name'=> 'Visualización de usuarios'],
            ['id'=> 5, '_module'=> 3, 'name'=> 'Visualización detallada de usuario'],
            ['id'=> 6, '_module'=> 3, 'name'=> 'Asignación de permisos'],
            ['id'=> 7, '_module'=> 3, 'name'=> 'Asignación de nuevo punto de trabajo'],
            ['id'=> 8, '_module'=> 3, 'name'=> 'Actualización de datos de la cuenta'],
            ['id'=> 9, '_module'=> 3, 'name'=> 'Cambio de contraseña de la cuenta'],
            ['id'=> 10, '_module'=> 3, 'name'=> 'Cambio de status de cuenta'],
            ['id'=> 11, '_module'=> 3, 'name'=> 'Bloquear o archivar una cuenta'],
            ['id'=> 12, '_module'=> 5, 'name'=> 'Acceso'],
            ['id'=> 13, '_module'=> 6, 'name'=> 'Acceso'],
            ['id'=> 14, '_module'=> 7, 'name'=> 'Acceso'],
            ['id'=> 15, '_module'=> 8, 'name'=> 'Acceso'],
            ['id'=> 16, '_module'=> 9, 'name'=> 'Acceso'],
            ['id'=> 17, '_module'=> 10, 'name'=> 'Acceso'],
            ['id'=> 18, '_module'=> 11, 'name'=> 'Acceso'],
            ['id'=> 19, '_module'=> 12, 'name'=> 'Acceso'],
            ['id'=> 21, '_module'=> 13, 'name'=> 'Acceso'],
            ['id'=> 22, '_module'=> 14, 'name'=> 'Acceso'],
            ['id'=> 23, '_module'=> 15, 'name'=> 'Acceso'],
            ['id'=> 24, '_module'=> 15, 'name'=> 'Iniciar conteo'],
            ['id'=> 25, '_module'=> 15, 'name'=> 'Finalizar conteo'],
            ['id'=> 26, '_module'=> 16, 'name'=> 'Acceso'],
            /* ['id'=> 11, '_module'=> 4, 'name'=> 'Creación de pedido'],
            ['id'=> 12, '_module'=> 4, 'name'=> 'Creación de pedido autostock'],
            ['id'=> 13, '_module'=> 4, 'name'=> 'Visualización genérica de pedidos'],
            ['id'=> 14, '_module'=> 4, 'name'=> 'Visualización detallada de pedido'],
            ['id'=> 15, '_module'=> 4, 'name'=> 'Cambio de preferencias de pedidos autostock'],
            ['id'=> 16, '_module'=> 4, 'name'=> 'Modificación de pedidos antes de comenzar a ser surtido'],
            ['id'=> 17, '_module'=> 4, 'name'=> 'Poner en pausa pedido antes de ser surtido'],
            ['id'=> 18, '_module'=> 4, 'name'=> 'Cancelación de pedido'],
            ['id'=> 19, '_module'=> 4, 'name'=> 'Cancelación de pedido autostock'],
            ['id'=> 20, '_module'=> 4, 'name'=> 'Impresión de ticket sucursal'],
            ['id'=> 21, '_module'=> 4, 'name'=> 'Re-impresión de ticket sucursal'],
            ['id'=> 22, '_module'=> 5, 'name'=> 'Cambio de status de pedido'],
            ['id'=> 23, '_module'=> 5, 'name'=> 'Impresión de ticket bodega'],
            ['id'=> 24, '_module'=> 5, 'name'=> 'Re-impresión de ticket bodega'],
            ['id'=> 25, '_module'=> 5, 'name'=> 'Realizar labor de recolección'],
            ['id'=> 26, '_module'=> 5, 'name'=> 'Cambio de status de pedidos'],
            ['id'=> 27, '_module'=> 5, 'name'=> 'Registrar salida de mercancia'],
            ['id'=> 28, '_module'=> 5, 'name'=> 'Cancelar pedido'] */
        ]);
        $permissions = DB::table('permissions')->get();
        $arr_to_insert =  $permissions->map(function( $permission){
            return ['_rol'=> 1, '_permission'=> $permission->id];
        })->toArray();
        DB::table('rol_permission_default')->insert($arr_to_insert);
    }
}
