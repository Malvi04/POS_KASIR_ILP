@extends('errors.layout')

@section('title', 'Sesi Halaman Kedaluwarsa (419)')
@section('code', '419')
@section('icon', 'timer_off')
@section('icon_bg', 'bg-indigo-50 text-indigo-600')
@section('icon_border', 'border-indigo-200')
@section('badge_classes', 'bg-indigo-100 text-indigo-800 border border-indigo-200')
@section('heading', 'Sesi Halaman Berakhir')

@section('message')
    Token sesi keamanan Anda telah habis masa berlakunya karena tidak ada aktivitas dalam waktu tertentu. Silakan muat ulang halaman atau login kembali. Apabila kendala terus berulang, <strong>silahkan hubungi team IT</strong>.
@endsection
