@extends('layouts.app')
@section('title', 'Tambah Postingan — SocioWatch Jateng')
@section('page-title', 'Tambah Postingan')
@section('breadcrumb')
<a href="{{ route('posts.index') }}" class="hover:text-indigo-600">Postingan</a>
<span class="breadcrumb-sep">/</span><span class="text-slate-600">Tambah</span>
@endsection
@section('content')
@include('posts._form', ['post' => null, 'action' => route('posts.store'), 'method' => 'POST'])
@endsection
