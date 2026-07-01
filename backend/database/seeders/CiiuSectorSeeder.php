<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CiiuSectorSeeder extends Seeder
{
    public function run(): void
    {
        $sectors = [
            // Sección A
            ['ciiu_code' => 'A',   'name' => 'Agricultura, ganadería, caza, silvicultura y pesca'],
            ['ciiu_code' => 'A01', 'name' => 'Agricultura, ganadería, caza y actividades de servicios conexas'],
            ['ciiu_code' => 'A02', 'name' => 'Silvicultura y extracción de madera'],
            ['ciiu_code' => 'A03', 'name' => 'Pesca y acuicultura'],
            // Sección B
            ['ciiu_code' => 'B',   'name' => 'Explotación de minas y canteras'],
            ['ciiu_code' => 'B05', 'name' => 'Extracción de carbón de piedra y lignito'],
            ['ciiu_code' => 'B06', 'name' => 'Extracción de petróleo crudo y gas natural'],
            ['ciiu_code' => 'B08', 'name' => 'Explotación de otras minas y canteras'],
            // Sección C
            ['ciiu_code' => 'C',   'name' => 'Industrias manufactureras'],
            ['ciiu_code' => 'C10', 'name' => 'Elaboración de productos alimenticios'],
            ['ciiu_code' => 'C11', 'name' => 'Elaboración de bebidas'],
            ['ciiu_code' => 'C13', 'name' => 'Fabricación de productos textiles'],
            ['ciiu_code' => 'C17', 'name' => 'Fabricación de papel y de productos de papel'],
            ['ciiu_code' => 'C19', 'name' => 'Fabricación de coque y productos de la refinación del petróleo'],
            ['ciiu_code' => 'C20', 'name' => 'Fabricación de substancias y productos químicos'],
            ['ciiu_code' => 'C22', 'name' => 'Fabricación de productos de caucho y de plástico'],
            ['ciiu_code' => 'C23', 'name' => 'Fabricación de otros productos minerales no metálicos'],
            ['ciiu_code' => 'C24', 'name' => 'Fabricación de metales comunes'],
            ['ciiu_code' => 'C25', 'name' => 'Fabricación de productos elaborados de metal (excepto maquinaria y equipo)'],
            ['ciiu_code' => 'C28', 'name' => 'Fabricación de maquinaria y equipo n.c.p.'],
            // Sección D
            ['ciiu_code' => 'D',   'name' => 'Suministro de electricidad, gas, vapor y aire acondicionado'],
            ['ciiu_code' => 'D35', 'name' => 'Suministro de electricidad, gas, vapor y aire acondicionado'],
            // Sección E
            ['ciiu_code' => 'E',   'name' => 'Suministro de agua; alcantarillado y gestión de desechos'],
            ['ciiu_code' => 'E36', 'name' => 'Captación, tratamiento y distribución de agua'],
            ['ciiu_code' => 'E38', 'name' => 'Recolección, tratamiento y eliminación de desechos'],
            // Sección F
            ['ciiu_code' => 'F',   'name' => 'Construcción'],
            ['ciiu_code' => 'F41', 'name' => 'Construcción de edificios'],
            ['ciiu_code' => 'F42', 'name' => 'Obras de ingeniería civil'],
            // Sección G
            ['ciiu_code' => 'G',   'name' => 'Comercio al por mayor y al por menor; reparación de vehículos'],
            ['ciiu_code' => 'G45', 'name' => 'Comercio al por mayor y al por menor de vehículos automotores'],
            ['ciiu_code' => 'G46', 'name' => 'Comercio al por mayor (excepto de vehículos automotores)'],
            ['ciiu_code' => 'G47', 'name' => 'Comercio al por menor (excepto de vehículos automotores)'],
            // Sección H
            ['ciiu_code' => 'H',   'name' => 'Transporte y almacenamiento'],
            ['ciiu_code' => 'H49', 'name' => 'Transporte por vía terrestre; transporte por tuberías'],
            ['ciiu_code' => 'H50', 'name' => 'Transporte por vía acuática'],
            ['ciiu_code' => 'H51', 'name' => 'Transporte por vía aérea'],
            ['ciiu_code' => 'H52', 'name' => 'Almacenamiento y actividades de apoyo al transporte'],
            // Sección I
            ['ciiu_code' => 'I',   'name' => 'Alojamiento y servicios de comida'],
            ['ciiu_code' => 'I55', 'name' => 'Alojamiento'],
            ['ciiu_code' => 'I56', 'name' => 'Actividades de servicio de comidas y bebidas'],
            // Sección J
            ['ciiu_code' => 'J',   'name' => 'Información y comunicaciones'],
            ['ciiu_code' => 'J58', 'name' => 'Actividades de edición'],
            ['ciiu_code' => 'J61', 'name' => 'Telecomunicaciones'],
            ['ciiu_code' => 'J62', 'name' => 'Programación informática, consultoría y actividades conexas'],
            // Sección K
            ['ciiu_code' => 'K',   'name' => 'Actividades financieras y de seguros'],
            ['ciiu_code' => 'K64', 'name' => 'Actividades de servicios financieros (excepto seguros)'],
            // Sección L
            ['ciiu_code' => 'L',   'name' => 'Actividades inmobiliarias'],
            ['ciiu_code' => 'L68', 'name' => 'Actividades inmobiliarias'],
            // Sección M
            ['ciiu_code' => 'M',   'name' => 'Actividades profesionales, científicas y técnicas'],
            ['ciiu_code' => 'M69', 'name' => 'Actividades jurídicas y de contabilidad'],
            ['ciiu_code' => 'M70', 'name' => 'Actividades de oficinas principales; actividades de consultoría de gestión'],
            ['ciiu_code' => 'M71', 'name' => 'Actividades de arquitectura e ingeniería; ensayos y análisis técnicos'],
            ['ciiu_code' => 'M72', 'name' => 'Investigación científica y desarrollo'],
            ['ciiu_code' => 'M73', 'name' => 'Publicidad y estudios de mercado'],
            // Sección N
            ['ciiu_code' => 'N',   'name' => 'Actividades de servicios administrativos y de apoyo'],
            // Sección O
            ['ciiu_code' => 'O',   'name' => 'Administración pública y defensa; seguridad social'],
            // Sección P
            ['ciiu_code' => 'P',   'name' => 'Educación'],
            // Sección Q
            ['ciiu_code' => 'Q',   'name' => 'Actividades de atención de la salud humana y de asistencia social'],
            ['ciiu_code' => 'Q86', 'name' => 'Actividades de atención de la salud humana'],
            // Sección R
            ['ciiu_code' => 'R',   'name' => 'Actividades artísticas, de entretenimiento y recreativas'],
            // Sección S
            ['ciiu_code' => 'S',   'name' => 'Otras actividades de servicios'],
        ];

        foreach ($sectors as $s) {
            // Upsert por nombre (constraint unique en name) — actualiza ciiu_code e is_ciiu si ya existe
            $updated = DB::table('company_sectors')
                ->where('name', $s['name'])
                ->update(['ciiu_code' => $s['ciiu_code'], 'is_ciiu' => true, 'updated_at' => now()]);

            if (!$updated && DB::table('company_sectors')->where('ciiu_code', $s['ciiu_code'])->doesntExist()) {
                DB::table('company_sectors')->insert([
                    'name'       => $s['name'],
                    'ciiu_code'  => $s['ciiu_code'],
                    'is_ciiu'    => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            }
        }
    }
}
