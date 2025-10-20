<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;

class OrganizationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create organization -> UCSC
        Organization::create([
            'name' => 'University of Colombo School of Computing',
            'description' => 'UCSC offers 5 Undergraduate degree programmes, 6 Masters degree programmes, 2 Research degree programmes and 1 External degree programme, plus a talented team of staff to help find what is right for you. Whatever your passion, we will put you on the path to success.',
            'status' => 'active',
            'contact_email' => 'info[at]ucsc.cmb.ac.lk',
            'phone_number' => '+94 -11- 2581245/ 7',
            'website' => 'https://www.ucsc.cmb.ac.lk',
            'address' => 'University of Colombo School of Computing, UCSC Building Complex, 35, Reid Avenue, Colombo 07, Sri Lanka',
        ]);
    }
}
