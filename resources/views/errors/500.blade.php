@extends('errors.layout')

@section('title', 'Terjadi Kesalahan pada Server (500)')
@section('code', '500')
@section('icon', 'error')
@section('icon_bg', 'bg-rose-50 text-rose-600')
@section('icon_border', 'border-rose-200')
@section('badge_classes', 'bg-rose-100 text-rose-800 border border-rose-200')
@section('heading', 'Terjadi Kesalahan pada Sistem')

@section('message')
    Mohon maaf, sistem POS mengalami gangguan saat memproses permintaan Anda. Data transaksi tersimpan aman. Mohon muat ulang halaman atau <strong>silahkan hubungi team IT</strong> agar masalah dapat segera diatasi.
@endsection
