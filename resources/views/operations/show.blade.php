@extends('layouts.app')

@section('content')
<div class="card">
    <div class="card-body">
        <h5 class="card-title">Operation Details</h5>
        <ul class="list-group list-group-flush">
            <li class="list-group-item"><strong>Amount:</strong> <span @class([ 'text-danger'=> $operation->type === 0,
                    'text-success' => $operation->type === 1,
                    ])>{{ $operation->amount_text }}</span></li>
            <li class="list-group-item"><strong>Type:</strong> {{ $operation->type_name }}</li>
            <li class="list-group-item"><strong>Bill:</strong> {{ $operation->bill->name }}</li>
            <li class="list-group-item"><strong>Category:</strong> {{ $operation->category->name }}</li>
            <li class="list-group-item"><strong>Currency:</strong> {{ $operation->currency->name }}</li>
            <li class="list-group-item"><strong>Place:</strong> {{ $operation->place->name }}</li>
            <li class="list-group-item"><strong>User:</strong> {{ $operation->user->name }}</li>
            <li class="list-group-item"><strong>Notes:</strong> {{ $operation->notes }}</li>
            <li class="list-group-item"><strong>Date:</strong> {{ $operation->date_formatted }}</li>
        </ul>
    </div>
    <div class="card-footer">
        <a href="{{ route('operations.edit', $operation) }}" class="card-link">Редактировать</a>
        <a href="#" class="card-link link-danger"
            onclick="event.preventDefault(); if (confirm('Are you sure you want to delete?')) { document.getElementById('delete-form').submit(); }">Удалить</a>
        <form id="delete-form" action="{{ route('operations.destroy', $operation) }}" method="POST"
            style="display: none;">
            @csrf
            @method('DELETE')
        </form>
    </div>
</div>
@endsection