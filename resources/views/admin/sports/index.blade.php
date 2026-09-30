@extends('admin.layout')

@section('title', 'Sports')
@section('subtitle', 'Manage sports, icons and featured-match banner artwork.')

@section('content')
<style>
    .sports-page{display:grid;gap:18px}
    .sports-top{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,.65fr);gap:18px}
    .sports-card{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 5px 18px rgba(13,19,40,.035)}
    .sports-card-pad{padding:20px}
    .sports-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}
    .sports-title{font-size:16px;font-weight:700;color:var(--ink)}
    .sports-copy{font-size:11px;line-height:1.55;color:var(--muted);margin-top:5px}
    .sport-form-grid{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(150px,.8fr) 120px;gap:12px;align-items:end}
    .sport-form-grid .wide{grid-column:1/-1}
    .sports-help{display:flex;gap:12px;align-items:flex-start;padding:16px;border-radius:14px;background:#FFF8F3;border:1px solid #F7DCCB}
    .sports-help-icon{width:36px;height:36px;flex:0 0 36px;border-radius:11px;background:#FFF0E6;color:var(--orange);display:grid;place-items:center;font-weight:700}
    .sports-help b{display:block;font-size:12px;color:var(--ink)}
    .sports-help span{display:block;margin-top:5px;color:var(--muted);font-size:11px;line-height:1.55}

    .sports-section-head{display:flex;justify-content:space-between;align-items:end;gap:16px}
    .sports-count{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;padding:0 9px;border-radius:999px;background:#FFF0E6;color:var(--orange);font-size:11px;font-weight:700}
    .sports-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    .sport-item{background:#fff;border:1px solid var(--line);border-radius:18px;padding:16px;box-shadow:0 4px 14px rgba(13,19,40,.03)}
    .sport-item:hover{border-color:#F1D5C1;box-shadow:0 8px 24px rgba(13,19,40,.055)}
    .sport-item-top{display:grid;grid-template-columns:minmax(0,1fr) 150px auto;gap:10px;align-items:end}
    .sport-active{display:flex;align-items:center;gap:9px;height:43px;padding:0 10px;border:1px solid var(--line);border-radius:11px;background:#FCFCFD}
    .sport-active span{font-size:11px;font-weight:600;color:#475467}
    .sport-banner-block{margin-top:14px}
    .banner-label{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:7px}
    .banner-label span:first-child{font-size:11px;font-weight:600;color:#475467}
    .banner-size{font-size:10px;color:#98A2B3}
    .banner-preview{position:relative;width:100%;aspect-ratio:16/6.4;overflow:hidden;border-radius:14px;border:1px dashed #D0D5DD;background:linear-gradient(135deg,#FCFCFD,#F8F9FB);display:grid;place-items:center}
    .banner-preview img{width:100%;height:100%;object-fit:cover;display:block}
    .banner-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:7px;color:#98A2B3;font-size:10px}
    .banner-empty-mark{width:34px;height:34px;border-radius:10px;background:#fff;border:1px solid var(--line);display:grid;place-items:center;color:var(--orange);font-size:18px}
    .banner-actions{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:10px;flex-wrap:wrap}
    .file-input{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
    .upload-btn{height:38px;padding:0 13px;border-radius:10px;border:1px solid #D0D5DD;background:#fff;color:var(--ink);display:inline-flex;align-items:center;gap:7px;font-size:11px;font-weight:600;cursor:pointer}
    .upload-btn:hover{border-color:var(--orange);color:var(--orange);background:#FFF8F3}
    .upload-icon{font-size:15px;color:var(--orange)}
    .file-name{font-size:10px;color:#98A2B3;max-width:190px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .remove-banner{display:flex;align-items:center;gap:6px;color:#667085;font-size:10px;cursor:pointer}
    .sport-item-footer{display:grid;grid-template-columns:110px 1fr auto;gap:10px;align-items:end;margin-top:14px;padding-top:14px;border-top:1px solid #F2F4F7}
    .sport-save{min-width:82px}
    .sport-meta{font-size:10px;color:#98A2B3;align-self:center}
    .field.compact label{margin-bottom:6px}
    .input.compact,.select.compact{height:41px}
    .new-banner-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:10px;align-items:center;padding:12px;border:1px dashed #D0D5DD;border-radius:12px;background:#FCFCFD}
    .new-banner-copy b{font-size:11px;display:block}
    .new-banner-copy span{display:block;font-size:10px;color:#98A2B3;margin-top:3px}
    @media(max-width:1120px){.sports-top{grid-template-columns:1fr}.sports-grid{grid-template-columns:1fr}}
    @media(max-width:760px){
        .sport-form-grid,.sport-item-top,.sport-item-footer{grid-template-columns:1fr}
        .sport-active{justify-content:space-between}
        .sports-section-head{align-items:flex-start}
        .new-banner-row{grid-template-columns:1fr}
        .file-name{max-width:100%}
    }
</style>

<div class="sports-page">
    <div class="sports-top">
        <div class="sports-card">
            <div class="sports-card-pad">
                <div class="sports-card-head">
                    <div>
                        <div class="sports-title">Add sport</div>
                        <div class="sports-copy">Add a sport once and it becomes available in Create Match. You can also attach the image used behind featured matches.</div>
                    </div>
                </div>

                <form method="post" action="{{ route('admin.sports.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="sport-form-grid">
                        <div class="field compact">
                            <label>Sport name</label>
                            <input class="input compact" name="name" value="{{ old('name') }}" placeholder="e.g. Racquetball" required>
                        </div>
                        <div class="field compact">
                            <label>Icon</label>
                            <select class="select compact" name="icon_key" required>
                                @foreach($iconKeys as $iconKey)
                                    <option value="{{ $iconKey }}" @selected(old('icon_key') === $iconKey)>{{ ucfirst($iconKey) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field compact">
                            <label>Order</label>
                            <input class="input compact" type="number" min="0" name="sort_order" value="{{ old('sort_order', 120) }}" required>
                        </div>

                        <div class="wide">
                            <div class="new-banner-row">
                                <div class="new-banner-copy">
                                    <b>Featured banner image</b>
                                    <span>JPG, PNG or WebP · up to 5 MB</span>
                                </div>
                                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                                    <span class="file-name" data-file-name="new-banner">No file selected</span>
                                    <label class="upload-btn" for="new-banner">
                                        <span class="upload-icon">↑</span>
                                        Choose image
                                    </label>
                                    <input
                                        class="file-input"
                                        id="new-banner"
                                        data-file-input
                                        data-preview-target="new-banner-preview"
                                        type="file"
                                        name="banner_image"
                                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                    >
                                </div>
                            </div>
                            <div id="new-banner-preview" style="display:none"></div>
                        </div>

                        <div class="wide">
                            <button class="btn" type="submit">Add sport</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="sports-card">
            <div class="sports-card-pad">
                <div class="sports-title">How featured banners work</div>
                <div class="sports-copy">The banner image belongs to the sport, while the Featured toggle belongs to an individual match.</div>
                <div class="sports-help" style="margin-top:16px">
                    <div class="sports-help-icon">M</div>
                    <div>
                        <b>Other sports</b>
                        <span>“Other” stays available automatically. Custom sports use the generic icon and no sport banner unless you later add that sport to this catalog.</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="sports-section-head">
        <div>
            <div class="sports-title">Sport catalog</div>
            <div class="sports-copy">Edit the sport, icon, banner, order and availability without touching the app.</div>
        </div>
        <span class="sports-count">{{ $sports->count() }}</span>
    </div>

    @if($sports->isEmpty())
        <div class="sports-card"><div class="empty">No sports configured yet.</div></div>
    @else
        <div class="sports-grid">
            @foreach($sports as $sport)
                <form
                    class="sport-item"
                    method="post"
                    action="{{ route('admin.sports.update', $sport) }}"
                    enctype="multipart/form-data"
                >
                    @csrf
                    @method('patch')

                    <div class="sport-item-top">
                        <div class="field compact">
                            <label>Sport</label>
                            <input class="input compact" name="name" value="{{ $sport->name }}" required>
                        </div>

                        <div class="field compact">
                            <label>Icon</label>
                            <select class="select compact" name="icon_key" required>
                                @foreach($iconKeys as $iconKey)
                                    <option value="{{ $iconKey }}" @selected($sport->icon_key === $iconKey)>{{ ucfirst($iconKey) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <label class="sport-active">
                            <span>Active</span>
                            <input class="switch" type="checkbox" name="is_active" value="1" @checked($sport->is_active)>
                        </label>
                    </div>

                    <div class="sport-banner-block">
                        <div class="banner-label">
                            <span>Featured banner</span>
                            <span class="banner-size">Recommended 16:6 · max 5 MB</span>
                        </div>

                        <div class="banner-preview" id="banner-preview-{{ $sport->id }}">
                            @if($sport->banner_image_path)
                                <img src="{{ asset('storage/'.$sport->banner_image_path) }}" alt="{{ $sport->name }} banner">
                            @else
                                <div class="banner-empty">
                                    <div class="banner-empty-mark">▧</div>
                                    <span>No banner uploaded</span>
                                </div>
                            @endif
                        </div>

                        <div class="banner-actions">
                            <div style="display:flex;align-items:center;gap:9px;min-width:0">
                                <label class="upload-btn" for="banner-image-{{ $sport->id }}">
                                    <span class="upload-icon">↑</span>
                                    {{ $sport->banner_image_path ? 'Replace image' : 'Upload image' }}
                                </label>
                                <input
                                    class="file-input"
                                    id="banner-image-{{ $sport->id }}"
                                    data-file-input
                                    data-preview-target="banner-preview-{{ $sport->id }}"
                                    type="file"
                                    name="banner_image"
                                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                >
                                <span class="file-name" data-file-name="banner-image-{{ $sport->id }}">No new file</span>
                            </div>

                            @if($sport->banner_image_path)
                                <label class="remove-banner">
                                    <input type="checkbox" name="remove_banner" value="1">
                                    Remove current
                                </label>
                            @endif
                        </div>
                    </div>

                    <div class="sport-item-footer">
                        <div class="field compact">
                            <label>Order</label>
                            <input class="input compact" type="number" min="0" name="sort_order" value="{{ $sport->sort_order }}" required>
                        </div>
                        <div class="sport-meta">Lower numbers appear first in Create Match.</div>
                        <button class="btn sport-save" type="submit">Save</button>
                    </div>
                </form>
            @endforeach
        </div>
    @endif
</div>

<script>
    document.querySelectorAll('[data-file-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            const fileName = document.querySelector('[data-file-name="' + input.id + '"]');
            if (fileName) {
                fileName.textContent = file ? file.name : 'No new file';
            }

            if (!file || !file.type.startsWith('image/')) return;

            const targetId = input.dataset.previewTarget;
            const target = targetId ? document.getElementById(targetId) : null;
            if (!target || input.id === 'new-banner') return;

            const reader = new FileReader();
            reader.onload = (event) => {
                target.innerHTML = '<img src="' + event.target.result + '" alt="Banner preview">';
            };
            reader.readAsDataURL(file);
        });
    });
</script>
@endsection
