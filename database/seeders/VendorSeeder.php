<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VendorSeeder extends Seeder
{
  public function run(): void
  {
    // Seed John Doe
    $this->seedVendorWithBusinesses(
      'johndoe',
      'John Doe',
      '09171234567',
      [
        [
          'name' => "John's Approved Cafe",
          'desc' => 'A cozy café offering artisan coffee and pastries in Concepcion Pequeña.',
          'type' => 'Cafe',
          'location' => 'Concepcion Pequeña',
          'id_type' => 'Driver License',
          'id_no' => 'DL123456',
          'permit_no' => 'BP123456',
          'bir_no' => 'BIR123456',
          'duration' => '5 years',
          'lat' => 13.61441715,
          'lng' => 123.20315781103,
          'status' => 'Approved',
          'remarks' => null,
          'hours' => ['08:00', '20:00'],
        ],
        [
          'name' => "John's Pending Bakery",
          'desc' => 'A neighborhood bakery specializing in fresh breads and local pastries.',
          'type' => 'Bakery',
          'location' => 'Cabusao, Camarines Sur, Philippines',
          'id_type' => 'Passport',
          'id_no' => 'P1234567',
          'permit_no' => 'BP654321',
          'bir_no' => 'BIR654321',
          'duration' => '2 years',
          'lat' => 13.7304,
          'lng' => 123.1042,
          'status' => 'Pending',
          'remarks' => 'Documents under review',
          'hours' => ['07:00', '18:00'],
        ],
      ]
    );

    // Seed Jane Jane
    $this->seedVendorWithBusinesses(
      'janejane',
      'Jane Jane',
      '09998765432',
      [
        [
          'name' => "Jane's Bloom & Blossoms",
          'desc' => 'A floral shop specializing in fresh bouquets and creative arrangements.',
          'type' => 'Florist',
          'location' => 'Naga City, Camarines Sur',
          'id_type' => 'SSS ID',
          'id_no' => 'SSS123456',
          'permit_no' => 'BP987654',
          'bir_no' => 'BIR987654',
          'duration' => '3 years',
          'lat' => 13.6234,
          'lng' => 123.1967,
          'status' => 'Approved',
          'remarks' => null,
          'hours' => ['09:00', '18:00'],
        ],
        [
          'name' => "Jane's Dessert Corner",
          'desc' => 'A small sweet spot for homemade cakes, cookies, and fruity delights.',
          'type' => 'Dessert Shop',
          'location' => 'Concepcion Grande, Naga City',
          'id_type' => 'PhilHealth ID',
          'id_no' => 'PH12345678',
          'permit_no' => 'BP112233',
          'bir_no' => 'BIR112233',
          'duration' => '1 year',
          'lat' => 13.6198,
          'lng' => 123.1922,
          'status' => 'Pending',
          'remarks' => 'Awaiting document verification',
          'hours' => ['10:00', '20:00'],
        ],
      ]
    );
  }

  private function seedVendorWithBusinesses(string $username, string $fullname, string $phone, array $businesses): void
  {
    // Look up user by username
    $userId = DB::table('users')->where('username', $username)->value('user_id');

    if (!$userId) {
      $this->command->error("User {$username} not found. Make sure UserSeeder has run!");
      return;
    }

    // Create vendor record
    $vendorId = DB::table('vendors')->insertGetId([
      'user_id' => $userId,
      'fullname' => $fullname,
      'phone_number' => $phone,
      'registration_status' => 'Approved',
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    foreach ($businesses as $biz) {
      $businessId = DB::table('business_details')->insertGetId([
        'vendor_id' => $vendorId,
        'business_image' => 'https://dishorastorage.blob.core.windows.net/images/business_logo_sample.png',
        'business_name' => $biz['name'],
        'business_description' => $biz['desc'],
        'business_type' => $biz['type'],
        'business_location' => $biz['location'],
        'valid_id_type' => $biz['id_type'],
        'valid_id_no' => $biz['id_no'],
        'business_permit_no' => $biz['permit_no'],
        'bir_reg_no' => $biz['bir_no'],
        'business_permit_file' => null,
        'valid_id_file' => null,
        'bir_reg_file' => null,
        'mayor_permit_file' => null,
        'business_duration' => $biz['duration'],
        'latitude' => $biz['lat'],
        'longitude' => $biz['lng'],
        'verification_status' => $biz['status'],
        'remarks' => $biz['remarks'],
        'created_at' => now(),
        'updated_at' => now(),
      ]);

      // Seed opening hours
      $this->seedOpeningHours($businessId, $biz['hours'][0], $biz['hours'][1]);
    }
  }

  private function seedOpeningHours(int $businessId, string $opensAt, string $closesAt): void
  {
    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    foreach ($days as $day) {
      DB::table('business_opening_hours')->insert([
        'business_id' => $businessId,
        'day_of_week' => $day,
        'opens_at' => $opensAt,
        'closes_at' => $closesAt,
        'is_closed' => false,
        'created_at' => now(),
        'updated_at' => now(),
      ]);
    }

    // Sunday closed
    DB::table('business_opening_hours')->insert([
      'business_id' => $businessId,
      'day_of_week' => 'Sunday',
      'opens_at' => null,
      'closes_at' => null,
      'is_closed' => true,
      'created_at' => now(),
      'updated_at' => now(),
    ]);
  }
}
