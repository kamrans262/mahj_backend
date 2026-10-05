@extends('admin.layout')

@section('title', 'Mah Jongg')
@section('subtitle', 'Manage the Mah Jongg match artwork used across featured matches.')

@section('content')
<style>
    .mahj-sport-page{display:grid;gap:18px}
    .mahj-sport-card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:20px;box-shadow:0 5px 18px rgba(13,19,40,.035)}
    .mahj-sport-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}
    .mahj-sport-title{font-size:16px;font-weight:700;color:var(--ink)}
    .mahj-sport-copy{font-size:11px;line-height:1.55;color:var(--muted);margin-top:5px}
    .mahj-static-row{display:grid;grid-template-columns:minmax(0,1fr) 120px;gap:12px;align-items:end}
    .banner-upload{margin-top:18px;padding-top:18px;border-top:1px solid #F2F4F7}
    .banner-upload-box{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:16px;border:1px dashed #D0D5DD;border-radius:14px;background:#FCFCFD;flex-wrap:wrap}
    .banner-upload-copy b{display:block;font-size:12px;color:var(--ink)}
    .banner-upload-copy span{display:block;margin-top:4px;font-size:10px;color:#98A2B3}
    .file-input{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
    .upload-btn{height:40px;padding:0 14px;border-radius:10px;border:1px solid #D0D5DD;background:#fff;color:var(--ink);display:inline-flex;align-items:center;gap:7px;font-size:11px;font-weight:600;cursor:pointer}
    .upload-btn:hover{border-color:var(--orange);color:var(--orange);background:#FFF8F3}
    .file-name{font-size:10px;color:#98A2B3;max-width:320px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .banner-gallery{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:16px}
    .banner-item{border:1px solid var(--line);border-radius:14px;overflow:hidden;background:#FCFCFD}
    .banner-image{aspect-ratio:16/7;overflow:hidden;background:#F8F9FB}
    .banner-image img{width:100%;height:100%;object-fit:cover;display:block}
    .banner-item-footer{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px}
    .banner-number{font-size:10px;color:#667085;font-weight:600}
    .remove-banner{display:flex;align-items:center;gap:6px;color:#667085;font-size:10px;cursor:pointer}
    .empty-gallery{margin-top:16px;padding:28px 16px;text-align:center;border:1px dashed #D0D5DD;border-radius:14px;color:#98A2B3;font-size:11px;background:#FCFCFD}
    .mahj-actions{display:flex;justify-content:flex-end;margin-top:18px}
    @media(max-width:900px){.banner-gallery{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:620px){.banner-gallery,.mahj-static-row{grid-template-columns:1fr}.file-name{max-width:100%}}
</style>

<div class="mahj-sport-page">
    @if($sports->isEmpty())
        <div class="mahj-sport-card">
            <div class="mahj-sport-title">Mah Jongg is not configured yet</div>
            <div class="mahj-sport-copy">Run the latest database migration to create the Mah Jongg-only catalog.</div>
        </div>
    @else
        @foreach($sports as $sport)
            <form
                class="mahj-sport-card"
                method="post"
                action="{{ route('admin.sports.update', $sport) }}"
                enctype="multipart/form-data"
            >
                @csrf
                @method('patch')
                <input type="hidden" name="name" value="Mah Jongg">
                <input type="hidden" name="icon_key" value="generic">

                <div class="mahj-sport-head">
                    <div>
                        <div class="mahj-sport-title">Mah Jongg</div>
                        <div class="mahj-sport-copy">
                            Mah Jongg is the only match type in the app. Upload multiple featured banners here so featured matches can use different artwork.
                        </div>
                    </div>
                </div>

                <div class="mahj-static-row">
                    <div class="field compact">
                        <label>Sport</label>
                        <input class="input compact" value="Mah Jongg" disabled>
                    </div>
                    <div class="field compact">
                        <label>Order</label>
                        <input class="input compact" type="number" min="0" name="sort_order" value="{{ $sport->sort_order }}" required>
                    </div>
                </div>

                <div class="banner-upload">
                    <div class="banner-upload-box">
                        <div class="banner-upload-copy">
                            <b>Add featured banner images</b>
                            <span>Select multiple JPG, PNG or WebP images · up to 5 MB each · maximum 20 per upload</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                            <span class="file-name" data-file-name="banner-images-{{ $sport->id }}">No files selected</span>
                            <label class="upload-btn" for="banner-images-{{ $sport->id }}">↑ Choose images</label>
                            <input
                                class="file-input"
                                id="banner-images-{{ $sport->id }}"
                                data-multi-banner-input
                                type="file"
                                name="banner_images[]"
                                multiple
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            >
                        </div>
                    </div>

                    @if($sport->bannerImages->isEmpty())
                        <div class="empty-gallery">No Mah Jongg banner images uploaded yet.</div>
                    @else
                        <div class="banner-gallery">
                            @foreach($sport->bannerImages as $index => $banner)
                                <div class="banner-item">
                                    <div class="banner-image">
                                        <img src="{{ asset('storage/'.$banner->image_path) }}" alt="Mah Jongg banner {{ $index + 1 }}">
                                    </div>
                                    <div class="banner-item-footer">
                                        <span class="banner-number">Banner {{ $index + 1 }}</span>
                                        <label class="remove-banner">
                                            <input type="checkbox" name="remove_banner_ids[]" value="{{ $banner->id }}">
                                            Remove
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="mahj-actions">
                    <button class="btn" type="submit">Save Changes</button>
                </div>
            </form>
        @endforeach
    @endif
</div>

<script>
    document.querySelectorAll('[data-multi-banner-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const label = document.querySelector('[data-file-name="' + input.id + '"]');
            if (!label) return;
            const count = input.files ? input.files.length : 0;
            label.textContent = count === 0
                ? 'No files selected'
                : count === 1
                    ? input.files[0].name
                    : count + ' images selected';
        });
    });
</script>
@endsection
