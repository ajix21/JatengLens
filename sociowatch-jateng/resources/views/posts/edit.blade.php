@extends('layouts.app')
@section('title', 'Edit Postingan — SocioWatch Jateng')
@section('page-title', 'Edit Postingan')
@section('breadcrumb')
<a href="{{ route('posts.index') }}" class="hover:text-indigo-600">Postingan</a>
<span class="breadcrumb-sep">/</span>
<a href="{{ route('posts.show', $post) }}" class="hover:text-indigo-600">Detail</a>
<span class="breadcrumb-sep">/</span><span class="text-slate-600">Edit</span>
@endsection
@section('content')
@include('posts._form', ['post' => $post, 'action' => route('posts.update', $post), 'method' => 'PUT'])
@endsection
