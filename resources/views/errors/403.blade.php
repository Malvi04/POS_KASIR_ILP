@extends('errors.layout')

@section('title', 'Akses Ditolak (403)')
@section('code', '403')
@section('icon', 'lock')
@section('icon_bg', 'bg-orange-50 text-orange-600')
@section('icon_border', 'border-orange-200')
@section('badge_classes', 'bg-orange-100 text-orange-800 border border-orange-200')
@section('heading', 'Akses Ditolak / Dibatasi')

@section('message')
    Akun atau sesi Anda tidak memiliki hak akses untuk membuka halaman atau fitur ini. Jika Anda memerlukan izin akses untuk operasional tugas Anda, <strong>silahkan hubungi team IT</strong> atau manajer cabang Anda.
@endsection
