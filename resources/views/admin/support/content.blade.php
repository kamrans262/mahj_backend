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
        <div class="card-copy" style="margin-bottom:14px">Edit the content visually. Use Heading for section titles, then format body text with bold, italic and lists as needed.</div>
        @if($item['page'])
            @php
                $editorHtml = data_get($item['page']->content, 'html');
                if (! is_string($editorHtml) || trim($editorHtml) === '') {
                    $editorHtml = '';
                    foreach (data_get($item['page']->content, 'sections', []) as $section) {
                        $editorHtml .= '<h2>'.e((string) data_get($section, 'title', '')).'</h2>';
                        foreach ((array) data_get($section, 'paragraphs', []) as $paragraph) {
                            $editorHtml .= '<p>'.e((string) $paragraph).'</p>';
                        }
                    }
                }
            @endphp
            <form method="post" action="{{ route('admin.content.pages.update', $item['page']) }}" data-rich-editor-form>
                @csrf @method('patch')
                <div class="field"><label>Title</label><input class="input" name="title" required value="{{ $item['page']->title }}"></div>
                <div class="field" style="margin-top:12px">
                    <label>Content</label>
                    <div class="rich-editor-shell">
                        <div class="rich-editor-toolbar" aria-label="Text formatting toolbar">
                            <button type="button" data-command="formatBlock" data-value="h2">Heading</button>
                            <button type="button" data-command="formatBlock" data-value="p">Paragraph</button>
                            <span class="rich-editor-divider"></span>
                            <button type="button" data-command="bold"><b>B</b></button>
                            <button type="button" data-command="italic"><i>I</i></button>
                            <button type="button" data-command="insertUnorderedList">• List</button>
                            <button type="button" data-command="insertOrderedList">1. List</button>
                            <button type="button" data-command="removeFormat">Clear</button>
                        </div>
                        <div class="rich-editor" contenteditable="true" spellcheck="true" data-rich-editor>{!! $editorHtml !!}</div>
                    </div>
                    <textarea name="content_html" data-rich-editor-input hidden>{{ $editorHtml }}</textarea>
                    <small>Headings become bold section titles in the app. Bold, italic and list formatting are preserved when published.</small>
                </div>
                <div style="margin-top:14px"><button class="btn" type="submit">Publish {{ $item['heading'] }}</button></div>
            </form>
        @else
            <div class="empty">Run the M10 content seeder to create this page.</div>
        @endif
    </div>
@endforeach

<style>
    .rich-editor-shell{border:1px solid #D0D5DD;border-radius:12px;background:#fff;overflow:hidden}
    .rich-editor-shell:focus-within{border-color:var(--orange);box-shadow:0 0 0 3px rgba(236,93,1,.1)}
    .rich-editor-toolbar{display:flex;align-items:center;gap:6px;flex-wrap:wrap;padding:8px;border-bottom:1px solid var(--line);background:#FCFCFD}
    .rich-editor-toolbar button{height:32px;padding:0 10px;border:1px solid #D0D5DD;border-radius:8px;background:#fff;color:var(--ink);font:inherit;font-size:11px;font-weight:600;cursor:pointer}
    .rich-editor-toolbar button:hover{border-color:#F4CDB5;background:#FFF8F3;color:var(--orange)}
    .rich-editor-divider{width:1px;height:22px;background:var(--line);margin:0 2px}
    .rich-editor{min-height:320px;padding:18px;outline:none;line-height:1.65;color:var(--ink)}
    .rich-editor h2,.rich-editor h3{margin:20px 0 8px;font-size:18px;line-height:1.35}
    .rich-editor h2:first-child,.rich-editor h3:first-child{margin-top:0}
    .rich-editor p{margin:0 0 12px}
    .rich-editor ul,.rich-editor ol{margin:0 0 12px;padding-left:24px}
    .rich-editor li{margin:4px 0}
</style>

<script>
document.querySelectorAll('[data-rich-editor-form]').forEach((form) => {
    const editor = form.querySelector('[data-rich-editor]');
    const input = form.querySelector('[data-rich-editor-input]');

    form.querySelectorAll('[data-command]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            editor.focus();
            document.execCommand(
                button.dataset.command,
                false,
                button.dataset.value || null
            );
        });
    });

    form.addEventListener('submit', () => {
        input.value = editor.innerHTML.trim();
    });
});
</script>
@endsection
