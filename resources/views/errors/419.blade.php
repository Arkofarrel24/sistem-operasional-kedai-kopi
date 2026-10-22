@extends('errors.layout')

@section('code', '419')
@section('title', 'Sesi kedaluwarsa')
@section('message', 'Halaman ini terlalu lama dibiarkan terbuka sehingga sesinya berakhir. Muat ulang halaman, lalu ulangi langkah terakhir Anda.')
@section('primary')
    {{-- Re-open the previous page with a plain GET (a reload would resend the expired form). --}}
    <a class="btn btn-primary" href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}">Buka ulang halaman</a>
@endsection
