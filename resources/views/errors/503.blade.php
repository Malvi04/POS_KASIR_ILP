@extends('errors.layout')

@section('title', 'Layanan Sedang Pemeliharaan (503)')
@section('code', '503')
@section('icon', 'construction')
@section('icon_bg', 'bg-blue-50 text-blue-600')
@section('icon_border', 'border-blue-200')
@section('badge_classes', 'bg-blue-100 text-blue-800 border border-blue-200')
@section('heading', 'Layanan Sedang Dalam Pemeliharaan')

@section('message')
    Sistem POS Kasir PT Indo Lampung Perkasa sedang dalam proses sinkronisasi atau pemeliharaan berkala untuk peningkatan stabilitas. Silakan coba kembali beberapa saat lagi. Jika ada kebutuhan mendesak, <strong>silahkan hubungi team IT</strong>.
@endsection
