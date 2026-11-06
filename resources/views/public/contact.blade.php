@extends('public.layout')

@section('page_title', 'Contact Us | Mahj Around Town')
@section('meta_description', 'Have a question about Mahj Around Town? Contact our team using the public contact form.')

@section('content')
<main>
    <section class="sub-hero" aria-labelledby="contact-title">
        <div class="container">
            <div class="eyebrow"><span class="eyebrow-dot" aria-hidden="true"></span> We're here to help</div>
            <h1 id="contact-title">Let's stay connected.</h1>
            <p>Have a question, suggestion, or need some help with Mahj Around Town? Send us a message, and our team can follow up by email.</p>
        </div>
    </section>
    <section class="page-section">
        <div class="container contact-layout">
            <div class="content-panel contact-panel">
                <h2>Send us a message</h2>
                <p>Fill out the form below to reach the Mahj Around Town team.</p>
                @if(session('contact_received'))
                    <div class="notice success" role="status">Thanks for reaching out. Your message has been received, and our team will review it.</div>
                @endif
                @if($errors->any())
                    <div class="notice error" role="alert">Please review your information. {{ $errors->first() }}</div>
                @endif
                <form method="post" action="{{ route('public.contact.submit') }}">
                    @csrf
                    <div class="field-grid">
                        <div class="field"><label for="name">Your name</label><input class="input" type="text" id="name" name="name" autocomplete="name" required maxlength="120" value="{{ old('name') }}" placeholder="Your full name"></div>
                        <div class="field"><label for="email">Email address</label><input class="input" type="email" id="email" name="email" autocomplete="email" required maxlength="255" value="{{ old('email') }}" placeholder="you@example.com"></div>
                    </div>
                    <div class="field"><label for="subject">Subject</label><input class="input" type="text" id="subject" name="subject" required maxlength="160" value="{{ old('subject') }}" placeholder="What can we help you with?"></div>
                    <div class="field"><label for="message">Message</label><textarea class="input" id="message" name="message" required minlength="10" maxlength="3000" placeholder="Tell us a little more about your question...">{{ old('message') }}</textarea></div>
                    <div style="display:none" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
                    <button type="submit" class="btn btn-primary">Send Message <span aria-hidden="true">→</span></button>
                    <p class="form-help">Please don't include passwords, payment card details, or verification codes.</p>
                </form>
            </div>
            <aside class="content-panel contact-aside">
                <h3>Other ways to reach us</h3>
                <p>Prefer email? You can send your questions directly to our support address.</p>
                @if($supportEmail)
                    <a class="email" href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>
                @else
                    <p>Use the contact form and we'll follow up by email.</p>
                @endif
                <div class="divider"></div>
                <h3>Account support</h3>
                <p>For privacy questions or account deletion, you can also view the instructions on our dedicated page.</p>
                <a class="email" href="{{ route('public.account-deletion') }}">Account Deletion Information →</a>
            </aside>
        </div>
    </section>
</main>
@endsection
