@extends('admin.layout')

@section('title', 'Content')
@section('subtitle', 'Manage FAQs, legal content and the support email shown in the app.')

@section('content')
<div class="grid2">
    <section class="card" style="padding:18px">
        <div class="card-title">Support email</div>
        <div class="card-copy" style="margin-bottom:14px">Used by the Email Support action in the app.</div>
        <form method="post" action="{{ route('admin.content.support.update') }}">
            @csrf @method('patch')
            <div class="field">
                <label>Email address</label>
                <input class="input" type="email" name="support_email" required value="{{ old('support_email', data_get($support?->content, 'support_email', config('mail.from.address'))) }}">
            </div>
            <div style="margin-top:14px"><button class="btn" type="submit">Save support email</button></div>
        </form>
    </section>

    <section class="card" style="padding:18px">
        <div class="card-title">Add FAQ</div>
        <div class="card-copy" style="margin-bottom:14px">Published FAQs appear inside the merged Support / FAQ screen.</div>
        <form method="post" action="{{ route('admin.content.faqs.store') }}">
            @csrf
            <div class="field"><label>Question</label><input class="input" name="question" required></div>
            <div class="field" style="margin-top:12px"><label>Answer</label><textarea class="input" name="answer" rows="4" required></textarea></div>
            <div class="form-grid" style="margin-top:12px">
                <div class="field"><label>Sort order</label><input class="input" type="number" min="0" name="sort_order" value="10" required></div>
                <label class="switch-row"><span><b>Published</b><span>Show this FAQ in the app</span></span><input class="switch" type="checkbox" name="is_active" value="1" checked></label>
            </div>
            <div style="margin-top:14px"><button class="btn" type="submit">Add FAQ</button></div>
        </form>
    </section>
</div>

<div class="card" style="padding:18px;margin-top:16px">
    <div class="card-title">FAQs</div>
    <div class="card-copy" style="margin-bottom:14px">Edit, publish or remove existing FAQ content.</div>
    @forelse($faqs as $faq)
        <details style="margin-bottom:10px">
            <summary>{{ $faq->question }} · {{ $faq->is_active ? 'Published' : 'Hidden' }}</summary>
            <div class="details-body">
                <form method="post" action="{{ route('admin.content.faqs.update', $faq) }}">
                    @csrf @method('patch')
                    <div class="field"><label>Question</label><input class="input" name="question" required value="{{ $faq->question }}"></div>
                    <div class="field" style="margin-top:12px"><label>Answer</label><textarea class="input" name="answer" rows="4" required>{{ $faq->answer }}</textarea></div>
                    <div class="form-grid" style="margin-top:12px">
                        <div class="field"><label>Sort order</label><input class="input" type="number" min="0" name="sort_order" value="{{ $faq->sort_order }}" required></div>
                        <label class="switch-row"><span><b>Published</b><span>Show this FAQ in the app</span></span><input class="switch" type="checkbox" name="is_active" value="1" @checked($faq->is_active)></label>
                    </div>
                    <div style="margin-top:14px;display:flex;gap:8px"><button class="btn" type="submit">Save FAQ</button></div>
                </form>
                <form method="post" action="{{ route('admin.content.faqs.delete', $faq) }}" style="margin-top:8px">
                    @csrf @method('delete')
                    <button class="btn btn-secondary" type="submit">Delete FAQ</button>
                </form>
            </div>
        </details>
    @empty
        <div class="empty">No FAQs have been created.</div>
    @endforelse
</div>

@foreach([['page' => $terms, 'heading' => 'Terms & Conditions'], ['page' => $privacy, 'heading' => 'Privacy Policy']] as $item)
    <div class="card" style="padding:18px;margin-top:16px">
        <div class="card-title">{{ $item['heading'] }}</div>
        <div class="card-copy" style="margin-bottom:14px">Edit the structured sections returned to the app. Publishing updates the displayed last-updated date.</div>
        @if($item['page'])
            <form method="post" action="{{ route('admin.content.pages.update', $item['page']) }}">
                @csrf @method('patch')
                <div class="field"><label>Title</label><input class="input" name="title" required value="{{ $item['page']->title }}"></div>
                <div class="field" style="margin-top:12px">
                    <label>Content JSON</label>
                    <textarea class="input" name="content_json" rows="14" required>{{ json_encode($item['page']->content, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</textarea>
                    <small>Keep a top-level "sections" array. Each section uses "title" and "paragraphs".</small>
                </div>
                <div style="margin-top:14px"><button class="btn" type="submit">Publish {{ $item['heading'] }}</button></div>
            </form>
        @else
            <div class="empty">Run the M10 content seeder to create this page.</div>
        @endif
    </div>
@endforeach
@endsection
