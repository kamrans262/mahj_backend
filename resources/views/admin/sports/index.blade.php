@extends('admin.layout')

@section('title', 'Sports')
@section('subtitle', 'Manage the sports available in the Create Match dropdown.')

@section('content')
<div class="grid2">
    <div class="card">
        <div class="card-pad">
            <div class="card-head">
                <div>
                    <div class="card-title">Add sport</div>
                    <div class="card-copy">New active sports become available in the mobile app automatically.</div>
                </div>
            </div>
            <form method="post" action="{{ route('admin.sports.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="span2 field">
                        <label>Sport name</label>
                        <input class="input" name="name" value="{{ old('name') }}" placeholder="e.g. Racquetball" required>
                    </div>
                    <div class="field">
                        <label>Icon</label>
                        <select class="select" name="icon_key" required>
                            @foreach($iconKeys as $iconKey)
                                <option value="{{ $iconKey }}" @selected(old('icon_key') === $iconKey)>{{ ucfirst($iconKey) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label>Sort order</label>
                        <input class="input" type="number" min="0" name="sort_order" value="{{ old('sort_order', 120) }}" required>
                    </div>
                    <div class="span2">
                        <button class="btn" type="submit">Add sport</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-pad">
            <div class="card-title">How Other works</div>
            <div class="card-copy" style="margin-top:8px">
                “Other” is always available in the app. When selected, the player types the sport name.
                Custom sports use the generic sport icon and do not alter this catalog.
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:18px">
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr><th>Sport</th><th>Icon</th><th>Order</th><th>Active</th><th></th></tr>
            </thead>
            <tbody>
            @forelse($sports as $sport)
                <tr>
                    <td>
                        <form id="sport-form-{{ $sport->id }}" method="post" action="{{ route('admin.sports.update', $sport) }}">
                            @csrf @method('patch')
                        </form>
                        <input class="input" form="sport-form-{{ $sport->id }}" name="name" value="{{ $sport->name }}" required>
                    </td>
                    <td>
                        <select class="select" form="sport-form-{{ $sport->id }}" name="icon_key" required>
                            @foreach($iconKeys as $iconKey)
                                <option value="{{ $iconKey }}" @selected($sport->icon_key === $iconKey)>{{ ucfirst($iconKey) }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td><input class="input" form="sport-form-{{ $sport->id }}" style="width:90px" type="number" min="0" name="sort_order" value="{{ $sport->sort_order }}" required></td>
                    <td><input class="switch" form="sport-form-{{ $sport->id }}" type="checkbox" name="is_active" value="1" @checked($sport->is_active)></td>
                    <td><button class="btn btn-secondary" form="sport-form-{{ $sport->id }}" type="submit">Save</button></td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty">No sports configured yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
