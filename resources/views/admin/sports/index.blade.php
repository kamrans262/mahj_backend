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
            <form method="post" action="{{ route('admin.sports.store') }}" enctype="multipart/form-data">
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
                    <div class="span2 field">
                        <label>Featured banner image</label>
                        <input class="input" style="padding-top:9px" type="file" name="banner_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        <small>Used behind featured matches for this sport. JPG, PNG or WebP up to 5 MB.</small>
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
                <tr><th>Sport</th><th>Icon</th><th>Featured banner</th><th>Order</th><th>Active</th><th></th></tr>
            </thead>
            <tbody>
            @forelse($sports as $sport)
                <tr>
                    <td>
                        <form id="sport-form-{{ $sport->id }}" method="post" action="{{ route('admin.sports.update', $sport) }}" enctype="multipart/form-data">
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
                    <td style="min-width:220px">
                        @if($sport->banner_image_path)
                            <img src="{{ asset('storage/'.$sport->banner_image_path) }}" alt="{{ $sport->name }} banner" style="width:150px;height:74px;object-fit:cover;border-radius:10px;border:1px solid #EAECF0;display:block;margin-bottom:8px">
                        @else
                            <div style="width:150px;height:74px;border-radius:10px;border:1px dashed #D0D5DD;display:grid;place-items:center;color:#98A2B3;font-size:10px;margin-bottom:8px">No banner</div>
                        @endif
                        <input class="input" form="sport-form-{{ $sport->id }}" style="padding-top:9px" type="file" name="banner_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        @if($sport->banner_image_path)
                            <label style="display:flex;align-items:center;gap:6px;margin-top:7px;font-size:10px;color:#667085">
                                <input form="sport-form-{{ $sport->id }}" type="checkbox" name="remove_banner" value="1"> Remove current banner
                            </label>
                        @endif
                    </td>
                    <td><input class="input" form="sport-form-{{ $sport->id }}" style="width:90px" type="number" min="0" name="sort_order" value="{{ $sport->sort_order }}" required></td>
                    <td><input class="switch" form="sport-form-{{ $sport->id }}" type="checkbox" name="is_active" value="1" @checked($sport->is_active)></td>
                    <td><button class="btn btn-secondary" form="sport-form-{{ $sport->id }}" type="submit">Save</button></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No sports configured yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
