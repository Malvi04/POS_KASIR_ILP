<?php

use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->manager = User::factory()->create([
        'name' => 'Manager Test',
        'email' => 'manager.test@ilp.co.id',
        'role' => 'MANAGER',
        'is_active' => true,
    ]);
});

test('manager can get leave requests filtered by hari / daily', function () {
    $today = Carbon::today();
    $yesterday = Carbon::yesterday();

    $leaveToday = LeaveRequest::create([
        'staff_name' => 'Budi Hari',
        'type' => 'IZIN',
        'date_range' => $today->toDateString(),
        'reason' => 'Keperluan keluarga',
        'status' => 'MENUNGGU',
    ]);
    // Force created_at to today
    $leaveToday->created_at = $today;
    $leaveToday->save();

    $leaveYesterday = LeaveRequest::create([
        'staff_name' => 'Siti Kemarin',
        'type' => 'CUTI',
        'date_range' => $yesterday->toDateString(),
        'reason' => 'Cuti tahunan',
        'status' => 'DISETUJUI',
    ]);
    $leaveYesterday->created_at = $yesterday;
    $leaveYesterday->save();

    $response = $this->actingAs($this->manager)
        ->getJson('/api/attendance/leaves?period=hari&date=' . $today->toDateString());

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('summary.total', 1)
        ->assertJsonFragment(['staffName' => 'Budi Hari'])
        ->assertJsonMissing(['staffName' => 'Siti Kemarin']);
});

test('manager can get leave requests filtered by minggu / weekly', function () {
    $thisWeek = Carbon::now()->startOfWeek()->addDays(2);
    $lastMonth = Carbon::now()->subMonths(2);

    $leaveWeek = LeaveRequest::create([
        'staff_name' => 'Andi Minggu',
        'type' => 'SAKIT',
        'date_range' => $thisWeek->toDateString(),
        'reason' => 'Demam flu',
        'status' => 'DISETUJUI',
    ]);
    $leaveWeek->created_at = $thisWeek;
    $leaveWeek->save();

    $leaveOld = LeaveRequest::create([
        'staff_name' => 'Rina Lama',
        'type' => 'IZIN',
        'date_range' => $lastMonth->toDateString(),
        'reason' => 'Acara keluarga',
        'status' => 'MENUNGGU',
    ]);
    $leaveOld->created_at = $lastMonth;
    $leaveOld->save();

    $response = $this->actingAs($this->manager)
        ->getJson('/api/attendance/leaves?period=minggu&date=' . $thisWeek->toDateString());

    $response->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonFragment(['staffName' => 'Andi Minggu'])
        ->assertJsonMissing(['staffName' => 'Rina Lama']);
});

test('manager can get leave requests filtered by bulan / monthly and tahun / yearly', function () {
    $targetDate = Carbon::create(2026, 5, 15);
    $otherDate = Carbon::create(2026, 8, 20);

    $leaveMay = LeaveRequest::create([
        'staff_name' => 'Joko Mei',
        'type' => 'CUTI',
        'date_range' => '15-18 Mei 2026',
        'reason' => 'Mudik',
        'status' => 'DISETUJUI',
    ]);
    $leaveMay->created_at = $targetDate;
    $leaveMay->save();

    $leaveAug = LeaveRequest::create([
        'staff_name' => 'Dewi Agustus',
        'type' => 'IZIN',
        'date_range' => '20 Agustus 2026',
        'reason' => 'Menikah',
        'status' => 'MENUNGGU',
    ]);
    $leaveAug->created_at = $otherDate;
    $leaveAug->save();

    // Test monthly
    $responseMonth = $this->actingAs($this->manager)
        ->getJson('/api/attendance/leaves?period=bulan&month=2026-05');

    $responseMonth->assertStatus(200)
        ->assertJsonFragment(['staffName' => 'Joko Mei'])
        ->assertJsonMissing(['staffName' => 'Dewi Agustus']);

    // Test yearly (both should be present)
    $responseYear = $this->actingAs($this->manager)
        ->getJson('/api/attendance/leaves?period=tahun&year=2026');

    $responseYear->assertStatus(200)
        ->assertJsonFragment(['staffName' => 'Joko Mei'])
        ->assertJsonFragment(['staffName' => 'Dewi Agustus']);
});

test('manager can export leave requests to excel (.xlsx)', function () {
    LeaveRequest::create([
        'staff_name' => 'Fajar Excel',
        'type' => 'IZIN',
        'date_range' => '2026-11-21',
        'reason' => 'Ujian sertifikasi',
        'status' => 'DISETUJUI',
        'approved_by' => 'SPV Gudang',
    ]);

    $response = $this->actingAs($this->manager)
        ->get('/api/attendance/leaves/export-excel?period=all');

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment; filename=');
    expect($response->headers->get('Content-Disposition'))->toContain('.xlsx');
});

test('manager can filter leave requests by status, type, and keyword q', function () {
    LeaveRequest::create([
        'staff_name' => 'Ahmad Kasir 1',
        'type' => 'IZIN',
        'date_range' => '2026-11-20',
        'reason' => 'Urusan KTP',
        'status' => 'DISETUJUI',
    ]);

    LeaveRequest::create([
        'staff_name' => 'Bambang Kasir 2',
        'type' => 'CUTI',
        'date_range' => '2026-11-21',
        'reason' => 'Cuti menikah',
        'status' => 'MENUNGGU',
    ]);

    // Test filter status
    $resStatus = $this->actingAs($this->manager)
        ->getJson('/api/attendance/leaves?period=all&status=MENUNGGU');
    $resStatus->assertStatus(200)
        ->assertJsonFragment(['staffName' => 'Bambang Kasir 2'])
        ->assertJsonMissing(['staffName' => 'Ahmad Kasir 1']);

    // Test filter type
    $resType = $this->actingAs($this->manager)
        ->getJson('/api/attendance/leaves?period=all&type=IZIN');
    $resType->assertStatus(200)
        ->assertJsonFragment(['staffName' => 'Ahmad Kasir 1'])
        ->assertJsonMissing(['staffName' => 'Bambang Kasir 2']);

    // Test search keyword q
    $resSearch = $this->actingAs($this->manager)
        ->getJson('/api/attendance/leaves?period=all&q=menikah');
    $resSearch->assertStatus(200)
        ->assertJsonFragment(['staffName' => 'Bambang Kasir 2'])
        ->assertJsonMissing(['staffName' => 'Ahmad Kasir 1']);
});
