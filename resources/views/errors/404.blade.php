@extends('errors.layout')

@section('title', 'Halaman Tidak Ditemukan (404)')
@section('code', '404')
@section('icon', 'search_off')
@section('icon_bg', 'bg-amber-50 text-amber-600')
@section('icon_border', 'border-amber-200')
@section('badge_classes', 'bg-amber-100 text-amber-800 border border-amber-200')
@section('heading', 'Halaman Tidak Ditemukan')

@section('message')
    Halaman yang Anda tuju tidak ditemukan atau tautan menu belum tersedia. Pastikan URL atau menu yang dipilih sudah benar. Jika halaman ini seharusnya aktif dan muncul, <strong>silahkan hubungi team IT</strong>.
@endsection
