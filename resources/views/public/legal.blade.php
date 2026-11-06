@extends('public.layout')

@section('page_title', $page->title.' | Mahj Around Town')
@section('meta_description', 'Read the '.$page->title.' for Mahj Around Town.')

@section('content')
<main class="page-section">
    <div class="container legal-wrap">
        <article class="content-panel">
            <h1>{{ $page->title }}</h1>
            @if($isPreview ?? false)
                <p class="legal-updated"><strong style="color:#EC5D01">Unpublished preview</strong> · Your edits have not been saved.</p>
            @else
                <p class="legal-updated">Last updated: {{ $page->published_at?->format('F j, Y') }}</p>
            @endif
            <div class="legal-content">{!! $html !!}</div>

            @if($slug === 'account-deletion' && !($isPreview ?? false))
                <section class="legal-deletion" aria-labelledby="deletion-request-title">
                    <h2 id="deletion-request-title">Request account deletion</h2>
                    <p>If you cannot access Mahj Around Town, use the form below to request deletion. Enter the email associated with your account. We will verify your identity before taking any action. Sending this form does not immediately delete your account.</p>
                    @if(session('deletion_request_received'))
                        <div class="notice success" role="status">Your request has been received. Our team will review it and may contact you to verify account ownership.</div>
                    @endif
                    @if($errors->any())
                        <div class="notice error" role="alert">{{ $errors->first() }}</div>
                    @endif
                    <form method="post" action="{{ route('public.account-deletion.request') }}">
                        @csrf
                        <div class="field">
                            <label for="email">Account email address</label>
                            <input class="input" id="email" name="email" type="email" autocomplete="email" maxlength="255" required value="{{ old('email') }}">
                        </div>
                        <div class="field">
                            <label for="note">Additional information (optional)</label>
                            <textarea class="input" id="note" name="note" maxlength="2000" placeholder="Only information needed to help locate your account. Do not include passwords or verification codes.">{{ old('note') }}</textarea>
                        </div>
                        <div style="display:none" aria-hidden="true"><label for="company_website">Company website</label><input id="company_website" name="company_website" tabindex="-1" autocomplete="off"></div>
                        <button type="submit" class="btn btn-primary">Submit deletion request <span aria-hidden="true">→</span></button>
                    </form>
                    @if($supportEmail)
                        <p class="form-help">Need assistance? Contact <a href="mailto:{{ $supportEmail }}" style="color:#EC5D01">{{ $supportEmail }}</a>.</p>
                    @endif
                </section>
            @endif
        </article>
    </div>
</main>
@endsection
